<?php

require_once __DIR__ . '/BaseRepository.php';

/**
 * AuthorityViewRepository
 *
 * Purpose:
 * Data access for authority-facing upload views.
 * Queries uploads (approved work photos) and belt_authority_assignments.
 *
 * Schema Reference:
 * - belt_authority_assignments: belt_id, authority_user_id, start_date, end_date
 * - uploads: parent_type, parent_id, authority_visibility, etc.
 */
class AuthorityViewRepository extends BaseRepository
{
    /**
     * List approved work uploads visible to authority, with optional scope filtering.
     */
    public function getList(array $filters, int $page, int $limit, ?array $allowedBeltIds): array
    {
        $where = [
            "u.parent_type = 'GREEN_BELT'",
            "u.upload_type = 'WORK'",
            "u.authority_visibility = 'APPROVED'",
            "u.is_deleted = 0",
            "u.is_purged = 0"
        ];
        $params = [];

        if ($allowedBeltIds !== null) {
            if (empty($allowedBeltIds)) {
                return [];
            }
            $placeholders = implode(',', array_fill(0, count($allowedBeltIds), '?'));
            $where[] = "u.parent_id IN ($placeholders)";
            $params = array_merge($params, $allowedBeltIds);
        }

        if (!empty($filters['date'])) {
            $where[] = "DATE(u.created_at) = ?";
            $params[] = $filters['date'];
        }
        if (!empty($filters['belt_id'])) {
            $where[] = "u.parent_id = ?";
            $params[] = (int)$filters['belt_id'];
        }
        if (!empty($filters['supervisor_user_id'])) {
            $where[] = "u.created_by_user_id = ?";
            $params[] = (int)$filters['supervisor_user_id'];
        }
        if (!empty($filters['work_type'])) {
            $where[] = "u.work_type = ?";
            $params[] = $filters['work_type'];
        }

        $whereClause = implode(' AND ', $where);
        $offset = ($page - 1) * $limit;

        $query = "SELECT u.id as upload_id, u.file_path, u.created_at as timestamp,
                         u.work_type, u.photo_label, u.gps_latitude, u.gps_longitude,
                         creator.full_name as supervisor_name
                  FROM uploads u
                  INNER JOIN users creator ON creator.id = u.created_by_user_id
                  WHERE {$whereClause}
                  ORDER BY u.created_at DESC
                  LIMIT {$limit} OFFSET {$offset}";

        return $this->fetchAll($query, $params);
    }

    /**
     * Count approved work uploads matching filters.
     */
    public function countList(array $filters, ?array $allowedBeltIds): int
    {
        $where = [
            "u.parent_type = 'GREEN_BELT'",
            "u.upload_type = 'WORK'",
            "u.authority_visibility = 'APPROVED'",
            "u.is_deleted = 0",
            "u.is_purged = 0"
        ];
        $params = [];

        if ($allowedBeltIds !== null) {
            if (empty($allowedBeltIds)) return 0;
            $placeholders = implode(',', array_fill(0, count($allowedBeltIds), '?'));
            $where[] = "u.parent_id IN ($placeholders)";
            $params = array_merge($params, $allowedBeltIds);
        }

        if (!empty($filters['date'])) {
            $where[] = "DATE(u.created_at) = ?";
            $params[] = $filters['date'];
        }
        if (!empty($filters['belt_id'])) {
            $where[] = "u.parent_id = ?";
            $params[] = (int)$filters['belt_id'];
        }
        if (!empty($filters['supervisor_user_id'])) {
            $where[] = "u.created_by_user_id = ?";
            $params[] = (int)$filters['supervisor_user_id'];
        }
        if (!empty($filters['work_type'])) {
            $where[] = "u.work_type = ?";
            $params[] = $filters['work_type'];
        }

        $whereClause = implode(' AND ', $where);
        $row = $this->fetchOne("SELECT COUNT(*) as total FROM uploads u WHERE {$whereClause}", $params);
        return (int)($row['total'] ?? 0);
    }

    /**
     * Summary statistics for authority dashboard.
     */
    public function getSummaryStats(array $filters, ?array $allowedBeltIds): array
    {
        $where = [
            "u.parent_type = 'GREEN_BELT'",
            "u.upload_type = 'WORK'",
            "u.authority_visibility = 'APPROVED'",
            "u.is_deleted = 0",
            "u.is_purged = 0"
        ];
        $params = [];

        if ($allowedBeltIds !== null) {
            if (empty($allowedBeltIds)) return [
                'total_belts' => 0,
                'total_morning_photos' => 0,
                'total_evening_photos' => 0,
                'total_photos' => 0
            ];
            $placeholders = implode(',', array_fill(0, count($allowedBeltIds), '?'));
            $where[] = "u.parent_id IN ($placeholders)";
            $params = array_merge($params, $allowedBeltIds);
        }

        if (!empty($filters['date'])) {
            $where[] = "DATE(u.created_at) = ?";
            $params[] = $filters['date'];
        }
        if (!empty($filters['belt_id'])) {
            $where[] = "u.parent_id = ?";
            $params[] = (int)$filters['belt_id'];
        }
        if (!empty($filters['supervisor_user_id'])) {
            $where[] = "u.created_by_user_id = ?";
            $params[] = (int)$filters['supervisor_user_id'];
        }
        if (!empty($filters['work_type'])) {
            $where[] = "u.work_type = ?";
            $params[] = $filters['work_type'];
        }

        $whereClause = implode(' AND ', $where);

        $sql = "SELECT
                    COUNT(DISTINCT u.parent_id) as total_belts,
                    SUM(CASE WHEN HOUR(u.created_at) < 12 THEN 1 ELSE 0 END) as total_morning_photos,
                    SUM(CASE WHEN HOUR(u.created_at) >= 12 THEN 1 ELSE 0 END) as total_evening_photos,
                    COUNT(u.id) as total_photos
                FROM uploads u
                WHERE {$whereClause}";

        $row = $this->fetchOne($sql, $params);
        return [
            'total_belts' => (int)($row['total_belts'] ?? 0),
            'total_morning_photos' => (int)($row['total_morning_photos'] ?? 0),
            'total_evening_photos' => (int)($row['total_evening_photos'] ?? 0),
            'total_photos' => (int)($row['total_photos'] ?? 0)
        ];
    }

    /**
     * Get belt IDs assigned to an authority user (active assignments only).
     *
     * Schema: belt_authority_assignments has belt_id and end_date (NOT green_belt_id, NOT released_date).
     */
    public function getAssignedBeltIdsForAuthority(int $userId): array
    {
        return array_column($this->fetchAll(
            "SELECT belt_id FROM belt_authority_assignments
             WHERE authority_user_id = ? AND end_date IS NULL",
            [$userId]
        ), 'belt_id');
    }
}
