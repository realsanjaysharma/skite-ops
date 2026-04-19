<?php

require_once __DIR__ . '/BaseRepository.php';

class MonitoringHistoryRepository extends BaseRepository {
    public function getHistory(array $filters, int $page, int $limit): array {
        $filterResult = $this->buildFilterClause($filters);
        $whereClause = $filterResult['clause'];
        $params = $filterResult['params'];

        $offset = ($page - 1) * $limit;
        
        $query = "SELECT u.id as upload_id, u.file_path, u.created_at as timestamp, 
                         u.is_discovery_mode, u.comment_text,
                         s.id as site_id, s.site_code, s.location_text, s.site_category, s.route_or_group,
                         creator.full_name as uploader_name
                  FROM uploads u
                  INNER JOIN sites s ON s.id = u.parent_id AND u.parent_type = 'SITE'
                  INNER JOIN users creator ON creator.id = u.created_by_user_id
                  {$whereClause}
                  ORDER BY u.created_at DESC, u.id DESC
                  LIMIT {$limit} OFFSET {$offset}";

        return $this->fetchAll($query, $params);
    }
    
    public function countHistory(array $filters): int {
        $filterResult = $this->buildFilterClause($filters);
        $whereClause = $filterResult['clause'];
        $params = $filterResult['params'];
        
        $query = "SELECT COUNT(DISTINCT u.id) as total
                  FROM uploads u
                  INNER JOIN sites s ON s.id = u.parent_id AND u.parent_type = 'SITE'
                  {$whereClause}";
                  
        $row = $this->fetchOne($query, $params);
        return (int)($row['total'] ?? 0);
    }

    private function buildFilterClause(array $filters): array {
        $where = ["u.is_deleted = 0", "u.is_purged = 0", "u.parent_type = 'SITE'"];
        $params = [];

        if (!empty($filters['date_from'])) {
            $where[] = "DATE(u.created_at) >= ?";
            $params[] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $where[] = "DATE(u.created_at) <= ?";
            $params[] = $filters['date_to'];
        }
        if (!empty($filters['site_id'])) {
            $where[] = "u.parent_id = ?";
            $params[] = (int)$filters['site_id'];
        }
        if (!empty($filters['site_category'])) {
            $where[] = "s.site_category = ?";
            $params[] = $filters['site_category'];
        }
        if (isset($filters['discovery_mode']) && $filters['discovery_mode'] !== '') {
            $where[] = "u.is_discovery_mode = ?";
            $params[] = (int)$filters['discovery_mode'];
        }
        
        if (!empty($filters['campaign_id']) || !empty($filters['client_name'])) {
            $subWhere = [];
            if (!empty($filters['campaign_id'])) {
                $subWhere[] = "c.id = ?";
                $params[] = (int)$filters['campaign_id'];
            }
            if (!empty($filters['client_name'])) {
                $subWhere[] = "c.client_name = ?";
                $params[] = $filters['client_name'];
            }
            $where[] = "EXISTS (
                SELECT 1 FROM campaign_sites cs 
                INNER JOIN campaigns c ON c.id = cs.campaign_id
                WHERE cs.site_id = s.id AND " . implode(' AND ', $subWhere) . "
            )";
        }

        return [
            'clause' => 'WHERE ' . implode(' AND ', $where),
            'params' => $params
        ];
    }
}
