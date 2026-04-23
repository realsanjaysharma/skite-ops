<?php

require_once __DIR__ . '/../helpers/Response.php';
require_once __DIR__ . '/../services/WorkerService.php';

/**
 * WorkerController
 *
 * Architecture: HTTP shape only. Role enforcement is in AuthMiddleware.
 * Ops-only create/update rules live in WorkerService.
 */
class WorkerController
{
    private WorkerService $workerService;

    public function __construct()
    {
        $this->workerService = new WorkerService();
    }

    /**
     * GET worker/list
     */
    public function listWorkers(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            Response::error('Method not allowed', 405);
            return;
        }

        try {
            $filters = [
                'is_active' => isset($_GET['is_active']) ? (int) $_GET['is_active'] : null,
                'skill_tag' => $_GET['skill_tag'] ?? null,
            ];

            $items = $this->workerService->listWorkers(
                $filters,
                (string) ($_SESSION['role_key'] ?? '')
            );

            Response::success([
                'items'      => $items,
                'pagination' => ['page' => 1, 'limit' => count($items), 'total' => count($items)],
            ]);
        } catch (Throwable $e) {
            Response::error($e->getMessage(), 400);
        }
    }

    /**
     * GET worker/get
     */
    public function getWorker(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            Response::error('Method not allowed', 405);
            return;
        }

        if (empty($_GET['worker_id'])) {
            Response::error('Missing worker_id param', 400);
            return;
        }

        try {
            $worker = $this->workerService->getWorker(
                (int) $_GET['worker_id'],
                (string) ($_SESSION['role_key'] ?? '')
            );

            if (!$worker) {
                Response::error('Worker not found', 404);
                return;
            }

            Response::success($worker);
        } catch (Throwable $e) {
            Response::error($e->getMessage(), 400);
        }
    }

    /**
     * POST worker/create
     */
    public function createWorker(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            Response::error('Method not allowed', 405);
            return;
        }

        $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

        try {
            $result = $this->workerService->createWorker(
                $input,
                (string) ($_SESSION['role_key'] ?? '')
            );
            Response::success($result);
        } catch (DomainException $e) {
            Response::error($e->getMessage(), 403);
        } catch (Throwable $e) {
            Response::error($e->getMessage(), 400);
        }
    }

    /**
     * POST worker/update
     */
    public function updateWorker(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            Response::error('Method not allowed', 405);
            return;
        }

        $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

        if (empty($input['worker_id'])) {
            Response::error('Missing worker_id param', 400);
            return;
        }

        try {
            $result = $this->workerService->updateWorker(
                $input,
                (string) ($_SESSION['role_key'] ?? '')
            );
            Response::success($result);
        } catch (DomainException $e) {
            Response::error($e->getMessage(), 403);
        } catch (Throwable $e) {
            Response::error($e->getMessage(), 400);
        }
    }

    /**
     * GET worker/availability
     */
    public function getAvailability(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            Response::error('Method not allowed', 405);
            return;
        }

        try {
            $result = $this->workerService->getWorkerAvailability(
                $_GET['date'] ?? date('Y-m-d'),
                $_GET['skill_tag'] ?? null,
                (string) ($_SESSION['role_key'] ?? '')
            );
            Response::success($result);
        } catch (Throwable $e) {
            Response::error($e->getMessage(), 400);
        }
    }
}
