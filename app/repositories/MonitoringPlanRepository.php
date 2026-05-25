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

    /**
     * Mark a due date as completed for a site on a given date.
     * Called after a monitoring upload is submitted.
     */
    public function markCompleted(int $siteId, string $dueDate): bool
    {
        return $this->execute(
            "UPDATE site_monitoring_due_dates
             SET completed_at = NOW()
             WHERE site_id = ? AND due_date = ? AND completed_at IS NULL",
            [$siteId, $dueDate]
        );
    }

    /**
     * Get today's planned site IDs for use in determining planned vs unplanned uploads.
     */
    public function getTodaysPlannedSiteIds(): array
    {
        $today = date('Y-m-d');
        $rows = $this->fetchAll(
            "SELECT DISTINCT site_id FROM site_monitoring_due_dates WHERE due_date = ?",
            [$today]
        );
        return array_column($rows, 'site_id');
    }

    /**
     * Count planned and completed for today (for shift summary).
     */
    public function getTodaysPlanSummary(): array
    {
        $today = date('Y-m-d');
        $row = $this->fetchOne(
            "SELECT COUNT(*) AS planned_count,
                    SUM(CASE WHEN completed_at IS NOT NULL THEN 1 ELSE 0 END) AS completed_count
             FROM site_monitoring_due_dates
             WHERE due_date = ?",
            [$today]
        );
        return [
            'planned_count' => (int) ($row['planned_count'] ?? 0),
            'completed_count' => (int) ($row['completed_count'] ?? 0),
        ];
    }

    /**
     * Get plan list with completion status for the monitoring plan page.
     * Adds completed_at to the existing plan data for missed-site filtering.
     */
    public function getPlanListWithCompletion(array $filters, string $month): array
    {
        $query = "SELECT s.id as site_id, s.site_code, s.location_text, s.site_category,
                         s.lighting_type, s.route_or_group,
                         smdd.due_date, smdd.completed_at,
                         COUNT(smdd.id) OVER (PARTITION BY s.id) as total_due_dates
                  FROM sites s
                  INNER JOIN site_monitoring_due_dates smdd
                    ON s.id = smdd.site_id AND smdd.plan_month = ?
                  WHERE s.is_active = 1";

        $params = [$month];

        if (!empty($filters['site_category'])) {
            $query .= " AND s.site_category = ?";
            $params[] = $filters['site_category'];
        }

        if (isset($filters['completion_status'])) {
            $today = date('Y-m-d');
            if ($filters['completion_status'] === 'completed') {
                $query .= " AND smdd.completed_at IS NOT NULL";
            } elseif ($filters['completion_status'] === 'missed') {
                $query .= " AND smdd.completed_at IS NULL AND smdd.due_date < ?";
                $params[] = $today;
            }
        }

        $query .= " ORDER BY smdd.due_date ASC, s.site_code ASC";

        return $this->fetchAll($query, $params);
    }
}
