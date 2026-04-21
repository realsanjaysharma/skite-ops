<?php

require_once __DIR__ . '/../helpers/Response.php';
require_once __DIR__ . '/../services/TaskService.php';

class TaskController
{
    private TaskService $taskService;

    public function __construct()
    {
        $this->taskService = new TaskService();
    }

    /**
     * POST task/create
     */
    public function createTask(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            Response::error('Method not allowed', 405);
            return;
        }

        $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        $actorUserId = (int) $_SESSION['user_id'];
        $actorRoleKey = $_SESSION['role_key'] ?? '';

        if ($actorRoleKey !== 'OPS_MANAGER') {
            Response::error('Forbidden', 403);
            return;
        }

        try {
            $result = $this->taskService->createTask($input, $actorUserId, $actorRoleKey);
            Response::success($result);
        } catch (DomainException $e) {
            Response::error($e->getMessage(), 403);
        } catch (Throwable $e) {
            Response::error($e->getMessage(), 400);
        }
    }

    /**
     * GET task/list
     */
    public function listTasks(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            Response::error('Method not allowed', 405);
            return;
        }

        try {
            $actorUserId = (int) $_SESSION['user_id'];
            $actorRoleKey = $_SESSION['role_key'] ?? '';

            $filters = [
                'status' => $_GET['status'] ?? null,
                'priority' => $_GET['priority'] ?? null,
                'vertical_type' => $_GET['vertical_type'] ?? null,
                'assigned_lead_user_id' => $_GET['assigned_lead_user_id'] ?? null,
            ];

            $items = $this->taskService->listTasks($filters, $actorUserId, $actorRoleKey);

            Response::success([
                'items' => $items,
                'pagination' => [
                    'page' => 1,
                    'limit' => count($items),
                    'total' => count($items)
                ]
            ]);
        } catch (Throwable $e) {
            Response::error($e->getMessage(), 400);
        }
    }

    /**
     * GET task/get
     */
    public function getTask(): void
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
            $actorUserId = (int) $_SESSION['user_id'];
            $actorRoleKey = $_SESSION['role_key'] ?? '';

            $task = $this->taskService->getTask((int) $_GET['task_id'], $actorUserId, $actorRoleKey);
            
            if (!$task) {
                Response::error('Task not found', 404);
                return;
            }

            Response::success($task);
            
        } catch (Throwable $e) {
            Response::error($e->getMessage(), 400);
        }
    }

    /**
     * POST task/update
     */
    public function updateTask(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            Response::error('Method not allowed', 405);
            return;
        }

        $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        
        if (empty($input['task_id'])) {
            Response::error('Missing task_id param', 400);
            return;
        }

        $actorRoleKey = $_SESSION['role_key'] ?? '';

        try {
            $result = $this->taskService->updateTask($input, $actorRoleKey);
            Response::success($result);
        } catch (DomainException $e) {
            Response::error($e->getMessage(), 403);
        } catch (Throwable $e) {
            Response::error($e->getMessage(), 400);
        }
    }

    /**
     * POST task/archive
     */
    public function archiveTask(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            Response::error('Method not allowed', 405);
            return;
        }

        $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        
        if (empty($input['task_id'])) {
            Response::error('Missing task_id param', 400);
            return;
        }

        $actorRoleKey = $_SESSION['role_key'] ?? '';

        try {
            $result = $this->taskService->archiveTask((int) $input['task_id'], $actorRoleKey);
            Response::success($result);
        } catch (DomainException $e) {
            Response::error($e->getMessage(), 403);
        } catch (Throwable $e) {
            Response::error($e->getMessage(), 400);
        }
    }

    /**
     * POST task/work-done
     */
    public function markWorkDone(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            Response::error('Method not allowed', 405);
            return;
        }

        $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        
        if (empty($input['task_id'])) {
            Response::error('Missing task_id param', 400);
            return;
        }

        $actorUserId = (int) $_SESSION['user_id'];
        $actorRoleKey = $_SESSION['role_key'] ?? '';

        try {
            $result = $this->taskService->markWorkDone($input, $actorUserId, $actorRoleKey);
            Response::success($result);
        } catch (DomainException $e) {
            Response::error($e->getMessage(), 403);
        } catch (Throwable $e) {
            Response::error($e->getMessage(), 400);
        }
    }

    /**
     * GET task/my
     * Landing route for FABRICATION_LEAD — returns their assigned task list.
     */
    public function myTasks(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            Response::error('Method not allowed', 405);
            return;
        }

        $actorUserId = (int) $_SESSION['user_id'];
        $actorRoleKey = $_SESSION['role_key'] ?? '';

        if ($actorRoleKey !== 'FABRICATION_LEAD') {
            Response::error('Access denied', 403);
            return;
        }

        try {
            $filters = [
                'status'                => $_GET['status'] ?? null,
                'assigned_lead_user_id' => $actorUserId,
            ];

            $items = $this->taskService->listTasks($filters, $actorUserId, $actorRoleKey);

            Response::success([
                'items'      => $items,
                'pagination' => [
                    'page'  => 1,
                    'limit' => count($items),
                    'total' => count($items)
                ]
            ]);
        } catch (Throwable $e) {
            Response::error($e->getMessage(), 400);
        }
    }
}
