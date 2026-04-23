<?php

require_once __DIR__ . '/../helpers/Response.php';
require_once __DIR__ . '/../services/TaskWorkerService.php';

/**
 * TaskWorkerController
 *
 * Architecture: HTTP shape only. Role enforcement is in AuthMiddleware.
 * Task ownership scope (lead must own the task) lives in TaskWorkerService.
 */
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

        try {
            $createdIds = $this->taskWorkerService->assignWorkers(
                $input,
                (string) ($_SESSION['role_key'] ?? ''),
                (int) $_SESSION['user_id']
            );
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

        try {
            $success = $this->taskWorkerService->releaseWorker(
                $input,
                (string) ($_SESSION['role_key'] ?? ''),
                (int) $_SESSION['user_id']
            );
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
