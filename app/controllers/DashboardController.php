<?php

require_once __DIR__ . '/../helpers/Response.php';
require_once __DIR__ . '/../services/DashboardService.php';
require_once __DIR__ . '/../../config/constants.php';

class DashboardController extends BaseController
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

        try {
            $summary = $this->dashboardService->getGreenBeltSummary();
            Response::success($summary);
        } catch (Throwable $e) {
            Response::error($e->getMessage(), 400);
        }
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

        try {
            $summary = $this->dashboardService->getAdvertisementSummary();
            Response::success($summary);
        } catch (Throwable $e) {
            Response::error($e->getMessage(), 400);
        }
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

        try {
            $summary = $this->dashboardService->getMonitoringSummary();
            Response::success($summary);
        } catch (Throwable $e) {
            Response::error($e->getMessage(), 400);
        }
    }
}
