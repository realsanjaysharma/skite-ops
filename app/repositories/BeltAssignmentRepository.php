<?php

/**
 * BeltAssignmentRepository
 *
 * Purpose:
 * SQL-only data access for belt assignment tables:
 * - belt_supervisor_assignments
 * - belt_authority_assignments
 * - belt_outsourced_assignments
 *
 * Architecture Rule:
 * Controller -> Service -> Repository -> Database
 *
 * Design decision:
 * All 3 assignment tables share identical structure (belt_id, user_id, start_date, end_date).
 * This repository uses parameterized table/column names to avoid repeating the same SQL 3 times.
 * The SERVICE layer is responsible for mapping assignment type to the correct table/column.
 */

require_once __DIR__ . '/BaseRepository.php';

class BeltAssignmentRepository extends BaseRepository
{
    /**
     * Valid assignment table configurations.
     * Maps assignment type to table name and user column.
     */
    private const TABLE_MAP = [
        'supervisor' => [
            'table' => 'belt_supervisor_assignments',
            'user_column' => 'supervisor_user_id',
        ],
        'authority' => [
            'table' => 'belt_authority_assignments',
            'user_column' => 'authority_user_id',
        ],
        'outsourced' => [
            'table' => 'belt_outsourced_assignments',
            'user_column' => 'outsourced_user_id',
        ],
    ];

    /**
     * Get table config for a given assignment type.
     * Throws if the type is invalid.
     */
    public function getTableConfig(string $type): array
    {
        if (!isset(self::TABLE_MAP[$type])) {
            throw new InvalidArgumentException("Invalid assignment type: {$type}");
        }

        return self::TABLE_MAP[$type];
    }

    /**
     * Find a single assignment by ID.
     */
    public function findById(string $type, int $id): ?array
    {
        $config = $this->getTableConfig($type);

        return $this->fetchOne(
            "SELECT * FROM {$config['table']} WHERE id = ?",
            [$id]
        );
    }

    /**
     * Find all assignments for a belt (ordered by most recent first).
     */
    public function findByBeltId(string $type, int $beltId): array
    {
        $config = $this->getTableConfig($type);

        return $this->fetchAll(
            "SELECT a.*, u.full_name AS assigned_user_name
             FROM {$config['table']} a
             INNER JOIN users u ON u.id = a.{$config['user_column']}
             WHERE a.belt_id = ?
             ORDER BY a.start_date DESC",
            [$beltId]
        );
    }

    /**
     * Find all assignments for a specific user.
     */
    public function findByUserId(string $type, int $userId): array
    {
        $config = $this->getTableConfig($type);

        return $this->fetchAll(
            "SELECT a.*, gb.belt_code, gb.common_name
             FROM {$config['table']} a
             INNER JOIN green_belts gb ON gb.id = a.belt_id
             WHERE a.{$config['user_column']} = ?
             ORDER BY a.start_date DESC",
            [$userId]
        );
    }

    /**
     * Find active (open-ended) assignments for a belt.
     */
    public function findActiveByBeltId(string $type, int $beltId): array
    {
        $config = $this->getTableConfig($type);

        return $this->fetchAll(
            "SELECT a.*, u.full_name AS assigned_user_name
             FROM {$config['table']} a
             INNER JOIN users u ON u.id = a.{$config['user_column']}
             WHERE a.belt_id = ? AND a.end_date IS NULL
             ORDER BY a.start_date DESC",
            [$beltId]
        );
    }

    /**
     * List assignments with optional filters.
     */
    public function findAll(string $type, array $filters): array
    {
        $config = $this->getTableConfig($type);
        $where = [];
        $params = [];

        if (!empty($filters['belt_id'])) {
            $where[] = 'a.belt_id = ?';
            $params[] = (int) $filters['belt_id'];
        }

        if (!empty($filters['user_id'])) {
            $where[] = "a.{$config['user_column']} = ?";
            $params[] = (int) $filters['user_id'];
        }

        if (isset($filters['active_only']) && $filters['active_only']) {
            $where[] = 'a.end_date IS NULL';
        }

        $whereClause = count($where) > 0 ? 'WHERE ' . implode(' AND ', $where) : '';

        return $this->fetchAll(
            "SELECT a.*, u.full_name AS assigned_user_name,
                    gb.belt_code, gb.common_name
             FROM {$config['table']} a
             INNER JOIN users u ON u.id = a.{$config['user_column']}
             INNER JOIN green_belts gb ON gb.id = a.belt_id
             {$whereClause}
             ORDER BY a.start_date DESC",
            $params
        );
    }

    /**
     * Create a new assignment. Returns the new ID.
     */
    public function create(string $type, array $data): int
    {
        $config = $this->getTableConfig($type);

        $this->execute(
            "INSERT INTO {$config['table']}
            (belt_id, {$config['user_column']}, start_date, end_date)
            VALUES (?, ?, ?, ?)",
            [
                $data['belt_id'],
                $data['user_id'],
                $data['start_date'],
                $data['end_date'] ?? null,
            ]
        );

        return (int) $this->lastInsertId();
    }

    /**
     * Close an assignment by setting end_date.
     */
    public function close(string $type, int $id, string $endDate): bool
    {
        $config = $this->getTableConfig($type);

        return $this->execute(
            "UPDATE {$config['table']} SET end_date = ? WHERE id = ?",
            [$endDate, $id]
        );
    }
}
