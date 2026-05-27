<?php

require_once __DIR__ . '/BaseController.php';
require_once __DIR__ . '/../helpers/Response.php';
require_once __DIR__ . '/../services/BoardMonitoringService.php';

/**
 * BoardMonitoringController
 *
 * HTTP shape for board monitoring report submission and history.
 */
class BoardMonitoringController extends BaseController
{
    private BoardMonitoringService $service;

    public function __construct()
    {
        $this->service = new BoardMonitoringService();
    }

    /**
     * GET boardmonitoring/my-belts
     */
    public function myBelts(): void
    {
        if (!$this->requireMethod('GET')) return;

        $actor = $this->getActor();

        try {
            $belts = $this->service->getMyBelts($actor['user_id']);
            Response::success(['items' => $belts]);
        } catch (Throwable $e) {
            Response::error($e->getMessage(), 400);
        }
    }

    /**
     * POST boardmonitoring/submit
     */
    public function submit(): void
    {
        if (!$this->requireMethod('POST')) return;

        $actor = $this->getActor();
        $input = $this->getInput();

        try {
            $result = $this->service->submitReport($input, $_FILES, $actor['user_id']);
            Response::success($result);
        } catch (DomainException $e) {
            Response::error($e->getMessage(), 403);
        } catch (InvalidArgumentException $e) {
            Response::error($e->getMessage(), 400);
        } catch (Throwable $e) {
            Response::error($e->getMessage(), 500);
        }
    }

    /**
     * GET boardmonitoring/history
     */
    public function history(): void
    {
        if (!$this->requireMethod('GET')) return;

        $actor = $this->getActor();

        try {
            $filters = [
                'belt_id'   => $_GET['belt_id'] ?? null,
                'date_from' => $_GET['date_from'] ?? null,
                'date_to'   => $_GET['date_to'] ?? null,
                'status'    => $_GET['status'] ?? null,
            ];

            $page  = max(1, (int) ($_GET['page'] ?? 1));
            $limit = max(1, min(50, (int) ($_GET['limit'] ?? 20)));

            $result = $this->service->getHistory($actor['user_id'], $filters, $page, $limit);
            Response::success($result);
        } catch (Throwable $e) {
            Response::error($e->getMessage(), 400);
        }
    }
}
