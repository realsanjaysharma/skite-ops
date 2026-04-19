<?php

require_once __DIR__ . '/../repositories/TaskWorkerRepository.php';
require_once __DIR__ . '/../repositories/TaskRepository.php';

class TaskWorkerService
{
    private TaskWorkerRepository $taskWorkerRepo;
    private TaskRepository $taskRepo;

    public function __construct()
    {
        $this->taskWorkerRepo = new TaskWorkerRepository();
        $this->taskRepo = new TaskRepository();
    }

    /**
     * Assign workers to a task securely guarding ops lead.
     */
    public function assignWorkers(array $data, string $actorRoleKey, int $actorUserId): array
    {
        if (empty($data['task_id'])) {
            throw new InvalidArgumentException("task_id is required");
        }
        if (empty($data['worker_ids']) || !is_array($data['worker_ids'])) {
            throw new InvalidArgumentException("worker_ids array is required");
        }
        if (empty($data['assignment_role'])) {
            throw new InvalidArgumentException("assignment_role is required");
        }

        $validRoles = ['PRIMARY', 'HELPER'];
        if (!in_array($data['assignment_role'], $validRoles, true)) {
            throw new DomainException("Invalid assignment_role enumeration");
        }

        $this->verifyTaskScope((int)$data['task_id'], $actorRoleKey, $actorUserId);

        return $this->taskWorkerRepo->assignWorkers(
            (int)$data['task_id'],
            $data['worker_ids'],
            $data['assignment_role'],
            $actorUserId
        );
    }

    /**
     * Release worker mathematically ending occupancy tracking securely.
     */
    public function releaseWorker(array $data, string $actorRoleKey, int $actorUserId): bool
    {
        if (empty($data['assignment_id'])) {
            throw new InvalidArgumentException("assignment_id is required");
        }
        if (empty($data['release_date'])) {
            throw new InvalidArgumentException("release_date is required");
        }

        $assignment = $this->taskWorkerRepo->getAssignmentById((int)$data['assignment_id']);
        if (!$assignment) {
            throw new DomainException("Assignment not found");
        }

        $this->verifyTaskScope((int)$assignment['task_id'], $actorRoleKey, $actorUserId);

        return $this->taskWorkerRepo->releaseWorker((int)$data['assignment_id'], $data['release_date']);
    }

    /**
     * Encapsulates authorization checking against the live task lead context.
     */
    private function verifyTaskScope(int $taskId, string $actorRoleKey, int $actorUserId): void
    {
        if ($actorRoleKey === 'OPS_MANAGER') {
            return; // Ops has global authority
        }

        if ($actorRoleKey === 'FABRICATION_LEAD') {
            $task = $this->taskRepo->getTaskById($taskId);
            if (!$task) {
                throw new DomainException("Task not found");
            }
            if ($task['assigned_lead_user_id'] != $actorUserId) {
                throw new DomainException("Unauthorized: Only Ops or the explicitly assigned Fabrication Lead can alter worker allocations");
            }
            return;
        }

        throw new DomainException("Unauthorized role for task worker assignments");
    }
}
