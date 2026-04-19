<?php

require_once __DIR__ . '/../helpers/Response.php';
require_once __DIR__ . '/../services/LabourService.php';

class LabourController
{
    private LabourService $labourService;

    public function __construct()
    {
        $this->labourService = new LabourService();
    }

    /**
     * GET labour/list
     * Query params: date, belt_id
     */
    public function listLabourEntries(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            Response::error('Method not allowed', 405);
            return;
        }

        try {
            $actorRoleKey = $_SESSION['role_key'] ?? '';

            if (!in_array($actorRoleKey, ['OPS_MANAGER', 'HEAD_SUPERVISOR'])) {
                Response::error('Forbidden', 403);
                return;
            }

            $filters = [
                'date' => $_GET['date'] ?? null,
                'belt_id' => $_GET['belt_id'] ?? null,
            ];

            $items = $this->labourService->listLabourEntries($filters);

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
     * POST labour/mark
     * JSON body: belt_id, entry_date, labour_count, gardener_count, night_guard_count, override_reason
     */
    public function markLabourCounts(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            Response::error('Method not allowed', 405);
            return;
        }

        $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

        if (empty($input['belt_id']) || empty($input['entry_date'])) {
            Response::error('Missing required fields: belt_id, entry_date', 400);
            return;
        }

        $actorUserId = (int) $_SESSION['user_id'];
        $actorRoleKey = $_SESSION['role_key'] ?? '';

        if (!in_array($actorRoleKey, ['OPS_MANAGER', 'HEAD_SUPERVISOR'])) {
            Response::error('Forbidden', 403);
            return;
        }

        try {
            $result = $this->labourService->markLabourCounts($input, $actorUserId, $actorRoleKey);
            Response::success($result);
        } catch (DomainException $e) {
            Response::error($e->getMessage(), 403);
        } catch (Throwable $e) {
            Response::error($e->getMessage(), 400);
        }
    }
}
