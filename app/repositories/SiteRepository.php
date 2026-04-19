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
