<?php

require_once __DIR__ . '/BaseRepository.php';

class WorkerEntryRepository extends BaseRepository
{
    /**
     * Find a worker's entry for a specific date
     */
    public function findByWorkerAndDate(int $workerId, string $entryDate): ?array
    {
        $stmt = $this->db->prepare("
            SELECT * FROM worker_daily_entries 
            WHERE worker_id = :worker_id AND entry_date = :entry_date
        ");
        $stmt->execute([
            'worker_id' => $workerId,
            'entry_date' => $entryDate
        ]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Upsert a daily entry for a worker
     */
    public function saveEntry(array $data): array
    {
        $existing = $this->findByWorkerAndDate($data['worker_id'], $data['entry_date']);

        if ($existing) {
            $stmt = $this->db->prepare("
                UPDATE worker_daily_entries
                SET attendance_status = :attendance_status,
                    activity_type = :activity_type,
                    task_id = :task_id,
                    site_id = :site_id,
                    work_plan = :work_plan,
                    work_update = :work_update,
                    updated_at = NOW()
                WHERE id = :id
            ");
            $params = [
                'id' => $existing['id'],
                'attendance_status' => $data['attendance_status'],
                'activity_type' => $data['activity_type'] ?? null,
                'task_id' => $data['task_id'] ?? null,
                'site_id' => $data['site_id'] ?? null,
                'work_plan' => $data['work_plan'] ?? null,
                'work_update' => $data['work_update'] ?? null
            ];
            $stmt->execute($params);
            
            return $this->findByWorkerAndDate($data['worker_id'], $data['entry_date']);
        } else {
            $stmt = $this->db->prepare("
                INSERT INTO worker_daily_entries (
                    worker_id, entry_date, attendance_status, activity_type, 
                    task_id, site_id, work_plan, work_update, created_by_user_id, created_at, updated_at
                ) VALUES (
                    :worker_id, :entry_date, :attendance_status, :activity_type, 
                    :task_id, :site_id, :work_plan, :work_update, :created_by_user_id, NOW(), NOW()
                )
            ");
            $params = [
                'worker_id' => $data['worker_id'],
                'entry_date' => $data['entry_date'],
                'attendance_status' => $data['attendance_status'],
                'activity_type' => $data['activity_type'] ?? null,
                'task_id' => $data['task_id'] ?? null,
                'site_id' => $data['site_id'] ?? null,
                'work_plan' => $data['work_plan'] ?? null,
                'work_update' => $data['work_update'] ?? null,
                'created_by_user_id' => $data['created_by_user_id']
            ];
            $stmt->execute($params);

            return $this->findByWorkerAndDate($data['worker_id'], $data['entry_date']);
        }
    }

    /**
     * List worker entries with optional filters
     */
    public function listEntries(array $filters): array
    {
        $sql = "
            SELECT 
                wde.*,
                fw.worker_name,
                fw.skill_tag
            FROM worker_daily_entries wde
            JOIN fabrication_workers fw ON fw.id = wde.worker_id
            WHERE 1=1
        ";
        
        $params = [];

        if (!empty($filters['worker_id'])) {
            $sql .= " AND wde.worker_id = :worker_id";
            $params['worker_id'] = $filters['worker_id'];
        }

        if (!empty($filters['entry_date'])) {
            $sql .= " AND wde.entry_date = :entry_date";
            $params['entry_date'] = $filters['entry_date'];
        }

        if (!empty($filters['activity_type'])) {
            $sql .= " AND wde.activity_type = :activity_type";
            $params['activity_type'] = $filters['activity_type'];
        }

        $sql .= " ORDER BY wde.entry_date DESC, fw.worker_name ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
