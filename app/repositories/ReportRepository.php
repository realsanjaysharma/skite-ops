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
}
