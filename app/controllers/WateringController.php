<?php

require_once __DIR__ . '/../helpers/Response.php';
require_once __DIR__ . '/../services/WateringService.php';

/**
 * WateringController
 *
 * Architecture: HTTP shape only. Role enforcement is in AuthMiddleware.
 * Same-day rules, belt assignment scope, and override paths live in WateringService.
 */
class WateringController extends BaseController
{
    private WateringService $wateringService;

    public function __construct()
    {
        $this->wateringService = new WateringService();
    }

    /**
     * GET watering/list
     */
    public function listWateringRecords(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            Response::error('Method not allowed', 405);
            return;
        }

        try {
            $filters = [
                'date'               => $_GET['date'] ?? date('Y-m-d'),
                'belt_id'            => $_GET['belt_id'] ?? null,
                'supervisor_user_id' => $_GET['supervisor_user_id'] ?? null,
            ];

            $items = $this->wateringService->listWateringRecords(
                $filters,
                (int) $_SESSION['user_id'],
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
     * POST watering/mark
     */
    public function markWatering(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            Response::error('Method not allowed', 405);
            return;
        }

        $input = $this->getInput();

        if (empty($input['belt_id']) || empty($input['watering_date']) || empty($input['status'])) {
            Response::error('Missing required fields: belt_id, watering_date, status', 400);
            return;
        }

        try {
            $result = $this->wateringService->markWatering(
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
