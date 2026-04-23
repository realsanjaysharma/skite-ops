<?php

require_once __DIR__ . '/../services/ReportService.php';
require_once __DIR__ . '/../helpers/Response.php';

/**
 * ReportController
 *
 * Architecture: HTTP shape only. Role enforcement via AuthMiddleware (reports.monthly + read).
 */
class ReportController
{
    private ReportService $reportService;

    public function __construct()
    {
        $this->reportService = new ReportService();
    }

    public function getWorkerActivity(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') { Response::error('Method not allowed', 405); return; }
        $month = $_GET['month'] ?? null;
        if (!$month) { Response::error('Missing required field: month', 400); return; }
        try {
            $data = $this->reportService->getWorkerActivityReport($month, isset($_GET['worker_id']) ? (int)$_GET['worker_id'] : null, $_GET['worker_skill_tag'] ?? null);
            if (($_GET['format'] ?? null) === 'csv') { $this->reportService->exportCsv($data, 'worker_activity_' . str_replace('-', '_', $month) . '.csv'); return; }
            Response::success(['items' => $data]);
        } catch (InvalidArgumentException $e) { Response::error($e->getMessage(), 400);
        } catch (Throwable $e) { Response::error($e->getMessage(), 500); }
    }

    public function getBeltHealth(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') { Response::error('Method not allowed', 405); return; }
        $month = $_GET['month'] ?? null;
        if (!$month) { Response::error('Missing required field: month', 400); return; }
        try {
            $data = $this->reportService->getBeltHealthReport($month, isset($_GET['zone']) ? (int)$_GET['zone'] : null, isset($_GET['supervisor_user_id']) ? (int)$_GET['supervisor_user_id'] : null);
            if (($_GET['format'] ?? null) === 'csv') { $this->reportService->exportCsv($data, 'belt_health_summary_' . str_replace('-', '_', $month) . '.csv'); return; }
            Response::success(['items' => $data]);
        } catch (InvalidArgumentException $e) { Response::error($e->getMessage(), 400);
        } catch (Throwable $e) { Response::error($e->getMessage(), 500); }
    }

    public function getSupervisorActivity(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') { Response::error('Method not allowed', 405); return; }
        $month = $_GET['month'] ?? null;
        if (!$month) { Response::error('Missing required field: month', 400); return; }
        try {
            $data = $this->reportService->getSupervisorActivityReport($month, isset($_GET['supervisor_user_id']) ? (int)$_GET['supervisor_user_id'] : null);
            if (($_GET['format'] ?? null) === 'csv') { $this->reportService->exportCsv($data, 'supervisor_activity_' . str_replace('-', '_', $month) . '.csv'); return; }
            Response::success(['items' => $data]);
        } catch (InvalidArgumentException $e) { Response::error($e->getMessage(), 400);
        } catch (Throwable $e) { Response::error($e->getMessage(), 500); }
    }

    public function getAdvertisementOperations(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') { Response::error('Method not allowed', 405); return; }
        $month = $_GET['month'] ?? null;
        if (!$month) { Response::error('Missing required field: month', 400); return; }
        try {
            $data = $this->reportService->getAdvertisementOperationsReport($month, $_GET['site_category'] ?? null);
            if (($_GET['format'] ?? null) === 'csv') { $this->reportService->exportCsv($data, 'advertisement_operations_' . str_replace('-', '_', $month) . '.csv'); return; }
            Response::success(['items' => $data]);
        } catch (InvalidArgumentException $e) { Response::error($e->getMessage(), 400);
        } catch (Throwable $e) { Response::error($e->getMessage(), 500); }
    }
}
