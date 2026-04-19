<?php

class CampaignRepository extends BaseRepository {
    public function getList(array $filters, int $page, int $limit): array {
        $where = ["1=1"];
        $params = [];
        
        if (!empty($filters['status'])) {
            $where[] = "c.status = ?";
            $params[] = $filters['status'];
        }
        if (!empty($filters['client_name'])) {
            $where[] = "c.client_name = ?";
            $params[] = $filters['client_name'];
        }
        if (!empty($filters['site_category'])) {
            $where[] = "EXISTS (
                SELECT 1 FROM campaign_sites cs 
                INNER JOIN sites s ON s.id = cs.site_id 
                WHERE cs.campaign_id = c.id AND s.site_category = ? AND cs.released_date IS NULL
            )";
            $params[] = $filters['site_category'];
        }

        $whereClause = implode(' AND ', $where);
        $offset = ($page - 1) * $limit;
        
        $query = "SELECT c.*, 
                         (SELECT COUNT(*) FROM campaign_sites cs WHERE cs.campaign_id = c.id AND cs.released_date IS NULL) as active_sites_count
                  FROM campaigns c
                  WHERE {$whereClause}
                  ORDER BY c.created_at DESC
                  LIMIT {$limit} OFFSET {$offset}";

        return $this->fetchAll($query, $params);
    }
    
    public function countList(array $filters): int {
        $where = ["1=1"];
        $params = [];
        
        if (!empty($filters['status'])) {
            $where[] = "c.status = ?";
            $params[] = $filters['status'];
        }
        if (!empty($filters['client_name'])) {
            $where[] = "c.client_name = ?";
            $params[] = $filters['client_name'];
        }
        if (!empty($filters['site_category'])) {
            $where[] = "EXISTS (
                SELECT 1 FROM campaign_sites cs 
                INNER JOIN sites s ON s.id = cs.site_id 
                WHERE cs.campaign_id = c.id AND s.site_category = ? AND cs.released_date IS NULL
            )";
            $params[] = $filters['site_category'];
        }

        $whereClause = implode(' AND ', $where);
        $row = $this->fetchOne("SELECT COUNT(*) as total FROM campaigns c WHERE {$whereClause}", $params);
        return (int)($row['total'] ?? 0);
    }

    public function findById(int $campaignId): ?array {
        $campaign = $this->fetchOne("SELECT * FROM campaigns WHERE id = ?", [$campaignId]);
        if (!$campaign) return null;
        
        $campaign['sites'] = $this->fetchAll("
            SELECT s.*, cs.assigned_date, cs.released_date 
            FROM campaign_sites cs
            INNER JOIN sites s ON s.id = cs.site_id
            WHERE cs.campaign_id = ?
        ", [$campaignId]);
        
        return $campaign;
    }
    
    public function createCampaign(array $data): int {
        $this->execute(
            "INSERT INTO campaigns (campaign_code, client_name, campaign_name, start_date, expected_end_date, status, created_by_user_id, created_at, updated_at) 
             VALUES (?, ?, ?, ?, ?, 'ACTIVE', ?, NOW(), NOW())",
             [$data['campaign_code'], $data['client_name'], $data['campaign_name'], $data['start_date'], $data['expected_end_date'], $data['created_by_user_id']]
        );
        return (int)$this->lastInsertId();
    }
    
    public function updateCampaign(int $campaignId, array $data): void {
        $this->execute(
            "UPDATE campaigns SET client_name = ?, campaign_name = ?, expected_end_date = ?, updated_at = NOW() WHERE id = ?",
            [$data['client_name'], $data['campaign_name'], $data['expected_end_date'], $campaignId]
        );
    }
    
    public function endCampaign(int $campaignId, string $actualEndDate): void {
        $this->execute(
            "UPDATE campaigns SET status = 'ENDED', actual_end_date = ?, updated_at = NOW() WHERE id = ?",
            [$actualEndDate, $campaignId]
        );
        
        $this->execute(
            "UPDATE campaign_sites SET released_date = ?, updated_at = NOW() WHERE campaign_id = ? AND released_date IS NULL",
            [$actualEndDate, $campaignId]
        );
    }

    public function getActiveSiteIds(int $campaignId): array {
        return array_column($this->fetchAll(
            "SELECT site_id FROM campaign_sites WHERE campaign_id = ? AND released_date IS NULL",
            [$campaignId]
        ), 'site_id');
    }
    
    public function linkSites(int $campaignId, array $siteIds, string $date): void {
        if (empty($siteIds)) return;
        
        $values = [];
        $params = [];
        foreach ($siteIds as $siteId) {
            $values[] = "(?, ?, ?, NOW(), NOW())";
            array_push($params, $campaignId, $siteId, $date);
        }
        $query = "INSERT INTO campaign_sites (campaign_id, site_id, assigned_date, created_at, updated_at) VALUES " . implode(', ', $values);
        $this->execute($query, $params);
    }
    
    public function releaseSites(int $campaignId, array $siteIds, string $date): void {
        if (empty($siteIds)) return;
        
        $placeholders = implode(',', array_fill(0, count($siteIds), '?'));
        $params = array_merge([$date, $campaignId], $siteIds);
        
        $this->execute(
            "UPDATE campaign_sites SET released_date = ?, updated_at = NOW() WHERE campaign_id = ? AND released_date IS NULL AND site_id IN ($placeholders)",
            $params
        );
    }
}
