<?php

class AuthorityViewRepository extends BaseRepository {
    public function getList(array $filters, int $page, int $limit, ?array $allowedBeltIds): array {
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
    
    public function countList(array $filters, ?array $allowedBeltIds): int {
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

    public function getAssignedBeltIdsForAuthority(int $userId): array {
        return array_column($this->fetchAll(
            "SELECT green_belt_id FROM belt_authority_assignments 
             WHERE authority_user_id = ? AND released_date IS NULL", 
            [$userId]
        ), 'green_belt_id');
    }
}
