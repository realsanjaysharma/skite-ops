<?php

/**
 * BeltRepository
 *
 * Purpose:
 * SQL-only data access for the green_belts table.
 *
 * Architecture Rule:
 * Controller -> Service -> Repository -> Database
 *
 * IMPORTANT RULES:
 * - ONLY database access (SQL)
 * - NO business logic here
 * - NO validation
 */

require_once __DIR__ . '/BaseRepository.php';

class BeltRepository extends BaseRepository
{
    /**
     * Find a single belt by ID.
     */
    public function findById(int $id): ?array
    {
        return $this->fetchOne(
            "SELECT * FROM green_belts WHERE id = ?",
            [$id]
        );
    }

    /**
     * Fetch paginated list of belts with optional filters.
     *
     * Supported filters:
     * - zone
     * - permission_status
     * - maintenance_mode
     * - hidden (0 or 1)
     * - supervisor_user_id (requires JOIN to belt_supervisor_assignments)
     */
    public function findAll(array $filters, int $page, int $limit): array
    {
        $where = [];
        $params = [];
        $join = '';

        if (!empty($filters['zone'])) {
            $where[] = 'gb.zone = ?';
            $params[] = $filters['zone'];
        }

        if (!empty($filters['permission_status'])) {
            $where[] = 'gb.permission_status = ?';
            $params[] = $filters['permission_status'];
        }

        if (!empty($filters['maintenance_mode'])) {
            $where[] = 'gb.maintenance_mode = ?';
            $params[] = $filters['maintenance_mode'];
        }

        if (isset($filters['hidden']) && $filters['hidden'] !== '' && $filters['hidden'] !== null) {
            $where[] = 'gb.is_hidden = ?';
            $params[] = (int) $filters['hidden'];
        }

        if (!empty($filters['supervisor_user_id'])) {
            $join = 'INNER JOIN belt_supervisor_assignments bsa
                     ON bsa.belt_id = gb.id
                     AND bsa.end_date IS NULL
                     AND bsa.supervisor_user_id = ?';
            $params[] = (int) $filters['supervisor_user_id'];
        }

        $whereClause = count($where) > 0 ? 'WHERE ' . implode(' AND ', $where) : '';
        $offset = ($page - 1) * $limit;

        $sql = "SELECT gb.id, gb.belt_code, gb.common_name, gb.authority_name,
                       gb.zone, gb.permission_status, gb.maintenance_mode,
                       gb.watering_frequency, gb.is_hidden,
                       (SELECT COUNT(*) FROM issues i WHERE i.belt_id = gb.id AND i.status != 'CLOSED') as open_issue_count,
                       (SELECT mc.id FROM maintenance_cycles mc WHERE mc.belt_id = gb.id AND mc.end_date IS NULL ORDER BY mc.start_date DESC LIMIT 1) as active_cycle_id
                FROM green_belts gb
                {$join}
                {$whereClause}
                ORDER BY gb.belt_code ASC
                LIMIT ? OFFSET ?";

        $params[] = $limit;
        $params[] = $offset;

        return $this->fetchAll($sql, $params);
    }

    /**
     * Count total belts matching filters (for pagination).
     */
    public function countAll(array $filters): int
    {
        $where = [];
        $params = [];
        $join = '';

        if (!empty($filters['zone'])) {
            $where[] = 'gb.zone = ?';
            $params[] = $filters['zone'];
        }

        if (!empty($filters['permission_status'])) {
            $where[] = 'gb.permission_status = ?';
            $params[] = $filters['permission_status'];
        }

        if (!empty($filters['maintenance_mode'])) {
            $where[] = 'gb.maintenance_mode = ?';
            $params[] = $filters['maintenance_mode'];
        }

        if (isset($filters['hidden']) && $filters['hidden'] !== '' && $filters['hidden'] !== null) {
            $where[] = 'gb.is_hidden = ?';
            $params[] = (int) $filters['hidden'];
        }

        if (!empty($filters['supervisor_user_id'])) {
            $join = 'INNER JOIN belt_supervisor_assignments bsa
                     ON bsa.belt_id = gb.id
                     AND bsa.end_date IS NULL
                     AND bsa.supervisor_user_id = ?';
            $params[] = (int) $filters['supervisor_user_id'];
        }

        $whereClause = count($where) > 0 ? 'WHERE ' . implode(' AND ', $where) : '';

        $sql = "SELECT COUNT(DISTINCT gb.id) as total
                FROM green_belts gb
                {$join}
                {$whereClause}";

        $row = $this->fetchOne($sql, $params);

        return (int) ($row['total'] ?? 0);
    }

    /**
     * Insert a new green belt. Returns the new ID.
     */
    public function create(array $data): int
    {
        $this->execute(
            "INSERT INTO green_belts
            (belt_code, common_name, authority_name, zone, location_text,
             latitude, longitude, permission_start_date, permission_end_date,
             permission_status, maintenance_mode, watering_frequency, is_hidden)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [
                $data['belt_code'],
                $data['common_name'],
                $data['authority_name'],
                $data['zone'] ?? null,
                $data['location_text'] ?? null,
                $data['latitude'] ?? null,
                $data['longitude'] ?? null,
                $data['permission_start_date'] ?? null,
                $data['permission_end_date'] ?? null,
                $data['permission_status'],
                $data['maintenance_mode'],
                $data['watering_frequency'],
                $data['is_hidden'] ?? 0,
            ]
        );

        return (int) $this->lastInsertId();
    }

    /**
     * Update an existing belt's fields.
     */
    public function update(int $id, array $data): bool
    {
        return $this->execute(
            "UPDATE green_belts SET
                common_name = ?,
                authority_name = ?,
                zone = ?,
                location_text = ?,
                latitude = ?,
                longitude = ?,
                permission_start_date = ?,
                permission_end_date = ?,
                permission_status = ?,
                maintenance_mode = ?,
                watering_frequency = ?,
                is_hidden = ?
            WHERE id = ?",
            [
                $data['common_name'],
                $data['authority_name'],
                $data['zone'] ?? null,
                $data['location_text'] ?? null,
                $data['latitude'] ?? null,
                $data['longitude'] ?? null,
                $data['permission_start_date'] ?? null,
                $data['permission_end_date'] ?? null,
                $data['permission_status'],
                $data['maintenance_mode'],
                $data['watering_frequency'],
                $data['is_hidden'] ?? 0,
                $id,
            ]
        );
    }

    /**
     * Check if a belt_code already exists (for uniqueness validation).
     * Optionally exclude a specific belt ID (for update scenarios).
     */
    public function beltCodeExists(string $beltCode, ?int $excludeId = null): bool
    {
        if ($excludeId !== null) {
            $row = $this->fetchOne(
                "SELECT id FROM green_belts WHERE belt_code = ? AND id != ?",
                [$beltCode, $excludeId]
            );
        } else {
            $row = $this->fetchOne(
                "SELECT id FROM green_belts WHERE belt_code = ?",
                [$beltCode]
            );
        }

        return $row !== null;
    }

    /**
     * Fetch recent maintenance cycle history for a belt.
     */
    public function findCycleHistoryByBeltId(int $beltId, int $limit = 10): array
    {
        return $this->fetchAll(
            "SELECT mc.*,
                    starter.full_name AS started_by_user_name,
                    closer.full_name AS closed_by_user_name
             FROM maintenance_cycles mc
             INNER JOIN users starter ON starter.id = mc.started_by_user_id
             LEFT JOIN users closer ON closer.id = mc.closed_by_user_id
             WHERE mc.belt_id = ?
             ORDER BY mc.start_date DESC, mc.id DESC
             LIMIT {$limit}",
            [$beltId]
        );
    }

    /**
     * Build a compact cycle summary for the belt detail page.
     */
    public function findRecentCycleSummaryByBeltId(int $beltId): array
    {
        $activeCycle = $this->fetchOne(
            "SELECT mc.*,
                    starter.full_name AS started_by_user_name
             FROM maintenance_cycles mc
             INNER JOIN users starter ON starter.id = mc.started_by_user_id
             WHERE mc.belt_id = ? AND mc.end_date IS NULL
             ORDER BY mc.start_date DESC, mc.id DESC
             LIMIT 1",
            [$beltId]
        );

        $latestClosedCycle = $this->fetchOne(
            "SELECT mc.*,
                    starter.full_name AS started_by_user_name,
                    closer.full_name AS closed_by_user_name
             FROM maintenance_cycles mc
             INNER JOIN users starter ON starter.id = mc.started_by_user_id
             LEFT JOIN users closer ON closer.id = mc.closed_by_user_id
             WHERE mc.belt_id = ? AND mc.end_date IS NOT NULL
             ORDER BY mc.end_date DESC, mc.id DESC
             LIMIT 1",
            [$beltId]
        );

        $counts = $this->fetchOne(
            "SELECT COUNT(*) AS total_cycles,
                    SUM(CASE WHEN end_date IS NULL THEN 1 ELSE 0 END) AS active_cycle_count
             FROM maintenance_cycles
             WHERE belt_id = ?",
            [$beltId]
        );

        return [
            'active_cycle' => $activeCycle,
            'latest_closed_cycle' => $latestClosedCycle,
            'total_cycles' => (int) ($counts['total_cycles'] ?? 0),
            'active_cycle_count' => (int) ($counts['active_cycle_count'] ?? 0),
        ];
    }

    /**
     * Fetch recent watering rows for detail history.
     */
    public function findWateringHistoryByBeltId(int $beltId, int $limit = 14): array
    {
        return $this->fetchAll(
            "SELECT wr.*,
                    creator.full_name AS created_by_user_name,
                    overrider.full_name AS override_by_user_name
             FROM watering_records wr
             INNER JOIN users creator ON creator.id = wr.created_by_user_id
             LEFT JOIN users overrider ON overrider.id = wr.override_by_user_id
             WHERE wr.belt_id = ?
             ORDER BY wr.watering_date DESC, wr.id DESC
             LIMIT {$limit}",
            [$beltId]
        );
    }

    /**
     * Build a compact watering summary for the belt detail page.
     */
    public function findRecentWateringSummaryByBeltId(int $beltId): array
    {
        $latestRecord = $this->fetchOne(
            "SELECT wr.*,
                    creator.full_name AS created_by_user_name,
                    overrider.full_name AS override_by_user_name
             FROM watering_records wr
             INNER JOIN users creator ON creator.id = wr.created_by_user_id
             LEFT JOIN users overrider ON overrider.id = wr.override_by_user_id
             WHERE wr.belt_id = ?
             ORDER BY wr.watering_date DESC, wr.id DESC
             LIMIT 1",
            [$beltId]
        );

        $todayRecord = $this->fetchOne(
            "SELECT *
             FROM watering_records
             WHERE belt_id = ? AND watering_date = CURDATE()
             LIMIT 1",
            [$beltId]
        );

        $windowCounts = $this->fetchOne(
            "SELECT COUNT(*) AS recent_record_count,
                    SUM(CASE WHEN status = 'DONE' THEN 1 ELSE 0 END) AS done_count,
                    SUM(CASE WHEN status = 'NOT_REQUIRED' THEN 1 ELSE 0 END) AS not_required_count
             FROM watering_records
             WHERE belt_id = ?
               AND watering_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)",
            [$beltId]
        );

        return [
            'latest_record' => $latestRecord,
            'today_record' => $todayRecord,
            'recent_record_count' => (int) ($windowCounts['recent_record_count'] ?? 0),
            'recent_done_count' => (int) ($windowCounts['done_count'] ?? 0),
            'recent_not_required_count' => (int) ($windowCounts['not_required_count'] ?? 0),
        ];
    }

    /**
     * Fetch recent non-deleted, non-purged uploads for a belt.
     */
    public function findUploadsByBeltId(int $beltId, int $limit = 20): array
    {
        return $this->fetchAll(
            "SELECT u.id,
                    u.parent_type,
                    u.parent_id,
                    u.upload_type,
                    u.work_type,
                    u.photo_label,
                    u.comment_text,
                    u.authority_visibility,
                    u.original_file_name,
                    u.mime_type,
                    u.created_at,
                    creator.full_name AS created_by_user_name
             FROM uploads u
             INNER JOIN users creator ON creator.id = u.created_by_user_id
             WHERE u.parent_type = 'GREEN_BELT'
               AND u.parent_id = ?
               AND u.is_deleted = 0
               AND u.is_purged = 0
             ORDER BY u.created_at DESC, u.id DESC
             LIMIT {$limit}",
            [$beltId]
        );
    }

    /**
     * Fetch recent issues linked to a belt.
     */
    public function findIssuesByBeltId(int $beltId, int $limit = 20): array
    {
        return $this->fetchAll(
            "SELECT i.*,
                    raiser.full_name AS raised_by_user_name,
                    closer.full_name AS closed_by_user_name
             FROM issues i
             INNER JOIN users raiser ON raiser.id = i.raised_by_user_id
             LEFT JOIN users closer ON closer.id = i.closed_by_user_id
             WHERE i.belt_id = ?
             ORDER BY i.created_at DESC, i.id DESC
             LIMIT {$limit}",
            [$beltId]
        );
    }
}
