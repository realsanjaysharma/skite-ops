<?php

require_once __DIR__ . '/BaseRepository.php';

class MonitoringPlanRepository extends BaseRepository {
    public function getPlanList(array $filters, string $month): array {
        $query = "SELECT s.id as site_id, s.site_code, s.location_text, s.site_category, 
                         s.lighting_type, s.route_or_group, 
                         COUNT(smdd.id) as selected_due_dates_count,
                         GROUP_CONCAT(smdd.due_date ORDER BY smdd.due_date ASC) as due_dates_list
                  FROM sites s
                  LEFT JOIN site_monitoring_due_dates smdd 
                    ON s.id = smdd.site_id AND smdd.plan_month = ?
                  WHERE s.is_active = 1";
        
        $params = [$month];

        if (!empty($filters['site_category'])) {
            $query .= " AND s.site_category = ?";
            $params[] = $filters['site_category'];
        }
        if (!empty($filters['lighting_type'])) {
            $query .= " AND s.lighting_type = ?";
            $params[] = $filters['lighting_type'];
        }
        if (!empty($filters['route_or_group'])) {
            $query .= " AND s.route_or_group = ?";
            $params[] = $filters['route_or_group'];
        }

        $query .= " GROUP BY s.id ORDER BY s.site_code ASC";

        return $this->fetchAll($query, $params);
    }

    public function getDueDatesForSiteAndMonth(int $siteId, string $month): array {
        $query = "SELECT due_date FROM site_monitoring_due_dates 
                  WHERE site_id = ? AND plan_month = ? ORDER BY due_date ASC";
        return array_column($this->fetchAll($query, [$siteId, $month]), 'due_date');
    }

    public function saveDueDates(int $siteId, string $month, array $dueDates, int $actorId, ?string $sourceGroupKey = null): void {
        // Delete existing for month
        $this->execute("DELETE FROM site_monitoring_due_dates WHERE site_id = ? AND plan_month = ?", [$siteId, $month]);
        
        if (empty($dueDates)) {
            return;
        }

        $query = "INSERT INTO site_monitoring_due_dates 
                  (site_id, due_date, plan_month, source_group_key, created_by_user_id, created_at, updated_at) 
                  VALUES ";
        
        $placeholders = [];
        $params = [];
        foreach ($dueDates as $date) {
            $placeholders[] = "(?, ?, ?, ?, ?, NOW(), NOW())";
            array_push($params, $siteId, $date, $month, $sourceGroupKey, $actorId);
        }
        
        $query .= implode(", ", $placeholders);
        $this->execute($query, $params);
    }

    public function getSiteIdsByGroup(string $group): array {
        $query = "SELECT id FROM sites WHERE route_or_group = ? AND is_active = 1";
        return array_column($this->fetchAll($query, [$group]), 'id');
    }
}
