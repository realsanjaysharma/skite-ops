<?php

require_once __DIR__ . '/../repositories/TaskRepository.php';
require_once __DIR__ . '/../repositories/RequestRepository.php';
require_once __DIR__ . '/../repositories/IssueRepository.php';
require_once __DIR__ . '/../repositories/UploadRepository.php';

class TaskService
{
    private TaskRepository $taskRepo;
    private RequestRepository $requestRepo;
    private IssueRepository $issueRepo;
    private UploadRepository $uploadRepo;

    public function __construct()
    {
        $this->taskRepo = new TaskRepository();
        $this->requestRepo = new RequestRepository();
        $this->issueRepo = new IssueRepository();
        $this->uploadRepo = new UploadRepository();
    }

    /**
     * Create a new task. Updates related intake entities (like requests or issues) if mapped.
     */
    public function createTask(array $data, int $actorUserId, string $actorRoleKey): array
    {
        if ($actorRoleKey !== 'OPS_MANAGER') {
            throw new DomainException("Only Ops can create tasks.");
        }

        $sourceType = $data['task_source_type'] ?? 'MANUAL';
        $requestId = !empty($data['request_id']) ? (int) $data['request_id'] : null;
        $linkedIssueId = !empty($data['linked_issue_id']) ? (int) $data['linked_issue_id'] : null;

        // Validation for Request conversions
        if ($sourceType === 'REQUEST' && $requestId) {
            $request = $this->requestRepo->findById($requestId);
            if (!$request) {
                throw new InvalidArgumentException("Provided request_id does not exist.");
            }
            if ($request['status'] !== 'APPROVED') {
                throw new DomainException("Tasks can only be created from APPROVED requests.");
            }
        }

        // Prepare insertion payload
        $insertData = [
            'request_id' => $requestId,
            'linked_issue_id' => $linkedIssueId,
            'task_source_type' => $sourceType,
            'assigned_by_user_id' => $actorUserId,
            'assigned_lead_user_id' => !empty($data['assigned_lead_user_id']) ? (int) $data['assigned_lead_user_id'] : null,
            'task_category' => $data['task_category'] ?? null,
            'vertical_type' => $data['vertical_type'] ?? null,
            'work_description' => $data['work_description'] ?? null,
            'location_text' => $data['location_text'] ?? null,
            'priority' => $data['priority'] ?? 'MEDIUM',
            'start_date' => $data['start_date'] ?? null,
            'expected_close_date' => $data['expected_close_date'] ?? null,
            'status' => 'OPEN'
        ];

        $this->taskRepo->beginTransaction();

        try {
            $newTaskId = $this->taskRepo->create($insertData);

            // State Machine side effects
            if ($sourceType === 'REQUEST' && $requestId) {
                $this->requestRepo->update([
                    'id' => $requestId,
                    'status' => 'CONVERTED'
                ]);
            }
            
            // Note: Issue-to-task link is stored via tasks.linked_issue_id (already set above).
            // No update to the issues table is needed here — the issues table has no linked_task_id column.

            $this->taskRepo->commit();
        } catch (Throwable $e) {
            $this->taskRepo->rollback();
            throw $e;
        }

        return $this->taskRepo->findById($newTaskId);
    }

    /**
     * List tasks. Scope limited for non-Ops roles.
     */
    public function listTasks(array $filters, int $actorUserId, string $actorRoleKey): array
    {
        if ($actorRoleKey === 'FABRICATION_LEAD') {
            $filters['assigned_lead_user_id'] = $actorUserId;
        }

        return $this->taskRepo->findAll($filters);
    }

    /**
     * Get a specific task.
     */
    public function getTask(int $taskId, int $actorUserId, string $actorRoleKey): ?array
    {
        $task = $this->taskRepo->findById($taskId);

        if ($task && $actorRoleKey === 'FABRICATION_LEAD') {
            if ($task['assigned_lead_user_id'] != $actorUserId) {
                return null;
            }
        }

        return $task;
    }

    /**
     * Update an existing task's core metadata. Only Ops can update directly.
     */
    public function updateTask(array $data, string $actorRoleKey): array
    {
        if ($actorRoleKey !== 'OPS_MANAGER') {
            throw new DomainException("Only Ops can update task metadata directly.");
        }

        $taskId = (int) $data['task_id'];
        $task = $this->taskRepo->findById($taskId);
        if (!$task) {
            throw new InvalidArgumentException("Task not found.");
        }

        // Remap to match repository update data constraints
        $data['id'] = $taskId;
        unset($data['task_id']);

        $this->taskRepo->update($data);

        return $this->taskRepo->findById($taskId);
    }

    /**
     * Archive a task.
     */
    public function archiveTask(int $taskId, string $actorRoleKey): array
    {
        if ($actorRoleKey !== 'OPS_MANAGER') {
            throw new DomainException("Only Ops can archive a task.");
        }

        $task = $this->taskRepo->findById($taskId);
        if (!$task) {
            throw new InvalidArgumentException("Task not found.");
        }

        $this->taskRepo->update([
            'id' => $taskId,
            'is_archived' => 1,
            'archived_at' => date('Y-m-d H:i:s')
        ]);

        return $this->taskRepo->findById($taskId);
    }

    /**
     * List task progress explicitly mapped for commercial contexts. 
     * Limits scopes based on user profile.
     */
    public function listTaskProgress(array $filters, int $actorUserId, string $actorRoleKey): array
    {
        $commercialRoles = ['SALES_TEAM', 'CLIENT_SERVICING', 'MEDIA_PLANNING'];
        
        if (!in_array($actorRoleKey, $commercialRoles, true) && $actorRoleKey !== 'OPS_MANAGER') {
            throw new DomainException("Role not authorized to view task progress aggregates.");
        }

        // Additional commercial scopes can be enforced here (e.g. only seeing specific campaigns)
        return $this->taskRepo->findAllProgress($filters);
    }

    /**
     * Get specific task progress logic.
     */
    public function getTaskProgress(int $taskId, int $actorUserId, string $actorRoleKey): ?array
    {
        $commercialRoles = ['SALES_TEAM', 'CLIENT_SERVICING', 'MEDIA_PLANNING'];
        
        if (!in_array($actorRoleKey, $commercialRoles, true) && $actorRoleKey !== 'OPS_MANAGER') {
            return null;
        }

        return $this->taskRepo->findProgressById($taskId);
    }

    /**
     * Update progress natively, scoped strictly for Fabrication Leads.
     */
    public function updateTaskProgress(array $data, int $actorUserId, string $actorRoleKey): array
    {
        if ($actorRoleKey !== 'FABRICATION_LEAD' && $actorRoleKey !== 'OPS_MANAGER') {
            throw new DomainException("Role not authorized to update task progress.");
        }

        $taskId = (int) $data['task_id'];
        $task = $this->taskRepo->findById($taskId);
        
        if (!$task) {
            throw new InvalidArgumentException("Task not found.");
        }

        if ($actorRoleKey === 'FABRICATION_LEAD' && $task['assigned_lead_user_id'] != $actorUserId) {
            throw new DomainException("You can only update tasks assigned to you.");
        }

        $updatePayload = [
            'id' => $taskId,
            'progress_percent' => $data['progress_percent'] ?? $task['progress_percent'],
            'remark_1' => $data['remark_1'] ?? $task['remark_1'],
            'remark_2' => $data['remark_2'] ?? $task['remark_2'],
            'completion_note' => $data['completion_note'] ?? $task['completion_note'],
        ];

        // Ensure percent remains bounded
        if ($updatePayload['progress_percent'] < 0) $updatePayload['progress_percent'] = 0;
        if ($updatePayload['progress_percent'] > 100) $updatePayload['progress_percent'] = 100;

        $this->taskRepo->update($updatePayload);

        return $this->taskRepo->findById($taskId);
    }

    /**
     * Mark work as done enforcing completion payload blocks. Requires specific AFTER_WORK proofs natively mapped.
     */
    public function markWorkDone(array $data, int $actorUserId, string $actorRoleKey): array
    {
        if ($actorRoleKey !== 'FABRICATION_LEAD' && $actorRoleKey !== 'OPS_MANAGER') {
            throw new DomainException("Role not authorized to finalize work completion.");
        }

        $taskId = (int) $data['task_id'];
        $task = $this->taskRepo->findById($taskId);
        
        if (!$task) {
            throw new InvalidArgumentException("Task not found.");
        }

        if ($actorRoleKey === 'FABRICATION_LEAD' && $task['assigned_lead_user_id'] != $actorUserId) {
            throw new DomainException("You can only finalize tasks assigned to you.");
        }

        // Must validate AFTER_WORK uploads exist before allowing work-done to fire natively
        $uploads = $this->uploadRepo->findAll([
            'parent_type' => 'TASK',
            'parent_id' => $taskId,
            'photo_label' => 'AFTER_WORK'
        ]);

        if (count($uploads) === 0) {
            throw new DomainException("Marking work as done requires at least one AFTER_WORK proof uploaded to this task.");
        }

        $updatePayload = [
            'id' => $taskId,
            'progress_percent' => $data['progress_percent'] ?? 100,
            'completion_note' => $data['completion_note'] ?? $task['completion_note'],
        ];

        // Ensure percent remains bounded safely
        if ($updatePayload['progress_percent'] < 0) $updatePayload['progress_percent'] = 0;
        if ($updatePayload['progress_percent'] > 100) $updatePayload['progress_percent'] = 100;

        $this->taskRepo->update($updatePayload);

        return $this->taskRepo->findById($taskId);
    }
}
