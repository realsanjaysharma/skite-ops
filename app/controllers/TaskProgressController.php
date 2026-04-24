<?php

require_once __DIR__ . '/../helpers/Response.php';
require_once __DIR__ . '/../services/TaskService.php';

/**
 * TaskProgressController
 *
 * Architecture: HTTP shape only. Role enforcement is in AuthMiddleware.
 * Commercial-role scope and lead-only mutation rules live in TaskService.
 */
class TaskProgressController extends BaseController
{
    private TaskService $taskService;

    public function __construct()
    {
        $this->taskService = new TaskService();
    }

    /**
     * GET taskprogress/list
     */
    public function listTaskProgress(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            Response::error('Method not allowed', 405);
            return;
        }

        try {
            $filters = [
                'status'      => $_GET['status'] ?? null,
                'client_name' => $_GET['client_name'] ?? null,
                'campaign_id' => $_GET['campaign_id'] ?? null,
                'site_id'     => $_GET['site_id'] ?? null,
                'date_from'   => $_GET['date_from'] ?? null,
                'date_to'     => $_GET['date_to'] ?? null,
            ];

            $items = $this->taskService->listTaskProgress(
                $filters,
                (int) $_SESSION['user_id'],
                (string) ($_SESSION['role_key'] ?? '')
            );

            Response::success([
                'items'      => $items,
                'pagination' => ['page' => 1, 'limit' => count($items), 'total' => count($items)],
            ]);
        } catch (DomainException $e) {
            Response::error($e->getMessage(), 403);
        } catch (Throwable $e) {
            Response::error($e->getMessage(), 400);
        }
    }

    /**
     * GET taskprogress/get
     */
    public function getTaskProgress(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            Response::error('Method not allowed', 405);
            return;
        }

        if (empty($_GET['task_id'])) {
            Response::error('Missing task_id param', 400);
            return;
        }

        try {
            $progress = $this->taskService->getTaskProgress(
                (int) $_GET['task_id'],
                (int) $_SESSION['user_id'],
                (string) ($_SESSION['role_key'] ?? '')
            );

            if (!$progress) {
                Response::error('Task progress not found or out of scope', 404);
                return;
            }

            Response::success($progress);
        } catch (DomainException $e) {
            Response::error($e->getMessage(), 403);
        } catch (Throwable $e) {
            Response::error($e->getMessage(), 400);
        }
    }

    /**
     * POST task/progress
     */
    public function updateProgress(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            Response::error('Method not allowed', 405);
            return;
        }

        $input = $this->getInput();

        if (empty($input['task_id'])) {
            Response::error('Missing task_id param', 400);
            return;
        }

        try {
            $result = $this->taskService->updateTaskProgress(
                $input,
                (int) $_SESSION['user_id'],
                (string) ($_SESSION['role_key'] ?? '')
            );
            Response::success($result);
        } catch (DomainException $e) {
            Response::error($e->getMessage(), 403);
        } catch (Throwable $e) {
            Response::error($e->getMessage(), 400);
        }
    }
}
