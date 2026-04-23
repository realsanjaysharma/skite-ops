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
        $params = [
            'month_start1' => $monthStart,
            'month_end1' => $monthEnd,
            'month_start2' => $monthStart,
            'month_end2' => $monthEnd
        ];
        
        $workerWhere = [];
        if ($workerId) {
            $workerWhere[] = 'fw.id = :worker_id';
            $params['worker_id'] = $workerId;
        }
        if ($skillTag) {
            $workerWhere[] = 'fw.skill_tag = :skill_tag';
            $params['skill_tag'] = $skillTag;
        }
        
        $workerFilter = empty($workerWhere) ? '' : 'WHERE ' . implode(' AND ', $workerWhere);

        $sql = "
            SELECT 
                fw.id AS worker_id,
                fw.worker_name,
                fw.skill_tag,
                
                (SELECT COUNT(*) FROM worker_daily_entries wde 
                 WHERE wde.worker_id = fw.id 
                   AND wde.entry_date BETWEEN :month_start1 AND :month_end1
                   AND wde.attendance_status = 'PRESENT') AS present_days_count,
                   
                (SELECT COUNT(*) FROM worker_daily_entries wde 
                 WHERE wde.worker_id = fw.id 
                   AND wde.entry_date BETWEEN :month_start1 AND :month_end1
                   AND wde.attendance_status = 'ABSENT') AS absent_days_count,
                   
                (SELECT COUNT(*) FROM worker_daily_entries wde 
                 WHERE wde.worker_id = fw.id 
                   AND wde.entry_date BETWEEN :month_start1 AND :month_end1
                   AND wde.attendance_status = 'HALF_DAY') AS half_days_count,
                   
                (SELECT COUNT(*) FROM worker_daily_entries wde 
                 WHERE wde.worker_id = fw.id 
                   AND wde.entry_date BETWEEN :month_start1 AND :month_end1
                   AND wde.attendance_status IN ('PRESENT', 'HALF_DAY')) AS active_days_count,
                   
                (SELECT COUNT(*) FROM worker_daily_entries wde 
                 WHERE wde.worker_id = fw.id 
                   AND wde.entry_date BETWEEN :month_start1 AND :month_end1) AS daily_entries_count,
                   
                (SELECT COUNT(DISTINCT twa.task_id) FROM task_worker_assignments twa 
                 WHERE twa.worker_id = fw.id
                   AND twa.assigned_date <= :month_end2 
                   AND (twa.release_date IS NULL OR twa.release_date >= :month_start2)) AS assigned_tasks_count,
                   
                (SELECT COUNT(DISTINCT twa.task_id) FROM task_worker_assignments twa 
                 JOIN tasks t ON twa.task_id = t.id
                 WHERE twa.worker_id = fw.id 
                   AND t.status IN ('COMPLETED', 'ARCHIVED')
                   AND DATE(t.actual_close_date) BETWEEN :month_start2 AND :month_end2) AS completed_tasks_count
                   
            FROM fabrication_workers fw
            {$workerFilter}
            ORDER BY fw.worker_name ASC
        ";

        return $this->fetchAll($sql, $params);
    }

    public function getBeltHealthReport(string $monthStart, string $monthEnd, ?int $supervisorId = null): array {
        $params = [
            'month_start1' => $monthStart,
            'month_end1' => $monthEnd,
            'month_start2' => $monthStart,
            'month_end2' => $monthEnd
        ];
        
        $where = [];
        if ($supervisorId) {
            $where[] = "gb.id IN (SELECT belt_id FROM belt_supervisor_assignments WHERE supervisor_user_id = :supervisor_id AND (end_date IS NULL OR end_date >= :month_start3))";
            $params['supervisor_id'] = $supervisorId;
            $params['month_start3'] = $monthStart;
        }
        
        $whereClause = empty($where) ? '' : 'WHERE ' . implode(' AND ', $where);

        $sql = "
            SELECT 
                gb.id AS belt_id,
                gb.belt_code,
                gb.common_name,
                
                (SELECT COUNT(*) FROM maintenance_cycles mc 
                 WHERE mc.belt_id = gb.id AND DATE(mc.end_date) BETWEEN :month_start1 AND :month_end1) AS cycles_completed_count,
                 
                DATEDIFF(:month_end2, (SELECT MAX(DATE(mc2.end_date)) FROM maintenance_cycles mc2 WHERE mc2.belt_id = gb.id AND DATE(mc2.end_date) <= :month_end2)) AS days_since_last_completion,
                
                (SELECT COUNT(*) FROM issues i 
                 WHERE i.belt_id = gb.id AND i.status IN ('OPEN', 'IN_PROGRESS')) AS open_issues_count
                 
            FROM green_belts gb
            {$whereClause}
            ORDER BY gb.belt_code ASC
        ";

        return $this->fetchAll($sql, $params);
    }

    public function getSupervisorActivityReport(string $monthStart, string $monthEnd, ?int $supervisorId = null): array {
        $params = [
            'month_start1' => $monthStart,
            'month_end1' => $monthEnd,
            'month_start2' => $monthStart,
            'month_end2' => $monthEnd
        ];
        
        $where = ["u.id IN (SELECT DISTINCT supervisor_user_id FROM belt_supervisor_assignments WHERE start_date <= :month_end1 AND (end_date IS NULL OR end_date >= :month_start1))"];
        if ($supervisorId) {
            $where[] = "u.id = :supervisor_id";
            $params['supervisor_id'] = $supervisorId;
        }
        $whereClause = 'WHERE ' . implode(' AND ', $where);

        $sql = "
            SELECT 
                u.id AS supervisor_user_id,
                u.full_name AS supervisor_name,
                
                (SELECT COUNT(DISTINCT belt_id) FROM belt_supervisor_assignments sa 
                 WHERE sa.supervisor_user_id = u.id 
                 AND sa.start_date <= :month_end2 
                 AND (sa.end_date IS NULL OR sa.end_date >= :month_start2)) AS belts_covered_count,
                 
                (SELECT COUNT(*) FROM maintenance_cycles mc 
                 WHERE mc.closed_by_user_id = u.id AND DATE(mc.end_date) BETWEEN :month_start2 AND :month_end2) AS cycles_completed_count,
                 
                (SELECT AVG(DATEDIFF(end_date, start_date)) FROM maintenance_cycles mc 
                 WHERE mc.closed_by_user_id = u.id AND DATE(mc.end_date) BETWEEN :month_start2 AND :month_end2) AS average_cycle_duration_days,
                 
                (SELECT COUNT(*) FROM supervisor_attendance sa 
                 WHERE sa.supervisor_user_id = u.id AND sa.attendance_date BETWEEN :month_start2 AND :month_end2 AND sa.status = 'PRESENT') AS active_days_count,
                 
                (SELECT COUNT(*) FROM issues i 
                 WHERE i.created_by_user_id = u.id AND DATE(i.created_at) BETWEEN :month_start2 AND :month_end2) AS issues_raised_count
                 
            FROM users u
            {$whereClause}
            ORDER BY u.full_name ASC
        ";

        return $this->fetchAll($sql, $params);
    }

    public function getAdvertisementOperationsReport(string $monthStart, string $monthEnd, ?string $siteCategory = null): array {
        $params = [
            'month_start1' => $monthStart,
            'month_end1' => $monthEnd,
            'month_start2' => $monthStart,
            'month_end2' => $monthEnd
        ];
        
        $where = [];
        if ($siteCategory) {
            $where[] = "s.site_category = :site_category";
            $params['site_category'] = $siteCategory;
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
                 AND c.start_date <= :month_end1 
                 AND (cs.linked_to_date IS NULL OR cs.linked_to_date >= :month_start1)) AS active_campaigns_count,
                 
                (SELECT COUNT(*) FROM campaign_sites cs 
                 JOIN campaigns c ON cs.campaign_id = c.id 
                 WHERE cs.site_id = s.id 
                 AND c.status = 'COMPLETED' 
                 AND c.actual_end_date BETWEEN :month_start1 AND :month_end1) AS completed_campaigns_count,
                 
                (SELECT COUNT(*) FROM tasks t 
                 WHERE t.site_id = s.id 
                 AND t.vertical_type = 'ADVERTISEMENT' 
                 AND t.task_category IN ('INSTALLATION', 'REMOVAL') 
                 AND t.status IN ('COMPLETED', 'ARCHIVED') 
                 AND DATE(t.actual_close_date) BETWEEN :month_start1 AND :month_end1) AS installations_completed_count,
                 
                (SELECT COUNT(*) FROM tasks t 
                 WHERE t.site_id = s.id 
                 AND t.vertical_type = 'ADVERTISEMENT' 
                 AND t.task_category IN ('MAINTENANCE', 'REPAIR') 
                 AND t.status IN ('COMPLETED', 'ARCHIVED') 
                 AND DATE(t.actual_close_date) BETWEEN :month_start1 AND :month_end1) AS maintenance_tasks_completed_count,
                 
                (SELECT COUNT(*) FROM issues i 
                 WHERE i.site_id = s.id AND i.status IN ('OPEN', 'IN_PROGRESS')) AS open_issues_count,
                 
                (SELECT COUNT(*) FROM site_monitoring_due_dates sm
                 WHERE sm.site_id = s.id AND sm.due_date BETWEEN :month_start1 AND :month_end1) AS monitoring_due_count,
                 
                (SELECT COUNT(*) FROM free_media_records fmr 
                 WHERE fmr.site_id = s.id AND DATE(fmr.created_at) BETWEEN :month_start1 AND :month_end1) AS free_media_added_count,
                 
                (SELECT CASE WHEN COUNT(*) > 0 THEN 1 ELSE 0 END FROM free_media_records fmr 
                 WHERE fmr.site_id = s.id AND fmr.status = 'CONFIRMED' 
                 AND (fmr.resolved_date IS NULL OR fmr.resolved_date > :month_end1)) AS free_media_active_flag
                 
            FROM sites s
            {$whereClause}
            ORDER BY s.site_code ASC
        ";

        return $this->fetchAll($sql, $params);
    }
}

