<?php

require_once __DIR__ . '/../services/MonitoringHistoryService.php';
require_once __DIR__ . '/../helpers/Response.php';

class MonitoringHistoryController extends BaseController {
    public function getHistory(): void {
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            Response::error('Method not allowed', 405);
            return;
        }

        try {
            $service = new MonitoringHistoryService();
            $result = $service->getHistory($_GET);
            Response::success($result);
        } catch (Throwable $e) {
            Response::error($e->getMessage(), 400);
        }
    }
}
