<?php

require_once __DIR__ . '/../services/ReportService.php';
require_once __DIR__ . '/../helpers/Response.php';

class ReportController
{
    private $reportService;

    public function __construct()
    {
        $this->reportService = new ReportService();
    }

    /**
     * GET report/worker-activity
     * 
     * Auth: Ops, Management
     * Query Params: month, worker_id, worker_skill_tag, format
     */
    public function getWorkerActivity()
    {
        // Require specific roles as per the contract (Ops and Management)
        if (!in_array($_SESSION['role_key'] ?? '', ['OPS_MANAGER', 'MANAGEMENT'])) {
            Response::json(false, [], 'Access denied', 403);
            return;
        }

        $month = $_GET['month'] ?? null;
        if (!$month) {
            Response::json(false, [], 'Missing required field: month', 400);
            return;
        }

        $workerId = isset($_GET['worker_id']) ? (int)$_GET['worker_id'] : null;
        $skillTag = $_GET['worker_skill_tag'] ?? null;
        $format = $_GET['format'] ?? null;

        try {
            $data = $this->reportService->getWorkerActivityReport($month, $workerId, $skillTag);

            if ($format === 'csv') {
                $filename = 'worker_activity_' . str_replace('-', '_', $month) . '.csv';
                $this->reportService->exportCsv($data, $filename);
                return;
            }

            Response::json(true, ['items' => $data]);
        } catch (InvalidArgumentException $e) {
            Response::json(false, [], $e->getMessage(), 400);
        } catch (Exception $e) {
            Response::json(false, [], 'An error occurred while generating the report', 500);
        }
    }
}
