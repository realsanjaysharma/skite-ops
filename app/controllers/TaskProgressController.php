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
        if (!$this->requireMethod('GET')) return;

        try {
            $filters = [
                'status'      => $_GET['status'] ?? null,
                'client_name' => $_GET['client_name'] ?? null,
                'campaign_id' => $_GET['campaign_id'] ?? null,
                'site_id'     => $_GET['site_id'] ?? null,
                'date_from'   => $_GET['date_from'] ?? null,
                'date_to'     => $_GET['date_to'] ?? null,
            ];

            $actor = $this->getActor();

            $items = $this->taskService->listTaskProgress(
                $filters,
                $actor['user_id'],
                $actor['role_key']
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
        if (!$this->requireMethod('GET')) return;

        if (empty($_GET['task_id'])) {
            Response::error('Missing task_id param', 400);
            return;
        }

        try {
            $actor = $this->getActor();

            $progress = $this->taskService->getTaskProgress(
                (int) $_GET['task_id'],
                $actor['user_id'],
                $actor['role_key']
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
        if (!$this->requireMethod('POST')) return;

        $input = $this->getInput();

        if (empty($input['task_id'])) {
            Response::error('Missing task_id param', 400);
            return;
        }

        try {
            $actor = $this->getActor();

            $result = $this->taskService->updateTaskProgress(
                $input,
                $actor['user_id'],
                $actor['role_key']
            );
            Response::success($result);
        } catch (DomainException $e) {
            Response::error($e->getMessage(), 403);
        } catch (Throwable $e) {
            Response::error($e->getMessage(), 400);
        }
    }
}
