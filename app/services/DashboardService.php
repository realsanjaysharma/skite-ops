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
            'open_issues' => 0,
            'pending_requests' => 0,
            'active_tasks' => 0,
            'pending_uploads_for_review' => 0,
        ];

        // Active Sites
        $stmt = $this->db->query("SELECT COUNT(*) FROM sites WHERE is_active = 1");
        $summary['total_active_sites'] = (int) $stmt->fetchColumn();

        // Active Belts
        $stmt = $this->db->query("SELECT COUNT(*) FROM green_belts WHERE is_hidden = 0 AND permission_status = 'AGREEMENT_SIGNED'");
        $summary['total_active_belts'] = (int) $stmt->fetchColumn();

        // Open Issues
        $stmt = $this->db->query("SELECT COUNT(*) FROM issues WHERE status IN ('OPEN', 'IN_PROGRESS')");
        $summary['open_issues'] = (int) $stmt->fetchColumn();

        // Pending task requests
        $stmt = $this->db->query("SELECT COUNT(*) FROM task_requests WHERE status = 'SUBMITTED'");
        $summary['pending_requests'] = (int) $stmt->fetchColumn();

        // Active tasks
        $stmt = $this->db->query("SELECT COUNT(*) FROM tasks WHERE status = 'IN_PROGRESS'");
        $summary['active_tasks'] = (int) $stmt->fetchColumn();

        // Pending Uploads (Review Needed)
        $stmt = $this->db->query("SELECT COUNT(*) FROM uploads WHERE authority_visibility = 'HIDDEN' AND is_deleted = 0 AND is_purged = 0 AND reviewed_at IS NULL AND upload_type = 'WORK'");
        $summary['pending_uploads_for_review'] = (int) $stmt->fetchColumn();

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

        $stmt = $this->db->query("SELECT COUNT(*) FROM issues WHERE status = 'CLOSED' AND MONTH(resolved_at) = MONTH(CURRENT_DATE()) AND YEAR(resolved_at) = YEAR(CURRENT_DATE())");
        $summary['resolved_issues_this_month'] = (int) $stmt->fetchColumn();

        return $summary;
    }
}
