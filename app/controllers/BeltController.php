<?php

/**
 * BeltController
 *
 * Purpose:
 * Handles Green Belt Master HTTP requests and JSON responses.
 *
 * Architecture Rule:
 * Controller -> Service -> Repository -> Database
 *
 * IMPORTANT RULES:
 * - Controller handles request/response only
 * - No business logic is allowed here
 * - No SQL is allowed here
 */

require_once __DIR__ . '/../services/BeltService.php';
require_once __DIR__ . '/../helpers/Response.php';
require_once __DIR__ . '/../../config/constants.php';

class BeltController
{
    /**
     * @var BeltService
     */
    private $beltService;

    public function __construct()
    {
        $this->beltService = new BeltService();
    }

    /**
     * GET belt/list
     *
     * Query params: zone, permission_status, maintenance_mode, hidden, supervisor_user_id, page, limit
     */
    public function listBelts(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            Response::error('Method not allowed', 405);
            return;
        }

        try {
            $filters = [
                'zone'                => $_GET['zone'] ?? null,
                'permission_status'   => $_GET['permission_status'] ?? null,
                'maintenance_mode'    => $_GET['maintenance_mode'] ?? null,
                'hidden'              => $_GET['hidden'] ?? null,
                'supervisor_user_id'  => $_GET['supervisor_user_id'] ?? null,
            ];

            $page  = (int) ($_GET['page'] ?? 1);
            $limit = (int) ($_GET['limit'] ?? DEFAULT_PAGE_LIMIT);

            $result = $this->beltService->listBelts($filters, $page, $limit);

            Response::success($result);
        } catch (Throwable $e) {
            Response::error($e->getMessage(), 400);
        }
    }

    /**
     * GET belt/get
     *
     * Query params: belt_id
     */
    public function getBelt(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            Response::error('Method not allowed', 405);
            return;
        }

        try {
            $beltId = $_GET['belt_id'] ?? null;

            if (empty($beltId) || !is_numeric($beltId)) {
                Response::error('Valid belt_id is required.', 400);
                return;
            }

            $result = $this->beltService->getBelt(
                (int) $beltId,
                isset($_SESSION['role_key']) ? (string) $_SESSION['role_key'] : null
            );

            Response::success($result);
        } catch (RuntimeException $e) {
            $statusCode = $e->getMessage() === 'Forbidden' ? 403 : 400;
            Response::error($e->getMessage(), $statusCode);
        } catch (Throwable $e) {
            Response::error($e->getMessage(), 400);
        }
    }

    /**
     * POST belt/create
     *
     * JSON body with belt fields.
     */
    public function createBelt(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            Response::error('Method not allowed', 405);
            return;
        }

        $actorUserId = $_SESSION['user_id'] ?? null;

        if (!$actorUserId) {
            Response::error('Unauthorized', 401);
            return;
        }

        try {
            $data = $this->getRequestData();

            $result = $this->beltService->createBelt($data, (int) $actorUserId);

            Response::success($result);
        } catch (Throwable $e) {
            Response::error($e->getMessage(), 400);
        }
    }

    /**
     * POST belt/update
     *
     * JSON body with belt_id and editable fields.
     */
    public function updateBelt(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            Response::error('Method not allowed', 405);
            return;
        }

        $actorUserId = $_SESSION['user_id'] ?? null;

        if (!$actorUserId) {
            Response::error('Unauthorized', 401);
            return;
        }

        try {
            $data = $this->getRequestData();

            $result = $this->beltService->updateBelt($data, (int) $actorUserId);

            Response::success($result);
        } catch (Throwable $e) {
            Response::error($e->getMessage(), 400);
        }
    }

    /**
     * Parse JSON or form-encoded request body.
     */
    private function getRequestData(): array
    {
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';

        if (strpos($contentType, 'application/json') !== false) {
            $rawInput = file_get_contents('php://input');
            $data = json_decode($rawInput, true);

            if (!is_array($data)) {
                throw new InvalidArgumentException('Invalid JSON payload.');
            }

            return $data;
        }

        return $_POST;
    }
}
