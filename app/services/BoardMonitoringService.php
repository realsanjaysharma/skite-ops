<?php

require_once __DIR__ . '/../repositories/BoardMonitoringRepository.php';
require_once __DIR__ . '/../repositories/BeltUserAssignmentRepository.php';
require_once __DIR__ . '/../repositories/BeltRepository.php';
require_once __DIR__ . '/../repositories/IssueRepository.php';
require_once __DIR__ . '/../repositories/UploadRepository.php';
require_once __DIR__ . '/UploadStorageService.php';
require_once __DIR__ . '/AuditService.php';

/**
 * BoardMonitoringService
 *
 * Business logic for board monitoring report submission and history.
 * Transactions controlled here. UploadStorageService::storeValidatedFile()
 * handles file I/O only (no transaction inside it for our usage pattern).
 */
class BoardMonitoringService
{
    private BoardMonitoringRepository $reportRepo;
    private BeltUserAssignmentRepository $assignmentRepo;
    private BeltRepository $beltRepo;
    private IssueRepository $issueRepo;
    private UploadRepository $uploadRepo;
    private UploadStorageService $storageService;
    private AuditService $auditService;

    public function __construct()
    {
        $this->reportRepo = new BoardMonitoringRepository();
        $this->assignmentRepo = new BeltUserAssignmentRepository();
        $this->beltRepo = new BeltRepository();
        $this->issueRepo = new IssueRepository();
        $this->uploadRepo = new UploadRepository();
        $this->storageService = new UploadStorageService();
        $this->auditService = new AuditService();
    }

    /**
     * Get assigned belts with today's report status.
     */
    public function getMyBelts(int $userId): array
    {
        $today = date('Y-m-d');
        return $this->reportRepo->getMyBelts($userId, $today);
    }

    /**
     * Submit a board monitoring report with photos.
     */
    public function submitReport(array $data, array $rawFiles, int $userId): array
    {
        $beltId = (int) ($data['belt_id'] ?? 0);
        $status = $data['status'] ?? '';
        $offCount = isset($data['off_count']) ? (int) $data['off_count'] : null;
        $notes = $data['notes'] ?? null;
        $gpsLat = isset($data['latitude']) ? (float) $data['latitude'] : null;
        $gpsLng = isset($data['longitude']) ? (float) $data['longitude'] : null;
        $today = date('Y-m-d');

        // Validate status
        $validStatuses = ['ALL_OK', 'ALL_OFF', 'PARTIAL_OFF'];
        if (!in_array($status, $validStatuses, true)) {
            throw new InvalidArgumentException('Invalid status. Must be ALL_OK, ALL_OFF, or PARTIAL_OFF.');
        }

        // Validate belt assignment
        $activeBelts = $this->assignmentRepo->getActiveBeltIdsForUser($userId, 'BOARD_MONITOR');
        if (!in_array($beltId, array_map('intval', $activeBelts), true)) {
            throw new DomainException('You are not assigned to this belt for board monitoring.');
        }

        // Validate belt has board_count
        $belt = $this->beltRepo->findById($beltId);
        if (!$belt || empty($belt['board_count']) || (int) $belt['board_count'] <= 0) {
            throw new DomainException('This belt does not have a board count configured.');
        }

        $totalBoards = (int) $belt['board_count'];

        // Validate off_count for PARTIAL_OFF
        if ($status === 'PARTIAL_OFF') {
            if ($offCount === null || $offCount <= 0) {
                throw new InvalidArgumentException('off_count is required and must be > 0 for PARTIAL_OFF.');
            }
            if ($offCount > $totalBoards) {
                throw new InvalidArgumentException("off_count ({$offCount}) cannot exceed total boards ({$totalBoards}).");
            }
        } else {
            $offCount = null;
        }

        // Check for duplicate report today
        $existing = $this->reportRepo->findTodayReport($beltId, $userId, $today);
        if ($existing) {
            throw new DomainException('A report for this belt has already been submitted today.');
        }

        // Validate photos
        if (empty($rawFiles['files']) || empty($rawFiles['files']['tmp_name'])) {
            throw new InvalidArgumentException('Photos are required.');
        }

        $normalized = $this->storageService->normalizeFiles($rawFiles['files']);
        $validated = $this->storageService->validateFiles($normalized);

        if (count($validated) !== $totalBoards) {
            throw new InvalidArgumentException(
                "Expected {$totalBoards} photos (one per board), got " . count($validated) . "."
            );
        }

        // Begin transaction
        $this->reportRepo->beginTransaction();
        try {
            // Create report
            $reportId = $this->reportRepo->createReport([
                'belt_id' => $beltId,
                'user_id' => $userId,
                'report_date' => $today,
                'status' => $status,
                'off_count' => $offCount,
                'total_boards' => $totalBoards,
                'gps_latitude' => $gpsLat,
                'gps_longitude' => $gpsLng,
                'notes' => $notes,
            ]);

            // Store photos
            foreach ($validated as $index => $file) {
                $boardNum = $index + 1;
                $stored = $this->storageService->storeValidatedFile($file, 'BOARD_MONITORING', $reportId);

                $this->uploadRepo->create([
                    'parent_type' => 'BOARD_MONITORING',
                    'parent_id' => $reportId,
                    'upload_type' => 'WORK',
                    'work_type' => null,
                    'is_discovery_mode' => 0,
                    'file_path' => $stored['file_path'],
                    'original_file_name' => $stored['original_file_name'],
                    'mime_type' => $stored['mime_type'],
                    'file_size_bytes' => $stored['file_size_bytes'],
                    'photo_label' => 'GENERAL',
                    'site_condition' => null,
                    'comment_text' => "Board {$boardNum} of {$totalBoards}",
                    'gps_latitude' => $gpsLat,
                    'gps_longitude' => $gpsLng,
                    'authority_visibility' => 'NOT_ELIGIBLE',
                    'created_by_user_id' => $userId,
                ]);
            }

            // Auto-create issue if not ALL_OK
            $issueId = null;
            if ($status !== 'ALL_OK') {
                $issueTitle = $status === 'ALL_OFF'
                    ? "Belt {$belt['belt_code']} — All Off"
                    : "Belt {$belt['belt_code']} — {$offCount} of {$totalBoards} boards off";

                $issueId = $this->issueRepo->create([
                    'source_type' => 'BOARD_MONITORING',
                    'source_reference_id' => $reportId,
                    'belt_id' => $beltId,
                    'site_id' => null,
                    'title' => $issueTitle,
                    'description' => $notes,
                    'priority' => 'HIGH',
                    'status' => 'OPEN',
                    'raised_by_user_id' => $userId,
                ]);

                $this->reportRepo->setIssueId($reportId, $issueId);
            }

            $this->reportRepo->commit();

            return [
                'report_id' => $reportId,
                'issue_id' => $issueId,
                'status' => $status,
                'belt_code' => $belt['belt_code'],
            ];
        } catch (\Throwable $e) {
            $this->reportRepo->rollback();
            throw $e;
        }
    }

    /**
     * Get paginated report history.
     */
    public function getHistory(int $userId, array $filters, int $page, int $limit): array
    {
        return $this->reportRepo->getHistory($userId, $filters, $page, $limit);
    }

    /**
     * Get photos for a specific report.
     */
    public function getReportPhotos(int $reportId): array
    {
        return $this->uploadRepo->findAll([
            'parent_type' => 'BOARD_MONITORING',
            'parent_id' => $reportId,
            'is_deleted' => 0,
        ]);
    }
}
