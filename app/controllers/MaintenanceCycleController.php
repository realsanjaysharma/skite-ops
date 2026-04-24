<?php

/**
 * MaintenanceCycleController
 *
 * Purpose:
 * Handles maintenance cycle list/start/close HTTP requests.
 */

require_once __DIR__ . '/../services/MaintenanceCycleService.php';
require_once __DIR__ . '/../helpers/Response.php';

class MaintenanceCycleController extends BaseController
{
    /**
     * @var MaintenanceCycleService
     */
    private $cycleService;

    public function __construct()
    {
        $this->cycleService = new MaintenanceCycleService();
    }

    public function listCycles(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            Response::error('Method not allowed', 405);
            return;
        }

        try {
            $result = $this->cycleService->listCycles(
                [
                    'belt_id' => $_GET['belt_id'] ?? null,
                    'status' => $_GET['status'] ?? null,
                ],
                (string) ($_SESSION['role_key'] ?? '')
            );

            Response::success($result);
        } catch (RuntimeException $exception) {
            $statusCode = $exception->getMessage() === 'Forbidden' ? 403 : 400;
            Response::error($exception->getMessage(), $statusCode);
        } catch (Throwable $exception) {
            Response::error($exception->getMessage(), 400);
        }
    }

    public function startCycle(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            Response::error('Method not allowed', 405);
            return;
        }

        $actorUserId = (int) ($_SESSION['user_id'] ?? 0);

        if ($actorUserId <= 0) {
            Response::error('Unauthorized', 401);
            return;
        }

        try {
            $result = $this->cycleService->startCycle(
                $this->getRequestData(),
                $actorUserId,
                (string) ($_SESSION['role_key'] ?? '')
            );

            Response::success($result);
        } catch (RuntimeException $exception) {
            $statusCode = $exception->getMessage() === 'Forbidden' ? 403 : 400;
            Response::error($exception->getMessage(), $statusCode);
        } catch (Throwable $exception) {
            Response::error($exception->getMessage(), 400);
        }
    }

    public function closeCycle(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            Response::error('Method not allowed', 405);
            return;
        }

        $actorUserId = (int) ($_SESSION['user_id'] ?? 0);

        if ($actorUserId <= 0) {
            Response::error('Unauthorized', 401);
            return;
        }

        try {
            $data = $this->getRequestData();

            if (empty($data['cycle_id']) || !is_numeric($data['cycle_id'])) {
                Response::error('Valid cycle_id is required.', 400);
                return;
            }

            $result = $this->cycleService->closeCycle(
                (int) $data['cycle_id'],
                (string) ($data['end_date'] ?? ''),
                (string) ($data['close_reason'] ?? ''),
                $actorUserId,
                (string) ($_SESSION['role_key'] ?? '')
            );

            Response::success($result);
        } catch (RuntimeException $exception) {
            $statusCode = $exception->getMessage() === 'Forbidden' ? 403 : 400;
            Response::error($exception->getMessage(), $statusCode);
        } catch (Throwable $exception) {
            Response::error($exception->getMessage(), 400);
        }
    }

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
