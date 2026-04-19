<?php

require_once __DIR__ . '/BaseRepository.php';

class AttendanceRepository extends BaseRepository
{
    /**
     * Find an attendance record by ID.
     */
    public function findById(int $id): ?array
    {
        return $this->fetchOne(
            "SELECT a.*, 
                    creator.full_name AS created_by_user_name,
                    overrider.full_name AS override_by_user_name
             FROM supervisor_attendance a
             LEFT JOIN users creator ON creator.id = a.created_by_user_id
             LEFT JOIN users overrider ON overrider.id = a.override_by_user_id
             WHERE a.id = ?",
            [$id]
        );
    }

    /**
     * Find an attendance record by supervisor and date.
     * Enforced by unique constraint (supervisor_user_id, attendance_date).
     */
    public function findBySupervisorAndDate(int $supervisorUserId, string $attendanceDate): ?array
    {
        return $this->fetchOne(
            "SELECT a.*,
                    creator.full_name AS created_by_user_name,
                    overrider.full_name AS override_by_user_name
             FROM supervisor_attendance a
             LEFT JOIN users creator ON creator.id = a.created_by_user_id
             LEFT JOIN users overrider ON overrider.id = a.override_by_user_id
             WHERE a.supervisor_user_id = ? AND a.attendance_date = ?",
            [$supervisorUserId, $attendanceDate]
        );
    }

    /**
     * List attendance records with optional filters.
     */
    public function findAll(array $filters = []): array
    {
        $where = [];
        $params = [];

        if (!empty($filters['date'])) {
            $where[] = 'a.attendance_date = ?';
            $params[] = $filters['date'];
        }

        if (!empty($filters['supervisor_user_id'])) {
            $where[] = 'a.supervisor_user_id = ?';
            $params[] = (int) $filters['supervisor_user_id'];
        }

        $whereClause = count($where) > 0 ? 'WHERE ' . implode(' AND ', $where) : '';

        return $this->fetchAll(
            "SELECT a.*, 
                    sup.full_name AS supervisor_name,
                    creator.full_name AS created_by_user_name,
                    overrider.full_name AS override_by_user_name
             FROM supervisor_attendance a
             INNER JOIN users sup ON sup.id = a.supervisor_user_id
             LEFT JOIN users creator ON creator.id = a.created_by_user_id
             LEFT JOIN users overrider ON overrider.id = a.override_by_user_id
             {$whereClause}
             ORDER BY a.attendance_date DESC, sup.full_name ASC",
            $params
        );
    }

    /**
     * Create a new attendance record.
     */
    public function create(array $data): int
    {
        $this->execute(
            "INSERT INTO supervisor_attendance (
                supervisor_user_id, 
                attendance_date, 
                status, 
                created_by_user_id, 
                override_by_user_id, 
                override_reason
            ) VALUES (?, ?, ?, ?, ?, ?)",
            [
                $data['supervisor_user_id'],
                $data['attendance_date'],
                $data['status'],
                $data['created_by_user_id'],
                $data['override_by_user_id'] ?? null,
                $data['override_reason'] ?? null,
            ]
        );

        return (int) $this->lastInsertId();
    }

    /**
     * Update an existing attendance record.
     */
    public function update(array $data): bool
    {
        return $this->execute(
            "UPDATE supervisor_attendance
             SET status = ?,
                 override_by_user_id = ?,
                 override_reason = ?,
                 updated_at = CURRENT_TIMESTAMP
             WHERE id = ?",
            [
                $data['status'],
                $data['override_by_user_id'] ?? null,
                $data['override_reason'] ?? null,
                $data['id'],
            ]
        );
    }
}
