<?php

require_once __DIR__ . '/BaseRepository.php';

/**
 * BeltUserAssignmentRepository
 *
 * SQL-only data access for the belt_user_assignments table.
 * Used for role-agnostic belt assignments (BOARD_MONITOR, ELECTRICIAN, etc.).
 */
class BeltUserAssignmentRepository extends BaseRepository
{
    /**
     * Find assignment by ID.
     */
    public function findById(int $id): ?array
    {
        return $this->fetchOne(
            "SELECT bua.*, u.full_name AS user_name, u.email AS username,
                    gb.belt_code, gb.common_name AS belt_name
             FROM belt_user_assignments bua
             INNER JOIN users u ON u.id = bua.user_id
             INNER JOIN green_belts gb ON gb.id = bua.belt_id
             WHERE bua.id = ?",
            [$id]
        );
    }

    /**
     * List assignments with optional filters.
     * Filters: assignment_type, belt_id, user_id, active_only (bool).
     */
    public function findAll(array $filters = []): array
    {
        $where = [];
        $params = [];

        if (!empty($filters['assignment_type'])) {
            $where[] = 'bua.assignment_type = ?';
            $params[] = $filters['assignment_type'];
        }

        if (!empty($filters['belt_id'])) {
            $where[] = 'bua.belt_id = ?';
            $params[] = (int) $filters['belt_id'];
        }

        if (!empty($filters['user_id'])) {
            $where[] = 'bua.user_id = ?';
            $params[] = (int) $filters['user_id'];
        }

        if (!empty($filters['active_only'])) {
            $where[] = '(bua.end_date IS NULL OR bua.end_date >= CURDATE())';
        }

        $whereClause = count($where) > 0 ? 'WHERE ' . implode(' AND ', $where) : '';

        return $this->fetchAll(
            "SELECT bua.*, u.full_name AS user_name, u.email AS username,
                    gb.belt_code, gb.common_name AS belt_name
             FROM belt_user_assignments bua
             INNER JOIN users u ON u.id = bua.user_id
             INNER JOIN green_belts gb ON gb.id = bua.belt_id
             {$whereClause}
             ORDER BY bua.start_date DESC",
            $params
        );
    }

    /**
     * Check if an active assignment already exists for user + belt + type.
     */
    public function findActiveOverlap(int $beltId, int $userId, string $assignmentType): ?array
    {
        return $this->fetchOne(
            "SELECT * FROM belt_user_assignments
             WHERE belt_id = ? AND user_id = ? AND assignment_type = ?
               AND (end_date IS NULL OR end_date >= CURDATE())
             LIMIT 1",
            [$beltId, $userId, $assignmentType]
        );
    }

    /**
     * Create a new assignment. Returns the new ID.
     */
    public function create(array $data): int
    {
        $this->execute(
            "INSERT INTO belt_user_assignments (belt_id, user_id, assignment_type, start_date)
             VALUES (?, ?, ?, ?)",
            [
                $data['belt_id'],
                $data['user_id'],
                $data['assignment_type'],
                $data['start_date'],
            ]
        );

        return (int) $this->lastInsertId();
    }

    /**
     * Close an assignment by setting end_date.
     */
    public function close(int $id, string $endDate): bool
    {
        return $this->execute(
            "UPDATE belt_user_assignments SET end_date = ? WHERE id = ? AND end_date IS NULL",
            [$endDate, $id]
        );
    }

    /**
     * Get active belt IDs for a user with a specific assignment type.
     */
    public function getActiveBeltIdsForUser(int $userId, string $assignmentType): array
    {
        $rows = $this->fetchAll(
            "SELECT bua.belt_id
             FROM belt_user_assignments bua
             WHERE bua.user_id = ? AND bua.assignment_type = ?
               AND bua.start_date <= CURDATE()
               AND (bua.end_date IS NULL OR bua.end_date >= CURDATE())",
            [$userId, $assignmentType]
        );

        return array_column($rows, 'belt_id');
    }
}
