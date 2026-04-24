<?php

require_once __DIR__ . '/BaseRepository.php';

class WorkerRepository extends BaseRepository
{
    /**
     * Find a worker by ID.
     */
    public function findById(int $id): ?array
    {
        return $this->fetchOne(
            "SELECT * FROM fabrication_workers WHERE id = ?",
            [$id]
        );
    }

    /**
     * Create a new fabrication worker.
     */
    public function create(array $data): int
    {
        $this->execute(
            "INSERT INTO fabrication_workers (
                worker_name,
                skill_tag,
                phone,
                is_active
            ) VALUES (?, ?, ?, ?)",
            [
                $data['worker_name'],
                $data['skill_tag'] ?? null,
                $data['phone'] ?? null,
                $data['is_active'] ?? 1
            ]
        );

        return (int) $this->lastInsertId();
    }

    /**
     * List all workers with optional filters.
     */
    public function findAll(array $filters = []): array
    {
        $where = [];
        $params = [];

        if (isset($filters['is_active'])) {
            $where[] = 'is_active = ?';
            $params[] = (int) $filters['is_active'];
        }
        
        if (!empty($filters['skill_tag'])) {
            $where[] = 'skill_tag = ?';
            $params[] = $filters['skill_tag'];
        }

        $whereClause = count($where) > 0 ? 'WHERE ' . implode(' AND ', $where) : '';

        return $this->fetchAll(
            "SELECT * FROM fabrication_workers
             {$whereClause}
             ORDER BY worker_name ASC",
            $params
        );
    }

    /**
     * Update an existing worker.
     */
    public function update(array $data): bool
    {
        $fields = [];
        $params = [];
        
        $allowed = ['worker_name', 'skill_tag', 'phone', 'is_active'];
        
        foreach ($allowed as $field) {
            if (array_key_exists($field, $data)) {
                $fields[] = "{$field} = ?";
                $params[] = $data[$field];
            }
        }
        
        if (empty($fields)) {
            return false;
        }

        $fields[] = 'updated_at = CURRENT_TIMESTAMP';
        $params[] = $data['id'];

        $setClause = implode(', ', $fields);

        return $this->execute(
            "UPDATE fabrication_workers SET {$setClause} WHERE id = ?",
            $params
        );
    }
    /**
     * Get availability states for all workers on a given date.
     */
    public function getAvailabilityStats(string $date, ?string $skillTag = null): array
    {
        $params = [
            $date, // For :report_today1
            $date, // For :report_today2
            $date  // For :report_today3
        ];
        
        $where = [];
        if ($skillTag) {
            $where[] = "fw.skill_tag = ?";
            $params[] = $skillTag;
        }

        $whereClause = empty($where) ? '' : 'WHERE ' . implode(' AND ', $where);

        $sql = "
            SELECT 
                fw.id AS worker_id,
                fw.worker_name,
                fw.skill_tag,
                fw.is_active,
                wde.attendance_status,
                (
                    SELECT COUNT(*) 
                    FROM task_worker_assignments twa
                    JOIN tasks t ON t.id = twa.task_id
                    WHERE twa.worker_id = fw.id
                      AND twa.assigned_date <= ?
                      AND (twa.release_date IS NULL OR twa.release_date >= ?)
                      AND t.status IN ('OPEN', 'RUNNING')
                ) AS active_assignments_count
            FROM fabrication_workers fw
            LEFT JOIN worker_daily_entries wde 
              ON wde.worker_id = fw.id AND wde.entry_date = ?
            {$whereClause}
            ORDER BY fw.worker_name ASC
        ";

        return $this->fetchAll($sql, $params);
    }
}
