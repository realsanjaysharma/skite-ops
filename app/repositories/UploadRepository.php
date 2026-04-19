<?php

/**
 * UploadRepository
 *
 * Purpose:
 * SQL-only data access for uploads and upload-adjacent persistence helpers.
 */

require_once __DIR__ . '/BaseRepository.php';

class UploadRepository extends BaseRepository
{
    /**
     * Insert one upload row and return its ID.
     */
    public function create(array $data): int
    {
        $this->execute(
            "INSERT INTO uploads
            (parent_type, parent_id, upload_type, work_type, is_discovery_mode, file_path,
             original_file_name, mime_type, file_size_bytes, photo_label, comment_text,
             gps_latitude, gps_longitude, authority_visibility, created_by_user_id)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [
                $data['parent_type'],
                $data['parent_id'],
                $data['upload_type'],
                $data['work_type'],
                $data['is_discovery_mode'],
                $data['file_path'],
                $data['original_file_name'],
                $data['mime_type'],
                $data['file_size_bytes'],
                $data['photo_label'],
                $data['comment_text'],
                $data['gps_latitude'],
                $data['gps_longitude'],
                $data['authority_visibility'],
                $data['created_by_user_id'],
            ]
        );

        return (int) $this->lastInsertId();
    }

    /**
     * Fetch one upload row with actor name context.
     */
    public function findById(int $uploadId): ?array
    {
        return $this->fetchOne(
            "SELECT u.*,
                    creator.full_name AS created_by_user_name,
                    reviewer.full_name AS reviewed_by_user_name,
                    deleter.full_name AS deleted_by_user_name,
                    purger.full_name AS purged_by_user_name
             FROM uploads u
             INNER JOIN users creator ON creator.id = u.created_by_user_id
             LEFT JOIN users reviewer ON reviewer.id = u.reviewed_by_user_id
             LEFT JOIN users deleter ON deleter.id = u.deleted_by_user_id
             LEFT JOIN users purger ON purger.id = u.purged_by_user_id
             WHERE u.id = ?
             LIMIT 1",
            [$uploadId]
        );
    }

    /**
     * Generic filtered upload list with optional pagination.
     */
    public function findAll(array $filters = [], int $page = 0, int $limit = 0): array
    {
        $filterResult = $this->buildFilterClause($filters);
        $whereClause = $filterResult['clause'];
        $params = $filterResult['params'];

        $limitClause = '';
        if ($page > 0 && $limit > 0) {
            $offset = ($page - 1) * $limit;
            $limitClause = "LIMIT {$limit} OFFSET {$offset}";
        }

        return $this->fetchAll(
            "SELECT u.*,
                    creator.full_name AS created_by_user_name,
                    gb.common_name AS belt_name,
                    s.site_code AS site_name
             FROM uploads u
             INNER JOIN users creator ON creator.id = u.created_by_user_id
             LEFT JOIN green_belts gb ON gb.id = u.parent_id AND u.parent_type = 'GREEN_BELT'
             LEFT JOIN sites s ON s.id = u.parent_id AND u.parent_type = 'SITE'
             {$whereClause}
             ORDER BY u.created_at DESC, u.id DESC
             {$limitClause}",
            $params
        );
    }

    /**
     * Count filtered uploads for pagination.
     */
    public function countAll(array $filters = []): int
    {
        $filterResult = $this->buildFilterClause($filters);
        $whereClause = $filterResult['clause'];
        $params = $filterResult['params'];

        $row = $this->fetchOne(
            "SELECT COUNT(*) AS total
             FROM uploads u
             {$whereClause}",
            $params
        );

        return (int) ($row['total'] ?? 0);
    }

    /**
     * Soft-delete one upload row.
     */
    public function softDelete(int $uploadId, int $actorUserId): bool
    {
        return $this->execute(
            "UPDATE uploads
             SET is_deleted = 1,
                 deleted_at = NOW(),
                 deleted_by_user_id = ?,
                 updated_at = NOW()
             WHERE id = ?",
            [$actorUserId, $uploadId]
        );
    }

    /**
     * Find an existing discovery free-media row for a site if one is still DISCOVERED.
     */
    public function findDiscoveryFreeMediaBySiteId(int $siteId): ?array
    {
        return $this->fetchOne(
            "SELECT *
             FROM free_media_records
             WHERE site_id = ?
               AND source_type = 'MONITORING_DISCOVERY'
               AND status = 'DISCOVERED'
             ORDER BY updated_at DESC, id DESC
             LIMIT 1",
            [$siteId]
        );
    }

    /**
     * Create a new DISCOVERED free-media row for monitoring discovery.
     */
    public function createDiscoveryFreeMediaRecord(int $siteId, int $uploadId, string $discoveredDate): int
    {
        $this->execute(
            "INSERT INTO free_media_records
            (site_id, source_type, source_reference_id, discovered_date, status)
            VALUES (?, 'MONITORING_DISCOVERY', ?, ?, 'DISCOVERED')",
            [$siteId, $uploadId, $discoveredDate]
        );

        return (int) $this->lastInsertId();
    }

    /**
     * Refresh the representative discovery upload for an existing DISCOVERED row.
     */
    public function refreshDiscoveryFreeMediaRecord(int $recordId, int $uploadId, string $discoveredDate): bool
    {
        return $this->execute(
            "UPDATE free_media_records
             SET source_reference_id = ?,
                 discovered_date = ?,
                 updated_at = NOW()
             WHERE id = ?",
            [$uploadId, $discoveredDate, $recordId]
        );
    }

    /**
     * Parent existence check for polymorphic upload parents.
     */
    public function parentExists(string $parentType, int $parentId): bool
    {
        $tableMap = [
            'GREEN_BELT' => 'green_belts',
            'SITE' => 'sites',
            'TASK' => 'tasks',
        ];

        if (!isset($tableMap[$parentType])) {
            throw new InvalidArgumentException('Unsupported parent_type.');
        }

        $row = $this->fetchOne(
            "SELECT id FROM {$tableMap[$parentType]} WHERE id = ? LIMIT 1",
            [$parentId]
        );

        return $row !== null;
    }

    /**
     * Build shared WHERE clause and params from filter array.
     */
    private function buildFilterClause(array $filters): array
    {
        $where = ['u.is_purged = 0'];
        $params = [];

        if (empty($filters['include_deleted'])) {
            $where[] = 'u.is_deleted = 0';
        }

        if (!empty($filters['parent_type'])) {
            $where[] = 'u.parent_type = ?';
            $params[] = $filters['parent_type'];
        }

        if (!empty($filters['parent_id'])) {
            $where[] = 'u.parent_id = ?';
            $params[] = (int) $filters['parent_id'];
        }

        if (!empty($filters['upload_type'])) {
            $where[] = 'u.upload_type = ?';
            $params[] = $filters['upload_type'];
        }

        if (isset($filters['discovery_mode']) && $filters['discovery_mode'] !== null && $filters['discovery_mode'] !== '') {
            $where[] = 'u.is_discovery_mode = ?';
            $params[] = (int) $filters['discovery_mode'];
        }

        if (!empty($filters['authority_visibility'])) {
            $where[] = 'u.authority_visibility = ?';
            $params[] = $filters['authority_visibility'];
        }

        if (!empty($filters['created_by_user_id'])) {
            $where[] = 'u.created_by_user_id = ?';
            $params[] = (int) $filters['created_by_user_id'];
        }

        if (!empty($filters['date_from'])) {
            $where[] = 'DATE(u.created_at) >= ?';
            $params[] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $where[] = 'DATE(u.created_at) <= ?';
            $params[] = $filters['date_to'];
        }

        return [
            'clause' => 'WHERE ' . implode(' AND ', $where),
            'params' => $params,
        ];
    }
}
