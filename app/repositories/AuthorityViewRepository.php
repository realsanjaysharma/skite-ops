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
        if (!empty($filters['date_from'])) {
            $where[] = "DATE(u.created_at) >= ?";
            $params[] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $where[] = "DATE(u.created_at) <= ?";
            $params[] = $filters['date_to'];
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

        $query = "SELECT u.id as upload_id, u.file_path, u.file_size_bytes, u.created_at as timestamp,
                         u.work_type, u.photo_label, u.gps_latitude, u.gps_longitude,
                         u.parent_id as belt_id,
                         b.belt_code, b.common_name as belt_common_name,
                         creator.full_name as supervisor_name
                  FROM uploads u
                  INNER JOIN users creator ON creator.id = u.created_by_user_id
                  INNER JOIN green_belts b ON b.id = u.parent_id
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
        if (!empty($filters['date_from'])) {
            $where[] = "DATE(u.created_at) >= ?";
            $params[] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $where[] = "DATE(u.created_at) <= ?";
            $params[] = $filters['date_to'];
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
        if (!empty($filters['date_from'])) {
            $where[] = "DATE(u.created_at) >= ?";
            $params[] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $where[] = "DATE(u.created_at) <= ?";
            $params[] = $filters['date_to'];
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

    /**
     * Get every belt ever assigned to an authority user (active + historical).
     *
     * Used by Authority View v1 to allow the AR to browse and download photos
     * from belts they used to be assigned to. Product owner finalised this
     * scope on 2026-05-18.
     */
    public function getAllAssignedBeltIdsForAuthority(int $userId): array
    {
        return array_column($this->fetchAll(
            "SELECT DISTINCT belt_id FROM belt_authority_assignments
             WHERE authority_user_id = ?",
            [$userId]
        ), 'belt_id');
    }

    /**
     * Get belt option rows for the Authority View filter dropdown.
     *
     * Returns id + belt_code + common_name for every belt the AR has ever
     * been assigned to (active or historical). Distinct on belt_id so the
     * same belt does not appear twice if it was assigned, released and
     * re-assigned.
     */
    public function getBeltOptionsForAuthority(int $userId): array
    {
        return $this->fetchAll(
            "SELECT b.id, b.belt_code, b.common_name
             FROM green_belts b
             WHERE b.id IN (
               SELECT DISTINCT belt_id
               FROM belt_authority_assignments
               WHERE authority_user_id = ?
             )
             ORDER BY b.belt_code",
            [$userId]
        );
    }

    /**
     * Count approved authority-visible work photos per belt, within the same
     * filter context the gallery itself uses. Returns rows of { belt_id, photo_count }.
     *
     * Only counts uploads whose parent belt is in $allowedBeltIds — that is
     * the AR's full assignment set (active + historical) resolved by the service.
     */
    public function getBeltPhotoCounts(array $filters, array $allowedBeltIds): array
    {
        if (empty($allowedBeltIds)) return [];

        $placeholders = implode(',', array_fill(0, count($allowedBeltIds), '?'));
        $where = [
            "u.parent_type = 'GREEN_BELT'",
            "u.upload_type = 'WORK'",
            "u.authority_visibility = 'APPROVED'",
            "u.is_deleted = 0",
            "u.is_purged = 0",
            "u.parent_id IN ($placeholders)"
        ];
        $params = $allowedBeltIds;

        if (!empty($filters['date'])) {
            $where[] = "DATE(u.created_at) = ?";
            $params[] = $filters['date'];
        }
        if (!empty($filters['date_from'])) {
            $where[] = "DATE(u.created_at) >= ?";
            $params[] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $where[] = "DATE(u.created_at) <= ?";
            $params[] = $filters['date_to'];
        }
        if (!empty($filters['work_type'])) {
            $where[] = "u.work_type = ?";
            $params[] = $filters['work_type'];
        }

        $whereClause = implode(' AND ', $where);
        return $this->fetchAll(
            "SELECT u.parent_id AS belt_id, COUNT(u.id) AS photo_count
             FROM uploads u
             WHERE {$whereClause}
             GROUP BY u.parent_id",
            $params
        );
    }
}
