<?php

/**
 * DashboardService
 *
 * Purpose:
 * Centralized dashboard aggregations for Landing Pages.
 */

require_once __DIR__ . '/../../config/database.php';

class DashboardService
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    /**
     * Master Ops Dashboard Aggregation
     */
    public function getMasterOpsSummary(): array
    {
        $summary = [
            'total_active_sites' => 0,
            'total_active_belts' => 0,
            'open_issue_count' => 0,
            'pending_requests' => 0,
            'open_task_count' => 0,
            'pending_uploads_for_review' => 0,
            'campaign_ending_soon_count' => 0,
            'free_media_active_count' => 0,
            'monitoring_due_today_count' => 0,
        ];

        // Active Sites
        $summary['total_active_sites'] = (int) $this->db->query("SELECT COUNT(*) FROM sites WHERE is_active = 1")->fetchColumn();

        // Active Belts
        $summary['total_active_belts'] = (int) $this->db->query("SELECT COUNT(*) FROM green_belts WHERE is_hidden = 0 AND permission_status = 'AGREEMENT_SIGNED'")->fetchColumn();

        // Open Issues
        $summary['open_issue_count'] = (int) $this->db->query("SELECT COUNT(*) FROM issues WHERE status IN ('OPEN', 'IN_PROGRESS')")->fetchColumn();

        // Pending task requests
        $summary['pending_requests'] = (int) $this->db->query("SELECT COUNT(*) FROM task_requests WHERE status = 'SUBMITTED'")->fetchColumn();

        // Open tasks (OPEN + RUNNING)
        $summary['open_task_count'] = (int) $this->db->query("SELECT COUNT(*) FROM tasks WHERE status IN ('OPEN', 'RUNNING')")->fetchColumn();

        // Pending Uploads (Review Needed)
        $summary['pending_uploads_for_review'] = (int) $this->db->query("SELECT COUNT(*) FROM uploads WHERE authority_visibility = 'HIDDEN' AND is_deleted = 0 AND is_purged = 0 AND reviewed_at IS NULL AND upload_type = 'WORK'")->fetchColumn();

        // campaigns ending soon (next 7 days)
        $summary['campaign_ending_soon_count'] = (int) $this->db->query("SELECT COUNT(*) FROM campaigns WHERE status = 'ACTIVE' AND expected_end_date >= CURDATE() AND expected_end_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)")->fetchColumn();

        // confirmed active free media
        $summary['free_media_active_count'] = (int) $this->db->query("SELECT COUNT(*) FROM free_media_records WHERE status = 'CONFIRMED_ACTIVE' AND (expiry_date IS NULL OR expiry_date >= CURDATE())")->fetchColumn();

        // monitoring pressure (due today with no qualifying completion)
        $summary['monitoring_due_today_count'] = (int) $this->db->query("
            SELECT COUNT(*) FROM site_monitoring_due_dates smdd
            WHERE due_date = CURDATE()
            AND NOT EXISTS (
                SELECT 1 FROM uploads u
                WHERE u.parent_type = 'SITE' AND u.parent_id = smdd.site_id
                AND DATE(u.created_at) = smdd.due_date
                AND u.is_deleted = 0 AND u.is_purged = 0
            )
        ")->fetchColumn();

        return $summary;
    }

    /**
     * Management Dashboard Aggregation (high level numbers)
     */
    public function getManagementSummary(): array
    {
        $summary = [
            'active_campaigns' => 0,
            'total_sites' => 0,
            'total_belts' => 0,
            'resolved_issues_this_month' => 0,
        ];

        $stmt = $this->db->query("SELECT COUNT(*) FROM campaigns WHERE status = 'ACTIVE'");
        $summary['active_campaigns'] = (int) $stmt->fetchColumn();

        $stmt = $this->db->query("SELECT COUNT(*) FROM sites");
        $summary['total_sites'] = (int) $stmt->fetchColumn();

        $stmt = $this->db->query("SELECT COUNT(*) FROM green_belts");
        $summary['total_belts'] = (int) $stmt->fetchColumn();

        $stmt = $this->db->query("SELECT COUNT(*) FROM issues WHERE status = 'CLOSED' AND MONTH(closed_at) = MONTH(CURRENT_DATE()) AND YEAR(closed_at) = YEAR(CURRENT_DATE())");
        $summary['resolved_issues_this_month'] = (int) $stmt->fetchColumn();

        return $summary;
    }

    public function getGreenBeltSummary(): array
    {
        $summary = [
            'total_belts' => 0,
            'active_belts' => 0,
            'open_issues' => 0,
            'active_cycle_count' => 0,
            'same_day_watering_pending_count' => 0,
            'pending_authority_review_count' => 0,
        ];
        $summary['total_belts'] = (int) $this->db->query("SELECT COUNT(*) FROM green_belts")->fetchColumn();
        $summary['active_belts'] = (int) $this->db->query("SELECT COUNT(*) FROM green_belts WHERE is_hidden = 0")->fetchColumn();
        $summary['open_issues'] = (int) $this->db->query("SELECT COUNT(*) FROM issues WHERE belt_id IS NOT NULL AND status IN ('OPEN', 'IN_PROGRESS')")->fetchColumn();
        
        $summary['active_cycle_count'] = (int) $this->db->query("SELECT COUNT(*) FROM maintenance_cycles WHERE end_date IS NULL")->fetchColumn();
        
        $summary['same_day_watering_pending_count'] = (int) $this->db->query("
            SELECT COUNT(*) FROM green_belts gb
            WHERE gb.maintenance_mode = 'MAINTAINED' AND gb.is_hidden = 0
            AND NOT EXISTS (
                SELECT 1 FROM watering_records wr
                WHERE wr.belt_id = gb.id AND wr.watering_date = CURDATE()
            )
        ")->fetchColumn();

        $summary['pending_authority_review_count'] = (int) $this->db->query("
            SELECT COUNT(*) FROM uploads 
            WHERE parent_type = 'GREEN_BELT' 
            AND upload_type = 'WORK' 
            AND authority_visibility = 'HIDDEN' 
            AND is_deleted = 0 
            AND is_purged = 0
        ")->fetchColumn();

        return $summary;
    }

    public function getAdvertisementSummary(): array
    {
        $summary = [
            'active_campaigns' => 0,
            'total_sites' => 0,
            'sites_with_monitoring_overdue' => 0,
        ];
        $summary['active_campaigns'] = (int) $this->db->query("SELECT COUNT(*) FROM campaigns WHERE status = 'ACTIVE'")->fetchColumn();
        $summary['total_sites'] = (int) $this->db->query("SELECT COUNT(*) FROM sites")->fetchColumn();
        $summary['sites_with_monitoring_overdue'] = (int) $this->db->query("SELECT COUNT(*) FROM site_monitoring_due_dates WHERE due_date < CURRENT_DATE()")->fetchColumn();
        return $summary;
    }

    public function getMonitoringSummary(): array
    {
        $summary = [
            'due_today_count' => 0,
            'completed_today_count' => 0,
            'overdue_due_date_count' => 0,
            'discovery_mode_count' => 0,
        ];

        // due_today_count: due today with no qualifying upload
        $summary['due_today_count'] = (int) $this->db->query("
            SELECT COUNT(*) FROM site_monitoring_due_dates smdd
            WHERE due_date = CURDATE()
            AND NOT EXISTS (
                SELECT 1 FROM uploads u
                WHERE u.parent_type = 'SITE' AND u.parent_id = smdd.site_id
                AND DATE(u.created_at) = smdd.due_date
                AND u.is_deleted = 0 AND u.is_purged = 0
            )
        ")->fetchColumn();

        // completed_today_count: due today WITH qualifying upload
        $summary['completed_today_count'] = (int) $this->db->query("
            SELECT COUNT(*) FROM site_monitoring_due_dates smdd
            WHERE due_date = CURDATE()
            AND EXISTS (
                SELECT 1 FROM uploads u
                WHERE u.parent_type = 'SITE' AND u.parent_id = smdd.site_id
                AND DATE(u.created_at) = smdd.due_date
                AND u.is_deleted = 0 AND u.is_purged = 0
            )
        ")->fetchColumn();

        // overdue_count: past due with no qualifying upload
        $summary['overdue_due_date_count'] = (int) $this->db->query("
            SELECT COUNT(*) FROM site_monitoring_due_dates smdd
            WHERE due_date < CURDATE()
            AND NOT EXISTS (
                SELECT 1 FROM uploads u
                WHERE u.parent_type = 'SITE' AND u.parent_id = smdd.site_id
                AND DATE(u.created_at) = smdd.due_date
                AND u.is_deleted = 0 AND u.is_purged = 0
            )
        ")->fetchColumn();

        // discovery_mode_count: uploads with discovery_mode = 1 today
        $summary['discovery_mode_count'] = (int) $this->db->query("
            SELECT COUNT(*) FROM uploads 
            WHERE is_discovery_mode = 1 
            AND DATE(created_at) = CURDATE()
            AND is_deleted = 0 AND is_purged = 0
        ")->fetchColumn();

        return $summary;
    }

    /**
     * Alert Panel — aggregated attention items for OPS_MANAGER.
     *
     * Returns six alert categories, each as an array of minimal context objects.
     */
    public function getAlertSummary(): array
    {
        $today = date('Y-m-d');
        $plus7 = date('Y-m-d', strtotime('+7 days'));

        // 1. Permission expiry warnings — belts with permission_end_date in next 7 days
        $expiryWarnings = $this->db->prepare("
            SELECT id, belt_code, common_name AS name,
                   permission_end_date AS expiry_date,
                   DATEDIFF(permission_end_date, CURDATE()) AS days_remaining
            FROM green_belts
            WHERE permission_status = 'AGREEMENT_SIGNED'
              AND permission_end_date BETWEEN CURDATE() AND :plus7
              AND is_hidden = 0
            ORDER BY permission_end_date ASC
            LIMIT 20
        ");
        $expiryWarnings->execute([':plus7' => $plus7]);
        $expiryWarnings = $expiryWarnings->fetchAll(PDO::FETCH_ASSOC);

        // 2. Overdue monitoring — due dates in the past with no qualifying upload that day
        $overdueMonitoring = $this->db->query("
            SELECT s.id, s.site_code, s.location_text AS name,
                   d.due_date,
                   DATEDIFF(CURDATE(), d.due_date) AS days_overdue
            FROM site_monitoring_due_dates d
            INNER JOIN sites s ON s.id = d.site_id
            WHERE d.due_date < CURDATE()
              AND NOT EXISTS (
                SELECT 1 FROM uploads u
                WHERE u.parent_type = 'SITE'
                  AND u.parent_id = d.site_id
                  AND DATE(u.created_at) = d.due_date
                  AND u.upload_type = 'WORK'
                  AND u.is_deleted = 0
                  AND u.is_purged = 0
              )
            ORDER BY d.due_date ASC
            LIMIT 20
        ")->fetchAll(PDO::FETCH_ASSOC);

        // 3. Long-running cycles — active cycles older than 4 days with no end_date
        $cyclesOverdue = $this->db->query("
            SELECT mc.id, gb.belt_code, gb.common_name AS name,
                   mc.start_date,
                   DATEDIFF(CURDATE(), mc.start_date) AS days_open
            FROM maintenance_cycles mc
            INNER JOIN green_belts gb ON gb.id = mc.belt_id
            WHERE mc.end_date IS NULL
              AND mc.start_date < DATE_SUB(CURDATE(), INTERVAL 4 DAY)
            ORDER BY mc.start_date ASC
            LIMIT 20
        ")->fetchAll(PDO::FETCH_ASSOC);

        // 4. Attendance missing today — supervisors with no attendance row for today
        $attendanceMissingToday = $this->db->query("
            SELECT u.id, u.full_name AS name
            FROM users u
            INNER JOIN roles r ON r.id = u.role_id
            WHERE r.role_key = 'GREEN_BELT_SUPERVISOR'
              AND u.is_active = 1
              AND NOT EXISTS (
                SELECT 1 FROM supervisor_attendance sa
                WHERE sa.supervisor_user_id = u.id
                  AND sa.attendance_date = CURDATE()
              )
            ORDER BY u.full_name ASC
            LIMIT 20
        ")->fetchAll(PDO::FETCH_ASSOC);

        // 5. High priority tasks — OPEN or RUNNING, HIGH or CRITICAL
        $highPriorityTasks = $this->db->query("
            SELECT t.id, t.work_description AS name,
                   t.priority, t.status,
                   u.full_name AS assigned_lead_name
            FROM tasks t
            LEFT JOIN users u ON u.id = t.assigned_lead_user_id
            WHERE t.priority IN ('HIGH', 'CRITICAL')
              AND t.status IN ('OPEN', 'RUNNING')
            ORDER BY FIELD(t.priority,'CRITICAL','HIGH'), t.id ASC
            LIMIT 20
        ")->fetchAll(PDO::FETCH_ASSOC);

        // 6. Campaigns ended but free media not confirmed
        $campaignEndPending = $this->db->query("
            SELECT c.id, c.campaign_name AS name,
                   c.actual_end_date AS end_date
            FROM campaigns c
            WHERE c.status = 'ENDED'
              AND NOT EXISTS (
                SELECT 1 FROM free_media_records fm
                WHERE fm.source_reference_id = c.id
                  AND fm.source_type = 'CAMPAIGN_END'
              )
            ORDER BY c.actual_end_date DESC
            LIMIT 20
        ")->fetchAll(PDO::FETCH_ASSOC);

        return [
            'expiry_warnings'          => $expiryWarnings,
            'overdue_monitoring'       => $overdueMonitoring,
            'cycles_overdue'           => $cyclesOverdue,
            'attendance_missing_today' => $attendanceMissingToday,
            'high_priority_tasks'      => $highPriorityTasks,
            'campaign_end_pending'     => $campaignEndPending,
        ];
    }
}
