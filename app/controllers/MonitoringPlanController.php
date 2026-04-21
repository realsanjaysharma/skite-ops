<?php

require_once __DIR__ . '/../services/MonitoringPlanService.php';
require_once __DIR__ . '/../helpers/Response.php';

class MonitoringPlanController {
    public function listPlan(): void {
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            Response::error('Method not allowed', 405);
            return;
        }

        try {
            $service = new MonitoringPlanService();
            $result = $service->listPlan($_GET);
            Response::success($result);
        } catch (Throwable $e) {
            Response::error($e->getMessage(), 400);
        }
    }

    public function savePlan(): void {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            Response::error('Method not allowed', 405);
            return;
        }

        $input = json_decode(file_get_contents('php://input'), true) ?? [];

        try {
            $service = new MonitoringPlanService();
            $actorId = $_SESSION['user_id'];
            $service->savePlan($input, $actorId);
            Response::success(['success' => true]);
        } catch (Throwable $e) {
            Response::error($e->getMessage(), 400);
        }
    }

    public function copyNextMonth(): void {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            Response::error('Method not allowed', 405);
            return;
        }

        $input = json_decode(file_get_contents('php://input'), true) ?? [];

        try {
            $service = new MonitoringPlanService();
            $actorId = $_SESSION['user_id'];
            $service->copyNextMonth($input, $actorId);
            Response::success(['success' => true]);
        } catch (Throwable $e) {
            Response::error($e->getMessage(), 400);
        }
    }

    public function bulkCopy(): void {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            Response::error('Method not allowed', 405);
            return;
        }

        $input = json_decode(file_get_contents('php://input'), true) ?? [];

        try {
            $service = new MonitoringPlanService();
            $actorId = $_SESSION['user_id'];
            $service->bulkCopy($input, $actorId);
            Response::success(['success' => true]);
        } catch (Throwable $e) {
            Response::error($e->getMessage(), 400);
        }
    }
}
