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
}
