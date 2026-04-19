<?php

require_once __DIR__ . '/../helpers/Response.php';
require_once __DIR__ . '/../services/WateringService.php';

class WateringController
{
    private WateringService $wateringService;

    public function __construct()
    {
        $this->wateringService = new WateringService();
    }

    /**
     * GET watering/list
     * Query params: date, belt_id, supervisor_user_id
     */
    public function listWateringRecords(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            Response::error('Method not allowed', 405);
            return;
        }

        try {
            $actorUserId = (int) $_SESSION['user_id'];
            $actorRoleKey = $_SESSION['role_key'] ?? '';

            if (!in_array($actorRoleKey, ['OPS_MANAGER', 'HEAD_SUPERVISOR', 'GREEN_BELT_SUPERVISOR'])) {
                Response::error('Forbidden', 403);
                return;
            }

            $filters = [
                'date' => $_GET['date'] ?? date('Y-m-d'),
                'belt_id' => $_GET['belt_id'] ?? null,
                'supervisor_user_id' => $_GET['supervisor_user_id'] ?? null,
            ];

            $items = $this->wateringService->listWateringRecords($filters, $actorUserId, $actorRoleKey);

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
     * POST watering/mark
     * JSON body: belt_id, watering_date, status, reason_text, override_reason
     */
    public function markWatering(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            Response::error('Method not allowed', 405);
            return;
        }

        $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

        if (empty($input['belt_id']) || empty($input['watering_date']) || empty($input['status'])) {
            Response::error('Missing required fields: belt_id, watering_date, status', 400);
            return;
        }

        $actorUserId = (int) $_SESSION['user_id'];
        $actorRoleKey = $_SESSION['role_key'] ?? '';

        if (!in_array($actorRoleKey, ['OPS_MANAGER', 'HEAD_SUPERVISOR', 'GREEN_BELT_SUPERVISOR'])) {
            Response::error('Forbidden', 403);
            return;
        }

        try {
            $result = $this->wateringService->markWatering($input, $actorUserId, $actorRoleKey);
            Response::success($result);
        } catch (DomainException $e) {
            Response::error($e->getMessage(), 403);
        } catch (Throwable $e) {
            Response::error($e->getMessage(), 400);
        }
    }
}
