<?php

/**
 * MaintenanceCycleRepository
 *
 * Purpose:
 * SQL-only data access for maintenance cycle rows.
 */

require_once __DIR__ . '/BaseRepository.php';

class MaintenanceCycleRepository extends BaseRepository
{
    public function findById(int $cycleId): ?array
    {
        return $this->fetchOne(
            "SELECT mc.*,
                    gb.belt_code,
                    gb.common_name AS belt_name,
                    gb.maintenance_mode,
                    gb.permission_status,
                    gb.is_hidden,
                    starter.full_name AS started_by_user_name,
                    closer.full_name AS closed_by_user_name
             FROM maintenance_cycles mc
             INNER JOIN green_belts gb ON gb.id = mc.belt_id
             INNER JOIN users starter ON starter.id = mc.started_by_user_id
             LEFT JOIN users closer ON closer.id = mc.closed_by_user_id
             WHERE mc.id = ?",
            [$cycleId]
        );
    }

    public function findActiveByBeltId(int $beltId): ?array
    {
        return $this->fetchOne(
            "SELECT *
             FROM maintenance_cycles
             WHERE belt_id = ? AND end_date IS NULL
             ORDER BY start_date DESC, id DESC
             LIMIT 1",
            [$beltId]
        );
    }

    public function findAll(array $filters = []): array
    {
        $where = [];
        $params = [];

        if (!empty($filters['belt_id'])) {
            $where[] = 'mc.belt_id = ?';
            $params[] = (int) $filters['belt_id'];
        }

        if (!empty($filters['status'])) {
            if ($filters['status'] === 'ACTIVE') {
                $where[] = 'mc.end_date IS NULL';
            } elseif ($filters['status'] === 'CLOSED') {
                $where[] = 'mc.end_date IS NOT NULL';
            }
        }

        if (!empty($filters['maintenance_mode'])) {
            $where[] = 'gb.maintenance_mode = ?';
            $params[] = $filters['maintenance_mode'];
        }

        $whereClause = count($where) > 0 ? 'WHERE ' . implode(' AND ', $where) : '';

        return $this->fetchAll(
            "SELECT mc.*,
                    gb.belt_code,
                    gb.common_name AS belt_name,
                    gb.maintenance_mode,
                    gb.permission_status,
                    gb.is_hidden,
                    starter.full_name AS started_by_user_name,
                    closer.full_name AS closed_by_user_name
             FROM maintenance_cycles mc
             INNER JOIN green_belts gb ON gb.id = mc.belt_id
             INNER JOIN users starter ON starter.id = mc.started_by_user_id
             LEFT JOIN users closer ON closer.id = mc.closed_by_user_id
             {$whereClause}
             ORDER BY mc.start_date DESC, mc.id DESC",
            $params
        );
    }

    public function create(int $beltId, int $startedByUserId, string $startDate): int
    {
        $this->execute(
            "INSERT INTO maintenance_cycles
            (belt_id, started_by_user_id, start_date)
            VALUES (?, ?, ?)",
            [$beltId, $startedByUserId, $startDate]
        );

        return (int) $this->lastInsertId();
    }

    public function close(int $cycleId, string $endDate, string $closeReason, int $closedByUserId): bool
    {
        return $this->execute(
            "UPDATE maintenance_cycles
             SET end_date = ?,
                 close_reason = ?,
                 closed_by_user_id = ?,
                 updated_at = NOW()
             WHERE id = ?",
            [$endDate, $closeReason, $closedByUserId, $cycleId]
        );
    }
}
