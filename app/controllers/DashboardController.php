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
}
