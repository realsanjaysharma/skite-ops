<?php

require_once __DIR__ . '/../helpers/Response.php';
require_once __DIR__ . '/../services/DashboardService.php';
require_once __DIR__ . '/../../config/constants.php';

class DashboardController
{
    private DashboardService $dashboardService;

    public function __construct()
    {
        $this->dashboardService = new DashboardService();
    }

    /**
     * GET dashboard/master
     */
    public function master(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            Response::error('Method not allowed', 405);
            return;
        }

        // Only allow Ops Manager
        if (empty($_SESSION['user_id']) || ($_SESSION['role_key'] ?? '') !== 'OPS_MANAGER') {
            Response::error('Access denied', 403);
            return;
        }

        try {
            $summary = $this->dashboardService->getMasterOpsSummary();
            Response::success($summary);
        } catch (Throwable $e) {
            Response::error($e->getMessage(), 400);
        }
    }

    /**
     * GET dashboard/management
     */
    public function management(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            Response::error('Method not allowed', 405);
            return;
        }

        // Only allow Management
        if (empty($_SESSION['user_id']) || ($_SESSION['role_key'] ?? '') !== 'MANAGEMENT') {
            Response::error('Access denied', 403);
            return;
        }

        try {
            $summary = $this->dashboardService->getManagementSummary();
            Response::success($summary);
        } catch (Throwable $e) {
            Response::error($e->getMessage(), 400);
        }
    }

    /**
     * GET dashboard/green-belt
     * Auth: Ops, Head Supervisor
     */
    public function greenBelt(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            Response::error('Method not allowed', 405);
            return;
        }

        $allowed = ['OPS_MANAGER', 'HEAD_SUPERVISOR'];
        if (!in_array($_SESSION['role_key'] ?? '', $allowed, true)) {
            Response::error('Access denied', 403);
            return;
        }

        // Stub: returns overview of green belt activity grouped by zone/supervisor
        Response::success([
            'items' => [],
            'pagination' => ['page' => 1, 'limit' => 50, 'total' => 0],
            '_note' => 'Green belt dashboard aggregation — implementation pending'
        ]);
    }

    /**
     * GET dashboard/advertisement
     * Auth: Ops, Management
     */
    public function advertisement(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            Response::error('Method not allowed', 405);
            return;
        }

        $allowed = ['OPS_MANAGER', 'MANAGEMENT'];
        if (!in_array($_SESSION['role_key'] ?? '', $allowed, true)) {
            Response::error('Access denied', 403);
            return;
        }

        Response::success([
            'active_campaigns' => 0,
            'total_sites' => 0,
            'sites_with_monitoring_overdue' => 0,
            '_note' => 'Advertisement dashboard aggregation — implementation pending'
        ]);
    }

    /**
     * GET dashboard/monitoring
     * Auth: Ops, Management
     */
    public function monitoring(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            Response::error('Method not allowed', 405);
            return;
        }

        $allowed = ['OPS_MANAGER', 'MANAGEMENT'];
        if (!in_array($_SESSION['role_key'] ?? '', $allowed, true)) {
            Response::error('Access denied', 403);
            return;
        }

        Response::success([
            'sites_monitored_this_month' => 0,
            'sites_overdue' => 0,
            'total_monitoring_uploads' => 0,
            '_note' => 'Monitoring dashboard aggregation — implementation pending'
        ]);
    }
}
