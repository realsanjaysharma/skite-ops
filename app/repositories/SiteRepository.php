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

    public function create(array $data): int {
        $query = "INSERT INTO sites (
            site_code, location_text, site_category, green_belt_id, route_or_group,
            ownership_name, board_type, lighting_type, latitude, longitude, is_active, created_at, updated_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";

        $this->execute($query, [
            $data['site_code'],
            $data['location_text'] ?? null,
            $data['site_category'],
            $data['green_belt_id'] ?? null,
            $data['route_or_group'] ?? null,
            $data['ownership_name'] ?? null,
            $data['board_type'] ?? null,
            $data['lighting_type'],
            $data['latitude'] ?? null,
            $data['longitude'] ?? null,
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
            lighting_type = ?, 
            latitude = ?, 
            longitude = ?, 
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
            $data['lighting_type'],
            $data['latitude'] ?? null,
            $data['longitude'] ?? null,
            $data['is_active'] ?? 1,
            $id
        ]);
    }
}
