<?php

require_once __DIR__ . '/BaseRepository.php';

/**
 * BoardMonitoringRepository
 *
 * SQL-only data access for board_monitoring_reports and related queries.
 */
class BoardMonitoringRepository extends BaseRepository
{
    /**
     * Get assigned belts for a user with today's report status.
     */
    public function getMyBelts(int $userId, string $today): array
    {
        return $this->fetchAll(
            "SELECT gb.id AS belt_id, gb.belt_code, gb.common_name, gb.board_count,
                    bmr.status AS today_status, bmr.off_count AS today_off_count,
                    bmr.id AS today_report_id
             FROM belt_user_assignments bua
             INNER JOIN green_belts gb ON gb.id = bua.belt_id
             LEFT JOIN board_monitoring_reports bmr
                ON bmr.belt_id = gb.id AND bmr.user_id = ? AND bmr.report_date = ?
             WHERE bua.user_id = ? AND bua.assignment_type = 'BOARD_MONITOR'
               AND bua.start_date <= ?
               AND (bua.end_date IS NULL OR bua.end_date >= ?)
             ORDER BY gb.belt_code ASC",
            [$userId, $today, $userId, $today, $today]
        );
    }

    /**
     * Check if a report already exists for belt+date+user.
     */
    public function findTodayReport(int $beltId, int $userId, string $date): ?array
    {
        return $this->fetchOne(
            "SELECT * FROM board_monitoring_reports
             WHERE belt_id = ? AND user_id = ? AND report_date = ?",
            [$beltId, $userId, $date]
        );
    }

    /**
     * Create a new board monitoring report. Returns the new ID.
     */
    public function createReport(array $data): int
    {
        $this->execute(
            "INSERT INTO board_monitoring_reports
                (belt_id, user_id, report_date, status, off_count, total_boards,
                 gps_latitude, gps_longitude, notes)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [
                $data['belt_id'],
                $data['user_id'],
                $data['report_date'],
                $data['status'],
                $data['off_count'],
                $data['total_boards'],
                $data['gps_latitude'],
                $data['gps_longitude'],
                $data['notes'],
            ]
        );

        return (int) $this->lastInsertId();
    }

    /**
     * Update issue_id on a report.
     */
    public function setIssueId(int $reportId, int $issueId): bool
    {
        return $this->execute(
            "UPDATE board_monitoring_reports SET issue_id = ? WHERE id = ?",
            [$issueId, $reportId]
        );
    }

    /**
     * Find a report by ID with belt info.
     */
    public function findById(int $id): ?array
    {
        return $this->fetchOne(
            "SELECT bmr.*, gb.belt_code, gb.common_name AS belt_name,
                    u.full_name AS user_name
             FROM board_monitoring_reports bmr
             INNER JOIN green_belts gb ON gb.id = bmr.belt_id
             INNER JOIN users u ON u.id = bmr.user_id
             WHERE bmr.id = ?",
            [$id]
        );
    }

    /**
     * Paginated history for a user with optional filters.
     */
    public function getHistory(int $userId, array $filters, int $page, int $limit): array
    {
        $where = ['bmr.user_id = ?'];
        $params = [$userId];

        if (!empty($filters['belt_id'])) {
            $where[] = 'bmr.belt_id = ?';
            $params[] = (int) $filters['belt_id'];
        }
        if (!empty($filters['date_from'])) {
            $where[] = 'bmr.report_date >= ?';
            $params[] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $where[] = 'bmr.report_date <= ?';
            $params[] = $filters['date_to'];
        }
        if (!empty($filters['status'])) {
            if ($filters['status'] === 'ISSUES') {
                $where[] = "bmr.status IN ('ALL_OFF', 'PARTIAL_OFF')";
            } else {
                $where[] = 'bmr.status = ?';
                $params[] = $filters['status'];
            }
        }

        $whereClause = 'WHERE ' . implode(' AND ', $where);
        $offset = ($page - 1) * $limit;

        $countRow = $this->fetchOne(
            "SELECT COUNT(*) AS total FROM board_monitoring_reports bmr {$whereClause}",
            $params
        );

        $params[] = $limit;
        $params[] = $offset;

        $items = $this->fetchAll(
            "SELECT bmr.*, gb.belt_code, gb.common_name AS belt_name,
                    i.status AS issue_status,
                    (SELECT COUNT(*) FROM uploads u
                     WHERE u.parent_type = 'BOARD_MONITORING' AND u.parent_id = bmr.id
                       AND u.is_deleted = 0 AND (u.work_type IS NULL OR u.work_type = '')) AS photo_count
             FROM board_monitoring_reports bmr
             INNER JOIN green_belts gb ON gb.id = bmr.belt_id
             LEFT JOIN issues i ON i.id = bmr.issue_id
             {$whereClause}
             ORDER BY bmr.report_date DESC, bmr.created_at DESC
             LIMIT ? OFFSET ?",
            $params
        );

        return [
            'items' => $items,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => (int) ($countRow['total'] ?? 0),
            ],
        ];
    }
}
