<?php

require_once __DIR__ . '/../helpers/Response.php';
require_once __DIR__ . '/../services/WorkerEntryService.php';

class WorkerEntryController
{
    private WorkerEntryService $workerEntryService;

    public function __construct()
    {
        $this->workerEntryService = new WorkerEntryService();
    }

    /**
     * GET workday/list
     */
    public function listEntries(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            Response::error('Method not allowed', 405);
            return;
        }

        try {
            $actorRoleKey = $_SESSION['role_key'] ?? '';

            $filters = [
                'worker_id' => isset($_GET['worker_id']) ? (int) $_GET['worker_id'] : null,
                'entry_date' => $_GET['entry_date'] ?? null,
                'activity_type' => $_GET['activity_type'] ?? null
            ];

            $items = $this->workerEntryService->listEntries($filters, $actorRoleKey);

            Response::success([
                'items' => $items,
                'pagination' => [
                    'page' => 1,
                    'limit' => count($items),
                    'total' => count($items)
                ]
            ]);
        } catch (DomainException $e) {
            Response::error($e->getMessage(), 403);
        } catch (Throwable $e) {
            Response::error($e->getMessage(), 400);
        }
    }

    /**
     * POST workday/mark
     */
    public function markEntry(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            Response::error('Method not allowed', 405);
            return;
        }

        $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        $actorRoleKey = $_SESSION['role_key'] ?? '';
        $actorUserId = $_SESSION['user_id'] ?? 0;

        try {
            // Append creator identity natively in the controller layer
            $data = [
                'worker_id' => isset($input['worker_id']) ? (int) $input['worker_id'] : null,
                'entry_date' => $input['entry_date'] ?? null,
                'attendance_status' => $input['attendance_status'] ?? null,
                'activity_type' => $input['activity_type'] ?? null,
                'task_id' => isset($input['task_id']) ? (int) $input['task_id'] : null,
                'site_id' => isset($input['site_id']) ? (int) $input['site_id'] : null,
                'work_plan' => $input['work_plan'] ?? null,
                'work_update' => $input['work_update'] ?? null,
                'created_by_user_id' => $actorUserId
            ];

            $result = $this->workerEntryService->markEntry($data, $actorRoleKey);
            Response::success($result);
        } catch (InvalidArgumentException $e) {
            Response::error($e->getMessage(), 400);
        } catch (DomainException $e) {
            Response::error($e->getMessage(), 403);
        } catch (Throwable $e) {
            Response::error($e->getMessage(), 400);
        }
    }
}
