<?php

require_once __DIR__ . '/../helpers/Response.php';
require_once __DIR__ . '/../services/LabourService.php';

/**
 * LabourController
 *
 * Architecture: HTTP shape only. Role enforcement is in AuthMiddleware.
 * Same-day constraints, belt scope, and Ops override rules live in LabourService.
 */
class LabourController extends BaseController
{
    private LabourService $labourService;

    public function __construct()
    {
        $this->labourService = new LabourService();
    }

    /**
     * GET labour/list
     */
    public function listLabourEntries(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            Response::error('Method not allowed', 405);
            return;
        }

        try {
            $filters = [
                'date'    => $_GET['date'] ?? null,
                'belt_id' => $_GET['belt_id'] ?? null,
            ];

            $items = $this->labourService->listLabourEntries($filters);

            Response::success([
                'items'      => $items,
                'pagination' => ['page' => 1, 'limit' => count($items), 'total' => count($items)],
            ]);
        } catch (Throwable $e) {
            Response::error($e->getMessage(), 400);
        }
    }

    /**
     * POST labour/mark
     */
    public function markLabourCounts(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            Response::error('Method not allowed', 405);
            return;
        }

        $input = $this->getInput();

        if (empty($input['belt_id']) || empty($input['entry_date'])) {
            Response::error('Missing required fields: belt_id, entry_date', 400);
            return;
        }

        try {
            $result = $this->labourService->markLabourCounts(
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
