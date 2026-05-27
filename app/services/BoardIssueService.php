<?php

require_once __DIR__ . '/../repositories/BoardIssueRepository.php';
require_once __DIR__ . '/../repositories/BeltUserAssignmentRepository.php';
require_once __DIR__ . '/../repositories/IssueRepository.php';
require_once __DIR__ . '/../repositories/TaskRepository.php';
require_once __DIR__ . '/../repositories/UploadRepository.php';
require_once __DIR__ . '/UploadStorageService.php';
require_once __DIR__ . '/IssueService.php';
require_once __DIR__ . '/AuditService.php';

/**
 * BoardIssueService
 *
 * Business logic for electrician board issue resolution.
 */
class BoardIssueService
{
    private BoardIssueRepository $boardIssueRepo;
    private BeltUserAssignmentRepository $assignmentRepo;
    private IssueRepository $issueRepo;
    private IssueService $issueService;
    private TaskRepository $taskRepo;
    private UploadRepository $uploadRepo;
    private UploadStorageService $storageService;
    private AuditService $auditService;

    public function __construct()
    {
        $this->boardIssueRepo = new BoardIssueRepository();
        $this->assignmentRepo = new BeltUserAssignmentRepository();
        $this->issueRepo = new IssueRepository();
        $this->issueService = new IssueService();
        $this->taskRepo = new TaskRepository();
        $this->uploadRepo = new UploadRepository();
        $this->storageService = new UploadStorageService();
        $this->auditService = new AuditService();
    }

    /**
     * Get combined issue list for the electrician: belt-assigned + task-assigned, deduped.
     */
    public function getIssueList(int $userId): array
    {
        $beltIds = $this->assignmentRepo->getActiveBeltIdsForUser($userId, 'ELECTRICIAN');

        $beltIssues = $this->boardIssueRepo->getIssuesByBeltAssignment(
            array_map('intval', $beltIds)
        );
        $taskIssues = $this->boardIssueRepo->getIssuesByTaskAssignment($userId);

        // Dedup by issue ID
        $seen = [];
        $merged = [];
        foreach (array_merge($beltIssues, $taskIssues) as $issue) {
            $id = (int) $issue['id'];
            if (!isset($seen[$id])) {
                $seen[$id] = true;
                $merged[] = $issue;
            }
        }

        // Sort: priority DESC (CRITICAL first), then created_at ASC
        usort($merged, function ($a, $b) {
            $priorityOrder = ['CRITICAL' => 0, 'HIGH' => 1, 'MEDIUM' => 2, 'LOW' => 3];
            $pa = $priorityOrder[$a['priority']] ?? 4;
            $pb = $priorityOrder[$b['priority']] ?? 4;
            if ($pa !== $pb) return $pa - $pb;
            return strcmp($a['created_at'], $b['created_at']);
        });

        return $merged;
    }

    /**
     * Get issue detail with photos.
     */
    public function getIssueDetail(int $issueId, int $userId): array
    {
        $issue = $this->boardIssueRepo->getIssueDetail($issueId);
        if (!$issue) {
            throw new InvalidArgumentException('Issue not found.');
        }

        // Load original monitoring photos (work_type IS NULL)
        $originalPhotos = [];
        $fixPhotos = [];
        $reportId = $issue['report_id'] ?? null;

        if ($reportId) {
            $allUploads = $this->uploadRepo->findAll([
                'parent_type' => 'BOARD_MONITORING',
                'parent_id' => (int) $reportId,
                'is_deleted' => 0,
            ]);

            foreach ($allUploads as $upload) {
                if ($upload['work_type'] === 'REPAIR') {
                    $fixPhotos[] = $upload;
                } else {
                    $originalPhotos[] = $upload;
                }
            }
        }

        // Determine expected fix count
        $expectedFixCount = 1; // default for non-board-monitoring issues
        if ($reportId && $issue['report_status']) {
            if ($issue['report_status'] === 'ALL_OFF') {
                $expectedFixCount = (int) $issue['total_boards'];
            } elseif ($issue['report_status'] === 'PARTIAL_OFF') {
                $expectedFixCount = (int) $issue['off_count'];
            }
        }

        // Check if linked to a task assigned to this user
        $linkedTaskId = null;
        $taskRows = $this->taskRepo->findByLinkedIssueId($issueId);
        foreach ($taskRows as $task) {
            if ((int) $task['assigned_lead_user_id'] === $userId) {
                $linkedTaskId = (int) $task['id'];
                break;
            }
        }

        return [
            'issue' => $issue,
            'original_photos' => $originalPhotos,
            'fix_photos' => $fixPhotos,
            'expected_fix_count' => $expectedFixCount,
            'has_linked_report' => $reportId !== null,
            'linked_task_id' => $linkedTaskId,
        ];
    }

    /**
     * Start fix: move issue to IN_PROGRESS.
     */
    public function startFix(int $issueId, int $userId, string $roleKey): array
    {
        return $this->issueService->markInProgress($issueId, $userId, $roleKey);
    }

    /**
     * Resolve issue with fix photos and comment.
     */
    public function resolveIssue(int $issueId, array $data, array $rawFiles, int $userId, string $roleKey): array
    {
        $issue = $this->boardIssueRepo->getIssueDetail($issueId);
        if (!$issue) {
            throw new InvalidArgumentException('Issue not found.');
        }

        if ($issue['status'] !== 'IN_PROGRESS') {
            throw new DomainException('Issue must be IN_PROGRESS to resolve.');
        }

        $comment = trim($data['comment'] ?? '');
        if ($comment === '') {
            throw new InvalidArgumentException('A comment describing the fix is required.');
        }

        // Validate + normalize photos
        if (empty($rawFiles['files']) || empty($rawFiles['files']['tmp_name'])) {
            throw new InvalidArgumentException('Fix photos are required.');
        }

        $normalized = $this->storageService->normalizeFiles($rawFiles['files']);
        $validated = $this->storageService->validateFiles($normalized);

        // Determine expected photo count
        $reportId = $issue['report_id'] ?? null;
        $hasLinkedReport = $reportId !== null;

        if ($hasLinkedReport) {
            $expectedCount = 1;
            if ($issue['report_status'] === 'ALL_OFF') {
                $expectedCount = (int) $issue['total_boards'];
            } elseif ($issue['report_status'] === 'PARTIAL_OFF') {
                $expectedCount = (int) $issue['off_count'];
            }
            if (count($validated) !== $expectedCount) {
                throw new InvalidArgumentException(
                    "Expected {$expectedCount} fix photos, got " . count($validated) . "."
                );
            }
        } else {
            // No linked report — require at least 1 photo
            if (count($validated) < 1) {
                throw new InvalidArgumentException('At least one fix photo is required.');
            }
        }

        // Determine upload parent
        $parentType = 'BOARD_MONITORING';
        $parentId = $reportId;

        // For task-assigned issues without a board monitoring report
        if (!$hasLinkedReport) {
            $taskRows = $this->taskRepo->findByLinkedIssueId($issueId);
            $linkedTask = null;
            foreach ($taskRows as $task) {
                if ((int) $task['assigned_lead_user_id'] === $userId) {
                    $linkedTask = $task;
                    break;
                }
            }
            if ($linkedTask) {
                $parentType = 'TASK';
                $parentId = (int) $linkedTask['id'];
            } else {
                throw new DomainException('Cannot determine upload target for this issue.');
            }
        }

        // Begin transaction
        $this->issueRepo->beginTransaction();
        try {
            // Store fix photos
            $totalFix = count($validated);
            foreach ($validated as $index => $file) {
                $boardNum = $index + 1;
                $stored = $this->storageService->storeValidatedFile($file, $parentType, $parentId);

                $commentText = $hasLinkedReport ? "Fix Board {$boardNum} of {$totalFix}" : $comment;

                $this->uploadRepo->create([
                    'parent_type' => $parentType,
                    'parent_id' => $parentId,
                    'upload_type' => 'WORK',
                    'work_type' => 'REPAIR',
                    'is_discovery_mode' => 0,
                    'file_path' => $stored['file_path'],
                    'original_file_name' => $stored['original_file_name'],
                    'mime_type' => $stored['mime_type'],
                    'file_size_bytes' => $stored['file_size_bytes'],
                    'photo_label' => 'GENERAL',
                    'site_condition' => null,
                    'comment_text' => $commentText,
                    'gps_latitude' => null,
                    'gps_longitude' => null,
                    'authority_visibility' => 'NOT_ELIGIBLE',
                    'created_by_user_id' => $userId,
                ]);
            }

            // Resolve the issue
            $now = date('Y-m-d H:i:s');
            $this->issueRepo->update([
                'id' => $issueId,
                'status' => 'RESOLVED',
                'resolved_by_user_id' => $userId,
                'resolved_at' => $now,
            ]);

            // If linked to a task, mark task completed
            $taskRows = $this->taskRepo->findByLinkedIssueId($issueId);
            foreach ($taskRows as $task) {
                if ((int) $task['assigned_lead_user_id'] === $userId
                    && in_array($task['status'], ['OPEN', 'RUNNING'], true)) {
                    $this->taskRepo->update([
                        'id' => (int) $task['id'],
                        'status' => 'COMPLETED',
                        'actual_close_date' => date('Y-m-d'),
                    ]);
                }
            }

            $this->auditService->logAction(
                $userId,
                'BOARD_ISSUE_RESOLVED',
                'issue',
                $issueId,
                ['status' => 'IN_PROGRESS'],
                ['status' => 'RESOLVED', 'resolved_by_user_id' => $userId, 'comment' => $comment]
            );

            $this->issueRepo->commit();

            return ['issue_id' => $issueId, 'status' => 'RESOLVED'];
        } catch (\Throwable $e) {
            $this->issueRepo->rollback();
            throw $e;
        }
    }
}
