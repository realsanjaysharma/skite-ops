<?php

require_once __DIR__ . '/BaseRepository.php';

class ReportRepository extends BaseRepository
{
    /**
     * Get Worker Activity Report
     * As per docs/11_build_specs/07_REPORTS_ALERTS_AND_FORMULAS.md Section 3
     */
    public function getWorkerActivityReport(string $monthStart, string $monthEnd, ?int $workerId = null, ?string $skillTag = null): array
    {
        $workerFilter = '';
        // 14 date params: 5 subqueries × BETWEEN (2 each) = 10, plus assigned_tasks (monthEnd + monthStart) + completed_tasks (monthStart + monthEnd) = 4
        $params = [
            $monthStart, $monthEnd,  // present_days_count BETWEEN
            $monthStart, $monthEnd,  // absent_days_count BETWEEN
            $monthStart, $monthEnd,  // half_days_count BETWEEN
            $monthStart, $monthEnd,  // active_days_count BETWEEN
            $monthStart, $monthEnd,  // daily_entries_count BETWEEN
            $monthEnd, $monthStart,  // assigned_tasks_count (<= monthEnd, >= monthStart)
            $monthStart, $monthEnd,  // completed_tasks_count BETWEEN
        ];

        $workerConditions = [];
        if ($workerId) {
            $workerConditions[] = 'fw.id = ?';
            $params[] = $workerId;
        }
        if ($skillTag) {
            $workerConditions[] = 'fw.skill_tag = ?';
            $params[] = $skillTag;
        }

        $workerFilter = empty($workerConditions) ? '' : 'WHERE ' . implode(' AND ', $workerConditions);

        $sql = "
            SELECT 
                fw.id AS worker_id,
                fw.worker_name,
                fw.skill_tag,
                
                (SELECT COUNT(*) FROM worker_daily_entries wde 
                 WHERE wde.worker_id = fw.id 
                   AND wde.entry_date BETWEEN ? AND ?
                   AND wde.attendance_status = 'PRESENT') AS present_days_count,
                   
                (SELECT COUNT(*) FROM worker_daily_entries wde 
                 WHERE wde.worker_id = fw.id 
                   AND wde.entry_date BETWEEN ? AND ?
                   AND wde.attendance_status = 'ABSENT') AS absent_days_count,
                   
                (SELECT COUNT(*) FROM worker_daily_entries wde 
                 WHERE wde.worker_id = fw.id 
                   AND wde.entry_date BETWEEN ? AND ?
                   AND wde.attendance_status = 'HALF_DAY') AS half_days_count,
                   
                (SELECT COUNT(*) FROM worker_daily_entries wde 
                 WHERE wde.worker_id = fw.id 
                   AND wde.entry_date BETWEEN ? AND ?
                   AND wde.attendance_status IN ('PRESENT', 'HALF_DAY')) AS active_days_count,
                   
                (SELECT COUNT(*) FROM worker_daily_entries wde 
                 WHERE wde.worker_id = fw.id 
                   AND wde.entry_date BETWEEN ? AND ?) AS daily_entries_count,
                   
                (SELECT COUNT(DISTINCT twa.task_id) FROM task_worker_assignments twa 
                 WHERE twa.worker_id = fw.id
                   AND twa.assigned_date <= ?
                   AND (twa.release_date IS NULL OR twa.release_date >= ?)) AS assigned_tasks_count,
                   
                (SELECT COUNT(DISTINCT twa.task_id) FROM task_worker_assignments twa 
                 JOIN tasks t ON twa.task_id = t.id
                 WHERE twa.worker_id = fw.id 
                   AND t.status IN ('COMPLETED', 'ARCHIVED')
                   AND DATE(t.actual_close_date) BETWEEN ? AND ?) AS completed_tasks_count
                   
            FROM fabrication_workers fw
            {$workerFilter}
            ORDER BY fw.worker_name ASC
        ";

        return $this->fetchAll($sql, $params);
    }

    public function getBeltHealthReport(string $monthStart, string $monthEnd, ?int $supervisorId = null): array {
        $params = [
            $monthStart, $monthStart, $monthEnd, // required_watering_days subquery
            $monthStart, $monthEnd,              // completed_watering_days
            $monthStart, $monthEnd,              // cycles_completed_count
            $monthEnd, $monthEnd,                // days_since_last_completion
            $monthEnd                            // open_cycles_count
        ];

        $where = [];
        if ($supervisorId) {
            $where[] = "gb.id IN (SELECT belt_id FROM belt_supervisor_assignments WHERE supervisor_user_id = ? AND start_date <= ? AND (end_date IS NULL OR end_date >= ?))";
            $params[] = $supervisorId;
            $params[] = $monthEnd;
            $params[] = $monthStart;
        }

        $whereClause = empty($where) ? '' : 'WHERE ' . implode(' AND ', $where);

        $sql = "
            SELECT 
                gb.id AS belt_id,
                gb.belt_code,
                gb.common_name,
                gb.permission_end_date,
                gb.maintenance_mode,
                
                (SELECT COUNT(*) FROM (
                    SELECT DATE_ADD(?, INTERVAL n DAY) as d
                    FROM (
                        SELECT 0 n UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9 UNION SELECT 10 UNION SELECT 11 UNION SELECT 12 UNION SELECT 13 UNION SELECT 14 UNION SELECT 15 UNION SELECT 16 UNION SELECT 17 UNION SELECT 18 UNION SELECT 19 UNION SELECT 20 UNION SELECT 21 UNION SELECT 22 UNION SELECT 23 UNION SELECT 24 UNION SELECT 25 UNION SELECT 26 UNION SELECT 27 UNION SELECT 28 UNION SELECT 29 UNION SELECT 30
                    ) numbers
                    WHERE DATE_ADD(?, INTERVAL n DAY) <= ?
                ) dates
                WHERE gb.is_hidden = 0
                  AND gb.maintenance_mode = 'MAINTAINED'
                  AND gb.watering_frequency != 'NOT_REQUIRED'
                  AND NOT EXISTS (
                      SELECT 1 FROM watering_records wr 
                      WHERE wr.belt_id = gb.id 
                        AND wr.watering_date = dates.d 
                        AND wr.status = 'NOT_REQUIRED'
                  )
                ) as required_watering_days,

                (SELECT COUNT(*) FROM watering_records wr2
                 WHERE wr2.belt_id = gb.id
                   AND wr2.watering_date BETWEEN ? AND ?
                   AND wr2.status = 'DONE') as completed_watering_days,
                
                (SELECT COUNT(*) FROM maintenance_cycles mc 
                 WHERE mc.belt_id = gb.id AND DATE(mc.end_date) BETWEEN ? AND ?) AS cycles_completed_count,
                 
                DATEDIFF(?, (SELECT MAX(DATE(mc2.end_date)) FROM maintenance_cycles mc2 WHERE mc2.belt_id = gb.id AND DATE(mc2.end_date) <= ?)) AS days_since_last_completion,
                
                (SELECT COUNT(*) FROM issues i 
                 WHERE i.belt_id = gb.id AND i.status IN ('OPEN', 'IN_PROGRESS')) AS open_issues_count,

                (SELECT COUNT(*) FROM issues i2 
                 WHERE i2.belt_id = gb.id AND i2.status IN ('OPEN', 'IN_PROGRESS') AND i2.priority = 'CRITICAL') as critical_issues_count,

                (SELECT COUNT(*) FROM maintenance_cycles mc3 
                 WHERE mc3.belt_id = gb.id AND mc3.end_date IS NULL AND mc3.start_date <= ?) as open_cycles_count
                 
            FROM green_belts gb
            {$whereClause}
            ORDER BY gb.belt_code ASC
        ";

        return $this->fetchAll($sql, $params);
    }

    public function getSupervisorActivityReport(string $monthStart, string $monthEnd, ?int $supervisorId = null): array {
        $params = [
            $monthEnd, $monthStart,  // WHERE subquery
            $monthEnd, $monthStart,  // belts_covered_count
            $monthStart, $monthStart, $monthEnd, // required_watering_days
            $monthStart, $monthEnd,  // completed_watering_days
            $monthStart, $monthEnd,  // cycles_completed_count
            $monthStart, $monthEnd,  // average_cycle_duration_days
            $monthStart, $monthEnd,  // active_days_count
            $monthStart, $monthEnd,  // issues_raised_count
        ];

        $where = ["u.id IN (SELECT DISTINCT supervisor_user_id FROM belt_supervisor_assignments WHERE start_date <= ? AND (end_date IS NULL OR end_date >= ?))"];
        if ($supervisorId) {
            $where[] = "u.id = ?";
            $params[] = $supervisorId;
        }
        $whereClause = 'WHERE ' . implode(' AND ', $where);

        $sql = "
            SELECT 
                u.id AS supervisor_user_id,
                u.full_name AS supervisor_name,
                
                (SELECT COUNT(DISTINCT belt_id) FROM belt_supervisor_assignments sa 
                 WHERE sa.supervisor_user_id = u.id 
                 AND sa.start_date <= ?
                 AND (sa.end_date IS NULL OR sa.end_date >= ?)) AS belts_covered_count,

                (SELECT COUNT(*) FROM (
                    SELECT DATE_ADD(?, INTERVAL n DAY) as d
                    FROM (
                        SELECT 0 n UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9 UNION SELECT 10 UNION SELECT 11 UNION SELECT 12 UNION SELECT 13 UNION SELECT 14 UNION SELECT 15 UNION SELECT 16 UNION SELECT 17 UNION SELECT 18 UNION SELECT 19 UNION SELECT 20 UNION SELECT 21 UNION SELECT 22 UNION SELECT 23 UNION SELECT 24 UNION SELECT 25 UNION SELECT 26 UNION SELECT 27 UNION SELECT 28 UNION SELECT 29 UNION SELECT 30
                    ) numbers
                    WHERE DATE_ADD(?, INTERVAL n DAY) <= ?
                ) dates
                JOIN belt_supervisor_assignments sa2 ON sa2.supervisor_user_id = u.id 
                  AND sa2.start_date <= dates.d AND (sa2.end_date IS NULL OR sa2.end_date >= dates.d)
                JOIN green_belts gb2 ON sa2.belt_id = gb2.id
                WHERE gb2.is_hidden = 0
                  AND gb2.maintenance_mode = 'MAINTAINED'
                  AND gb2.watering_frequency != 'NOT_REQUIRED'
                  AND NOT EXISTS (
                      SELECT 1 FROM watering_records wr 
                      WHERE wr.belt_id = gb2.id 
                        AND wr.watering_date = dates.d 
                        AND wr.status = 'NOT_REQUIRED'
                  )
                ) as required_watering_days,

                (SELECT COUNT(*) FROM watering_records wr2
                 JOIN belt_supervisor_assignments sa3 ON wr2.belt_id = sa3.belt_id 
                   AND sa3.supervisor_user_id = u.id
                   AND sa3.start_date <= wr2.watering_date 
                   AND (sa3.end_date IS NULL OR sa3.end_date >= wr2.watering_date)
                 WHERE wr2.watering_date BETWEEN ? AND ?
                   AND wr2.status = 'DONE'
                ) as completed_watering_days,
                 
                (SELECT COUNT(*) FROM maintenance_cycles mc 
                 WHERE mc.closed_by_user_id = u.id AND DATE(mc.end_date) BETWEEN ? AND ?) AS cycles_completed_count,
                 
                (SELECT AVG(DATEDIFF(end_date, start_date)) FROM maintenance_cycles mc 
                 WHERE mc.closed_by_user_id = u.id AND DATE(mc.end_date) BETWEEN ? AND ?) AS average_cycle_duration_days,
                 
                (SELECT COUNT(*) FROM supervisor_attendance sa 
                 WHERE sa.supervisor_user_id = u.id AND sa.attendance_date BETWEEN ? AND ? AND sa.status = 'PRESENT') AS active_days_count,
                 
                (SELECT COUNT(*) FROM issues i 
                 WHERE i.raised_by_user_id = u.id AND DATE(i.created_at) BETWEEN ? AND ?) AS issues_raised_count
                 
            FROM users u
            {$whereClause}
            ORDER BY u.full_name ASC
        ";

        return $this->fetchAll($sql, $params);
    }

    public function getAdvertisementOperationsReport(string $monthStart, string $monthEnd, ?string $siteCategory = null): array {
        $params = [
            $monthEnd, $monthStart,   // active_campaigns_count
            $monthStart, $monthEnd,   // completed_campaigns_count
            $monthStart, $monthEnd,   // installations_completed_count
            $monthStart, $monthEnd,   // maintenance_tasks_completed_count
            $monthStart, $monthEnd,   // monitoring_due_count
            $monthStart, $monthEnd,   // monitoring_completed_count
            $monthStart, $monthEnd,   // free_media_added_count
            $monthEnd,                // free_media_active_flag expiry check
        ];

        $where = [];
        if ($siteCategory) {
            $where[] = "s.site_category = ?";
            $params[] = $siteCategory;
        }
        $whereClause = empty($where) ? '' : 'WHERE ' . implode(' AND ', $where);

        $sql = "
            SELECT 
                s.id AS site_id,
                s.site_code,
                s.location_text,
                s.site_category,
                
                (SELECT COUNT(*) FROM campaign_sites cs 
                 JOIN campaigns c ON cs.campaign_id = c.id 
                 WHERE cs.site_id = s.id 
                 AND c.start_date <= ?
                 AND (cs.linked_to_date IS NULL OR cs.linked_to_date >= ?)) AS active_campaigns_count,
                 
                (SELECT COUNT(*) FROM campaign_sites cs 
                 JOIN campaigns c ON cs.campaign_id = c.id 
                 WHERE cs.site_id = s.id 
                 AND c.status = 'ENDED'
                 AND c.actual_end_date BETWEEN ? AND ?) AS completed_campaigns_count,
                 
                (SELECT COUNT(*) FROM tasks t
                 JOIN task_requests tr ON t.request_id = tr.id
                 WHERE tr.site_id = s.id
                 AND t.vertical_type = 'ADVERTISEMENT'
                 AND t.task_category IN ('INSTALLATION', 'REMOVAL')
                 AND t.status IN ('COMPLETED', 'ARCHIVED')
                 AND DATE(t.actual_close_date) BETWEEN ? AND ?) AS installations_completed_count,
                 
                (SELECT COUNT(*) FROM tasks t
                 JOIN task_requests tr ON t.request_id = tr.id
                 WHERE tr.site_id = s.id
                 AND t.vertical_type = 'ADVERTISEMENT'
                 AND t.task_category IN ('MAINTENANCE', 'REPAIR')
                 AND t.status IN ('COMPLETED', 'ARCHIVED')
                 AND DATE(t.actual_close_date) BETWEEN ? AND ?) AS maintenance_tasks_completed_count,
                 
                (SELECT COUNT(*) FROM issues i 
                 WHERE i.site_id = s.id AND i.status IN ('OPEN', 'IN_PROGRESS')) AS open_issues_count,
                 
                (SELECT COUNT(*) FROM site_monitoring_due_dates sm
                 WHERE sm.site_id = s.id AND sm.due_date BETWEEN ? AND ?) AS monitoring_due_count,

                (SELECT COUNT(*) FROM site_monitoring_due_dates sm2
                 WHERE sm2.site_id = s.id AND sm2.due_date BETWEEN ? AND ?
                 AND EXISTS (
                     SELECT 1 FROM uploads u 
                     WHERE u.parent_type = 'SITE' AND u.parent_id = s.id 
                       AND u.is_deleted = 0 AND u.is_purged = 0 
                       AND DATE(u.created_at) = sm2.due_date
                 )) AS monitoring_completed_count,
                 
                (SELECT COUNT(*) FROM free_media_records fmr 
                 WHERE fmr.site_id = s.id AND DATE(fmr.created_at) BETWEEN ? AND ?) AS free_media_added_count,
                 
                (SELECT CASE WHEN COUNT(*) > 0 THEN 1 ELSE 0 END FROM free_media_records fmr 
                 WHERE fmr.site_id = s.id AND fmr.status = 'CONFIRMED_ACTIVE'
                 AND (fmr.expiry_date IS NULL OR fmr.expiry_date >= ?)) AS free_media_active_flag
                 
            FROM sites s
            {$whereClause}
            ORDER BY s.site_code ASC
        ";

        return $this->fetchAll($sql, $params);
    }
}

