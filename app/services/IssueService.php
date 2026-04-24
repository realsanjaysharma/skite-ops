<?php

require_once __DIR__ . '/../repositories/IssueRepository.php';

require_once __DIR__ . '/AuditService.php';

class IssueService
{
    private IssueRepository $issueRepo;
    private AuditService $auditService;

    public function __construct()
    {
        $this->issueRepo = new IssueRepository();
        $this->auditService = new AuditService();
    }

    /**
     * Create an issue manually or via automated systems.
     * Ops direct manual creation has 'MANUAL_OPS' source_type.
     */
    public function createIssue(array $data, int $actorUserId, string $actorRoleKey): array
    {
        if ($actorRoleKey !== 'OPS_MANAGER') {
            throw new DomainException("Only Ops can create issues directly.");
        }

        if (empty($data['title']) || empty($data['priority'])) {
            throw new InvalidArgumentException("Title and priority are required.");
        }

        $validPriorities = ['LOW', 'MEDIUM', 'HIGH', 'CRITICAL'];
        if (!in_array($data['priority'], $validPriorities, true)) {
            throw new InvalidArgumentException("Invalid priority.");
        }

        $insertData = [
            'source_type' => $data['source_type'] ?? 'MANUAL_OPS',
            'source_reference_id' => $data['source_reference_id'] ?? null,
            'belt_id' => $data['belt_id'] ?? null,
            'site_id' => $data['site_id'] ?? null,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'priority' => $data['priority'],
            'status' => 'OPEN',
            'raised_by_user_id' => $actorUserId,
        ];

        $newId = $this->issueRepo->create($insertData);

        $this->auditService->logAction(
            $actorUserId,
            'ISSUE_CREATED',
            'issue',
            $newId,
            null,
            $insertData
        );

        return $this->issueRepo->findById($newId);
    }

    /**
     * Transition issue OPEN -> IN_PROGRESS.
     */
    public function markInProgress(int $issueId, int $actorUserId, string $actorRoleKey): array
    {
        $issue = $this->issueRepo->findById($issueId);
        if (!$issue) {
            throw new InvalidArgumentException("Issue not found.");
        }

        if ($issue['status'] !== 'OPEN') {
            throw new DomainException("Issue must be OPEN to move IN_PROGRESS.");
        }

        // Apply role-based rules
        if ($actorRoleKey === 'HEAD_SUPERVISOR') {
            if (empty($issue['belt_id'])) {
                throw new DomainException("Head Supervisor can only operate on green-belt issues.");
            }
        } elseif ($actorRoleKey !== 'OPS_MANAGER') {
            throw new DomainException("Role not authorized to transition issue.");
        }

        $this->issueRepo->update([
            'id' => $issueId,
            'status' => 'IN_PROGRESS'
        ]);

        $this->auditService->logAction(
            $actorUserId,
            'ISSUE_IN_PROGRESS',
            'issue',
            $issueId,
            ['status' => $issue['status']],
            ['status' => 'IN_PROGRESS']
        );

        return $this->issueRepo->findById($issueId);
    }

    /**
     * Transition issue to CLOSED. Only Ops can close.
     */
    public function closeIssue(int $issueId, int $actorUserId, string $actorRoleKey): array
    {
        if ($actorRoleKey !== 'OPS_MANAGER') {
            throw new DomainException("Only Ops can close an issue.");
        }

        $issue = $this->issueRepo->findById($issueId);
        if (!$issue) {
            throw new InvalidArgumentException("Issue not found.");
        }

        $this->issueRepo->update([
            'id' => $issueId,
            'status' => 'CLOSED',
            'closed_by_user_id' => $actorUserId,
            'closed_at' => date('Y-m-d H:i:s'),
        ]);

        $this->auditService->logAction(
            $actorUserId,
            'ISSUE_CLOSED',
            'issue',
            $issueId,
            [
                'status' => $issue['status'],
                'closed_by_user_id' => $issue['closed_by_user_id'],
                'closed_at' => $issue['closed_at']
            ],
            [
                'status' => 'CLOSED',
                'closed_by_user_id' => $actorUserId,
                'closed_at' => date('Y-m-d H:i:s')
            ]
        );

        return $this->issueRepo->findById($issueId);
    }

    /**
     * Link an existing task to an issue.
     */
    public function linkTask(int $issueId, int $taskId, int $actorUserId, string $actorRoleKey): array
    {
        if ($actorRoleKey !== 'OPS_MANAGER') {
            throw new DomainException("Only Ops can link a task to an issue.");
        }

        $issue = $this->issueRepo->findById($issueId);
        if (!$issue) {
            throw new InvalidArgumentException("Issue not found.");
        }

        // Issue-to-task linking is via tasks.linked_issue_id, not on the issues table
        require_once __DIR__ . '/../repositories/TaskRepository.php';
        $taskRepo = new TaskRepository();
        $task = $taskRepo->findById($taskId);
        if (!$task) {
            throw new InvalidArgumentException("Task not found.");
        }

        $taskRepo->update([
            'id' => $taskId,
            'linked_issue_id' => $issueId,
        ]);

        $this->auditService->logAction(
            $actorUserId,
            'ISSUE_TASK_LINKED',
            'issue',
            $issueId,
            ['linked_task_id' => null],
            ['linked_task_id' => $taskId]
        );

        return $this->issueRepo->findById($issueId);
    }

    /**
     * List issues with role scoping.
     */
    public function listIssues(array $filters, string $actorRoleKey): array
    {
        if ($actorRoleKey === 'HEAD_SUPERVISOR') {
            $filters['restrict_to_belts'] = true;
        }

        return $this->issueRepo->findAll($filters);
    }

    /**
     * Get a specific issue.
     */
    public function getIssue(int $issueId, string $actorRoleKey): ?array
    {
        $issue = $this->issueRepo->findById($issueId);
        
        if ($issue && $actorRoleKey === 'HEAD_SUPERVISOR' && empty($issue['belt_id'])) {
            // Act as if it does not exist if it's out of scope
            return null; 
        }

        return $issue;
    }
}
