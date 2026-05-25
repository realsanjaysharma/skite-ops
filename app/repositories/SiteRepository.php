<?php

require_once __DIR__ . '/BaseRepository.php';

class SiteRepository extends BaseRepository {
    public function findById(int $id): ?array {
        $sql = "SELECT s.*, gb.belt_code as green_belt_reference 
                FROM sites s
                LEFT JOIN green_belts gb ON s.green_belt_id = gb.id
                WHERE s.id = ?";
        return $this->fetchOne($sql, [$id]);
    }

    public function findBySiteCode(string $siteCode): ?array {
        return $this->fetchOne("SELECT * FROM sites WHERE site_code = ?", [$siteCode]);
    }

    public function findAll(array $filters, int $page, int $limit): array {
        $query = "SELECT s.*, gb.belt_code as green_belt_reference 
                  FROM sites s
                  LEFT JOIN green_belts gb ON s.green_belt_id = gb.id
                  WHERE 1=1";
        $params = [];

        if (isset($filters['site_category']) && $filters['site_category'] !== '') {
            $query .= " AND s.site_category = ?";
            $params[] = $filters['site_category'];
        }
        if (isset($filters['lighting_type']) && $filters['lighting_type'] !== '') {
            $query .= " AND s.lighting_type = ?";
            $params[] = $filters['lighting_type'];
        }
        if (isset($filters['is_active']) && $filters['is_active'] !== '') {
            $query .= " AND s.is_active = ?";
            $params[] = (int) $filters['is_active'];
        }
        if (isset($filters['route_or_group']) && $filters['route_or_group'] !== '') {
            $query .= " AND s.route_or_group = ?";
            $params[] = $filters['route_or_group'];
        }
        if (isset($filters['green_belt_id']) && $filters['green_belt_id'] !== '') {
            $query .= " AND s.green_belt_id = ?";
            $params[] = (int) $filters['green_belt_id'];
        }

        $query .= " ORDER BY s.created_at DESC";

        $offset = ($page - 1) * $limit;
        $query .= " LIMIT $limit OFFSET $offset";

        return $this->fetchAll($query, $params);
    }

    public function countAll(array $filters): int {
        $query = "SELECT COUNT(*) as total FROM sites s WHERE 1=1";
        $params = [];

        if (isset($filters['site_category']) && $filters['site_category'] !== '') {
            $query .= " AND s.site_category = ?";
            $params[] = $filters['site_category'];
        }
        if (isset($filters['lighting_type']) && $filters['lighting_type'] !== '') {
            $query .= " AND s.lighting_type = ?";
            $params[] = $filters['lighting_type'];
        }
        if (isset($filters['is_active']) && $filters['is_active'] !== '') {
            $query .= " AND s.is_active = ?";
            $params[] = (int) $filters['is_active'];
        }
        if (isset($filters['route_or_group']) && $filters['route_or_group'] !== '') {
            $query .= " AND s.route_or_group = ?";
            $params[] = $filters['route_or_group'];
        }
        if (isset($filters['green_belt_id']) && $filters['green_belt_id'] !== '') {
            $query .= " AND s.green_belt_id = ?";
            $params[] = (int) $filters['green_belt_id'];
        }

        $row = $this->fetchOne($query, $params);
        return (int) ($row['total'] ?? 0);
    }

    /**
     * Search active sites by site_code prefix. Returns up to $limit matches.
     * Used by monitoring upload ad-hoc site selector.
     */
    public function searchBySiteCode(string $prefix, int $limit = 20): array
    {
        $sql = "SELECT id, site_code, location_text, site_category
                FROM sites
                WHERE is_active = 1
                  AND site_code LIKE ?
                ORDER BY site_code ASC
                LIMIT {$limit}";
        return $this->fetchAll($sql, [$prefix . '%']);
    }

    /**
     * Find the nearest pending-discovery site within a radius using Haversine.
     * Only matches DISC-* sites with a DISCOVERED free_media_record.
     *
     * @param float $lat Latitude of the new discovery
     * @param float $lng Longitude of the new discovery
     * @param int $radiusMeters Maximum distance in meters
     * @return array|null Nearest matching site row or null
     */
    public function findDiscoveryNearby(float $lat, float $lng, int $radiusMeters): ?array
    {
        $sql = "SELECT s.id, s.site_code, s.latitude, s.longitude,
                       (6371000 * ACOS(
                           COS(RADIANS(?)) * COS(RADIANS(s.latitude)) *
                           COS(RADIANS(s.longitude) - RADIANS(?)) +
                           SIN(RADIANS(?)) * SIN(RADIANS(s.latitude))
                       )) AS distance_meters
                FROM sites s
                INNER JOIN free_media_records fm ON fm.site_id = s.id AND fm.status = 'DISCOVERED'
                WHERE s.site_code LIKE 'DISC-%'
                  AND s.is_active = 0
                  AND s.latitude IS NOT NULL
                  AND s.longitude IS NOT NULL
                HAVING distance_meters <= ?
                ORDER BY distance_meters ASC
                LIMIT 1";

        return $this->fetchOne($sql, [$lat, $lng, $lat, $radiusMeters]);
    }

    /**
     * Generate next sequential discovery site code for today.
     * Format: DISC-YYYYMMDD-NNN (e.g., DISC-20260524-001)
     *
     * @return string The generated site_code (caller must handle race conditions)
     */
    public function generateDiscoverySiteCode(): string
    {
        $today = date('Ymd');
        $prefix = 'DISC-' . $today . '-';

        $row = $this->fetchOne(
            "SELECT COUNT(*) AS cnt FROM sites WHERE site_code LIKE ?",
            [$prefix . '%']
        );

        $next = ((int)($row['cnt'] ?? 0)) + 1;

        return $prefix . str_pad((string)$next, 3, '0', STR_PAD_LEFT);
    }

    /**
     * Get enriched site data with client name, creative URL, board size.
     * Used by monitoring upload page for card display.
     *
     * Joins campaign_sites + campaigns for active client name.
     * Joins uploads for today's upload status by the given user.
     * Joins issues for open issue count.
     *
     * @param array $siteIds  If non-empty, filter to these site IDs only
     * @param int   $userId   Current user ID (for uploaded_today check)
     * @param array $filters  Optional: ['site_category' => ?, 'route_or_group' => ?]
     */
    public function findEnrichedSites(array $siteIds, int $userId, array $filters = []): array
    {
        $where = ['s.is_active = 1'];
        $params = [];

        if (!empty($siteIds)) {
            $placeholders = implode(',', array_fill(0, count($siteIds), '?'));
            $where[] = "s.id IN ($placeholders)";
            $params = array_merge($params, $siteIds);
        }

        if (!empty($filters['site_category'])) {
            $where[] = 's.site_category = ?';
            $params[] = $filters['site_category'];
        }

        if (!empty($filters['route_or_group'])) {
            $where[] = 's.route_or_group = ?';
            $params[] = $filters['route_or_group'];
        }

        $today = date('Y-m-d');
        $params[] = $userId;
        $params[] = $today;

        $whereClause = implode(' AND ', $where);

        $sql = "SELECT s.id, s.site_code, s.location_text, s.site_category,
                       s.route_or_group, s.board_type,
                       s.board_width_ft, s.board_height_ft,
                       s.latitude, s.longitude,
                       s.creative_upload_id, s.last_monitored_at,
                       s.last_monitored_by_user_id,
                       lmu.full_name AS last_monitored_by_name,
                       -- Active client name (most recently linked campaign)
                       (SELECT c.client_name
                        FROM campaign_sites cs2
                        INNER JOIN campaigns c ON c.id = cs2.campaign_id AND c.status = 'ACTIVE'
                        WHERE cs2.site_id = s.id AND cs2.linked_to_date IS NULL
                        ORDER BY cs2.linked_from_date DESC
                        LIMIT 1
                       ) AS client_name,
                       -- Open issue count
                       (SELECT COUNT(*)
                        FROM issues i
                        WHERE i.site_id = s.id AND i.status IN ('OPEN', 'IN_PROGRESS')
                       ) AS open_issue_count,
                       -- Uploaded today by current user
                       (SELECT MAX(u.created_at)
                        FROM uploads u
                        WHERE u.parent_type = 'SITE'
                          AND u.parent_id = s.id
                          AND u.created_by_user_id = ?
                          AND DATE(u.created_at) = ?
                          AND u.is_deleted = 0
                       ) AS uploaded_today_at
                FROM sites s
                LEFT JOIN users lmu ON lmu.id = s.last_monitored_by_user_id
                WHERE {$whereClause}
                ORDER BY s.site_code ASC";

        return $this->fetchAll($sql, $params);
    }

    /**
     * Get distinct route_or_group values for a site category, with site counts.
     * Used by monitoring upload "Unplanned" tab route chips.
     */
    public function getRoutesByCategory(string $category): array
    {
        return $this->fetchAll(
            "SELECT route_or_group, COUNT(*) AS site_count
             FROM sites
             WHERE is_active = 1
               AND site_category = ?
               AND route_or_group IS NOT NULL
               AND route_or_group != ''
             GROUP BY route_or_group
             ORDER BY route_or_group ASC",
            [$category]
        );
    }

    /**
     * Search active sites by client name, location text, or site code.
     * Returns enriched card data (same shape as findEnrichedSites).
     */
    public function searchSitesEnriched(string $query, int $userId, int $limit = 20): array
    {
        $today = date('Y-m-d');
        $likeQuery = '%' . $query . '%';

        $sql = "SELECT s.id, s.site_code, s.location_text, s.site_category,
                       s.route_or_group, s.board_type,
                       s.board_width_ft, s.board_height_ft,
                       s.latitude, s.longitude,
                       s.creative_upload_id, s.last_monitored_at,
                       s.last_monitored_by_user_id,
                       lmu.full_name AS last_monitored_by_name,
                       (SELECT c.client_name
                        FROM campaign_sites cs2
                        INNER JOIN campaigns c ON c.id = cs2.campaign_id AND c.status = 'ACTIVE'
                        WHERE cs2.site_id = s.id AND cs2.linked_to_date IS NULL
                        ORDER BY cs2.linked_from_date DESC
                        LIMIT 1
                       ) AS client_name,
                       (SELECT COUNT(*)
                        FROM issues i
                        WHERE i.site_id = s.id AND i.status IN ('OPEN', 'IN_PROGRESS')
                       ) AS open_issue_count,
                       (SELECT MAX(u.created_at)
                        FROM uploads u
                        WHERE u.parent_type = 'SITE'
                          AND u.parent_id = s.id
                          AND u.created_by_user_id = ?
                          AND DATE(u.created_at) = ?
                          AND u.is_deleted = 0
                       ) AS uploaded_today_at
                FROM sites s
                LEFT JOIN users lmu ON lmu.id = s.last_monitored_by_user_id
                LEFT JOIN campaign_sites cs ON cs.site_id = s.id AND cs.linked_to_date IS NULL
                LEFT JOIN campaigns c ON c.id = cs.campaign_id AND c.status = 'ACTIVE'
                WHERE s.is_active = 1
                  AND (s.site_code LIKE ?
                       OR s.location_text LIKE ?
                       OR c.client_name LIKE ?)
                GROUP BY s.id
                ORDER BY s.site_code ASC
                LIMIT {$limit}";

        return $this->fetchAll($sql, [$userId, $today, $likeQuery, $likeQuery, $likeQuery]);
    }

    /**
     * Update last_monitored_at and last_monitored_by_user_id after a monitoring upload.
     */
    public function updateLastMonitored(int $siteId, int $userId): bool
    {
        return $this->execute(
            "UPDATE sites SET last_monitored_at = NOW(), last_monitored_by_user_id = ? WHERE id = ?",
            [$userId, $siteId]
        );
    }

    /**
     * Update just the creative_upload_id for a site.
     */
    public function updateCreative(int $siteId, int $uploadId): bool
    {
        return $this->execute(
            "UPDATE sites SET creative_upload_id = ?, updated_at = NOW() WHERE id = ?",
            [$uploadId, $siteId]
        );
    }

    public function create(array $data): int {
        $query = "INSERT INTO sites (
            site_code, location_text, site_category, green_belt_id, route_or_group,
            ownership_name, board_type, board_width_ft, board_height_ft,
            lighting_type, latitude, longitude, creative_upload_id, is_active, created_at, updated_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";

        $this->execute($query, [
            $data['site_code'],
            $data['location_text'] ?? null,
            $data['site_category'],
            $data['green_belt_id'] ?? null,
            $data['route_or_group'] ?? null,
            $data['ownership_name'] ?? null,
            $data['board_type'] ?? null,
            $data['board_width_ft'] ?? null,
            $data['board_height_ft'] ?? null,
            $data['lighting_type'],
            $data['latitude'] ?? null,
            $data['longitude'] ?? null,
            $data['creative_upload_id'] ?? null,
            $data['is_active'] ?? 1
        ]);

        return (int) $this->lastInsertId();
    }

    public function update(int $id, array $data): bool {
        $query = "UPDATE sites SET
            location_text = ?,
            site_category = ?,
            green_belt_id = ?,
            route_or_group = ?,
            ownership_name = ?,
            board_type = ?,
            board_width_ft = ?,
            board_height_ft = ?,
            lighting_type = ?,
            latitude = ?,
            longitude = ?,
            creative_upload_id = ?,
            is_active = ?,
            updated_at = NOW()
        WHERE id = ?";

        return $this->execute($query, [
            $data['location_text'] ?? null,
            $data['site_category'],
            $data['green_belt_id'] ?? null,
            $data['route_or_group'] ?? null,
            $data['ownership_name'] ?? null,
            $data['board_type'] ?? null,
            $data['board_width_ft'] ?? null,
            $data['board_height_ft'] ?? null,
            $data['lighting_type'],
            $data['latitude'] ?? null,
            $data['longitude'] ?? null,
            $data['creative_upload_id'] ?? null,
            $data['is_active'] ?? 1,
            $id
        ]);
    }
}
