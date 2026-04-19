<?php

require_once __DIR__ . '/BaseRepository.php';

class TaskWorkerRepository extends BaseRepository
{
    /**
     * Loops over worker_ids and inserts assignments while avoiding duplicate same-day errors.
     */
    public function assignWorkers(int $taskId, array $workerIds, string $assignmentRole, int $assignedByUserId): array
    {
        $assignedDate = date('Y-m-d');
        $createdIds = [];

        foreach ($workerIds as $workerId) {
            // Check if active or duplicate exists strictly (per unique constraint)
            $stmtCheck = $this->db->prepare("
                SELECT id FROM task_worker_assignments
                WHERE task_id = :task_id
                  AND worker_id = :worker_id
                  AND assigned_date = :assigned_date
            ");
            $stmtCheck->execute([
                'task_id' => $taskId,
                'worker_id' => $workerId,
                'assigned_date' => $assignedDate
            ]);

            $existing = $stmtCheck->fetchColumn();
            if ($existing) {
                continue; // Skip silently if already assigned on this date
            }

            // Insert new
            $stmtInsert = $this->db->prepare("
                INSERT INTO task_worker_assignments (
                    task_id, worker_id, assigned_by_user_id, assigned_date, assignment_role, created_at, updated_at
                ) VALUES (
                    :task_id, :worker_id, :assigned_by, :assigned_date, :assignment_role, NOW(), NOW()
                )
            ");
            $stmtInsert->execute([
                'task_id' => $taskId,
                'worker_id' => $workerId,
                'assigned_by' => $assignedByUserId,
                'assigned_date' => $assignedDate,
                'assignment_role' => $assignmentRole
            ]);

            $createdIds[] = $this->db->lastInsertId();
        }

        return $createdIds;
    }

    /**
     * Release a worker from an assignment.
     */
    public function releaseWorker(int $assignmentId, string $releaseDate): bool
    {
        $stmt = $this->db->prepare("
            UPDATE task_worker_assignments
            SET release_date = :release_date, updated_at = NOW()
            WHERE id = :assignment_id AND release_date IS NULL
        ");
        return $stmt->execute([
            'release_date' => $releaseDate,
            'assignment_id' => $assignmentId
        ]);
    }

    /**
     * Get assignment by id
     */
    public function getAssignmentById(int $assignmentId): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM task_worker_assignments WHERE id = :id");
        $stmt->execute(['id' => $assignmentId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }
}
