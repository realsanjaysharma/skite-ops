<?php

require_once __DIR__ . '/../helpers/Response.php';
require_once __DIR__ . '/../services/TaskWorkerService.php';

class TaskWorkerController
{
    private TaskWorkerService $taskWorkerService;

    public function __construct()
    {
        $this->taskWorkerService = new TaskWorkerService();
    }

    /**
     * POST taskworker/assign
     */
    public function assignWorkers(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            Response::error('Method not allowed', 405);
            return;
        }

        $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        $actorRoleKey = $_SESSION['role_key'] ?? '';
        $actorUserId = $_SESSION['user_id'] ?? 0;

        try {
            $createdIds = $this->taskWorkerService->assignWorkers($input, $actorRoleKey, $actorUserId);
            Response::success(['assigned_ids' => $createdIds]);
        } catch (InvalidArgumentException $e) {
            Response::error($e->getMessage(), 400);
        } catch (DomainException $e) {
            Response::error($e->getMessage(), 403);
        } catch (Throwable $e) {
            Response::error($e->getMessage(), 400);
        }
    }

    /**
     * POST taskworker/release
     */
    public function releaseWorker(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            Response::error('Method not allowed', 405);
            return;
        }

        $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        $actorRoleKey = $_SESSION['role_key'] ?? '';
        $actorUserId = $_SESSION['user_id'] ?? 0;

        try {
            $success = $this->taskWorkerService->releaseWorker($input, $actorRoleKey, $actorUserId);
            Response::success(['released' => $success]);
        } catch (InvalidArgumentException $e) {
            Response::error($e->getMessage(), 400);
        } catch (DomainException $e) {
            Response::error($e->getMessage(), 403);
        } catch (Throwable $e) {
            Response::error($e->getMessage(), 400);
        }
    }
}
