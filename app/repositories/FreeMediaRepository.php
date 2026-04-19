<?php

class FreeMediaRepository extends BaseRepository {
    public function getList(array $filters, int $page, int $limit): array {
        $where = ["1=1"];
        $params = [];
        
        if (!empty($filters['status'])) {
            $where[] = "fm.status = ?";
            $params[] = $filters['status'];
        }
        if (!empty($filters['site_category'])) {
            $where[] = "s.site_category = ?";
            $params[] = $filters['site_category'];
        }
        if (!empty($filters['route_or_group'])) {
            $where[] = "s.route_or_group = ?";
            $params[] = $filters['route_or_group'];
        }

        $whereClause = implode(' AND ', $where);
        $offset = ($page - 1) * $limit;
        
        $query = "SELECT fm.*, s.site_code, s.location_text, s.site_category, s.route_or_group,
                  creator.full_name as created_by_user_name
                  FROM free_media_records fm
                  INNER JOIN sites s ON s.id = fm.site_id
                  LEFT JOIN users creator ON creator.id = fm.created_by_user_id
                  WHERE {$whereClause}
                  ORDER BY fm.id DESC
                  LIMIT {$limit} OFFSET {$offset}";

        return $this->fetchAll($query, $params);
    }
    
    public function countList(array $filters): int {
        $where = ["1=1"];
        $params = [];
        if (!empty($filters['status'])) {
            $where[] = "fm.status = ?";
            $params[] = $filters['status'];
        }
        if (!empty($filters['site_category'])) {
            $where[] = "s.site_category = ?";
            $params[] = $filters['site_category'];
        }
        if (!empty($filters['route_or_group'])) {
            $where[] = "s.route_or_group = ?";
            $params[] = $filters['route_or_group'];
        }

        $whereClause = implode(' AND ', $where);
        $row = $this->fetchOne("
            SELECT COUNT(*) as total 
            FROM free_media_records fm
            INNER JOIN sites s ON s.id = fm.site_id
            WHERE {$whereClause}
        ", $params);
        return (int)($row['total'] ?? 0);
    }

    public function findById(int $recordId): ?array {
        return $this->fetchOne("SELECT * FROM free_media_records WHERE id = ?", [$recordId]);
    }
    
    public function updateStatus(int $recordId, string $status, string $dateField, string $dateValue, ?string $notes): void {
        $sql = "UPDATE free_media_records SET status = ?, {$dateField} = ?, updated_at = NOW()";
        $params = [$status, $dateValue];
        if ($notes !== null) {
            $sql .= ", notes = ?";
            $params[] = $notes;
        }
        $sql .= " WHERE id = ?";
        $params[] = $recordId;
        
        $this->execute($sql, $params);
    }
    
    public function createConfirmedRecord(array $data): int {
        $this->execute(
            "INSERT INTO free_media_records (site_id, source_type, source_reference_id, discovered_date, confirmed_date, status, notes, created_by_user_id, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, 'CONFIRMED', ?, ?, NOW(), NOW())",
             [
                 $data['site_id'],
                 $data['source_type'],
                 $data['source_reference_id'] ?? null,
                 $data['discovered_date'],
                 $data['confirmed_date'],
                 $data['notes'] ?? null,
                 $data['created_by_user_id']
             ]
        );
        return (int)$this->lastInsertId();
    }
}
