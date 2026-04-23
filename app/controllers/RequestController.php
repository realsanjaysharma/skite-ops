<?php

require_once __DIR__ . '/../helpers/Response.php';
require_once __DIR__ . '/../services/RequestService.php';

/**
 * RequestController
 *
 * Architecture: HTTP shape only. Role enforcement is in AuthMiddleware.
 * Requester scope and Ops-only approval/rejection live in RequestService.
 */
class RequestController
{
    private RequestService $requestService;

    public function __construct()
    {
        $this->requestService = new RequestService();
    }

    /**
     * GET request/list
     */
    public function listRequests(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            Response::error('Method not allowed', 405);
            return;
        }

        try {
            $filters = [
                'status'             => $_GET['status'] ?? null,
                'requester_user_id'  => $_GET['requester_user_id'] ?? null,
                'client_name'        => $_GET['client_name'] ?? null,
            ];

            $items = $this->requestService->listRequests(
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
     * GET request/get
     */
    public function getRequest(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            Response::error('Method not allowed', 405);
            return;
        }

        if (empty($_GET['request_id'])) {
            Response::error('Missing request_id param', 400);
            return;
        }

        try {
            $request = $this->requestService->getRequest(
                (int) $_GET['request_id'],
                (int) $_SESSION['user_id'],
                (string) ($_SESSION['role_key'] ?? '')
            );

            if (!$request) {
                Response::error('Request not found', 404);
                return;
            }

            Response::success($request);
        } catch (Throwable $e) {
            Response::error($e->getMessage(), 400);
        }
    }

    /**
     * POST request/create
     */
    public function createRequest(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            Response::error('Method not allowed', 405);
            return;
        }

        $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

        try {
            $result = $this->requestService->createRequest(
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

    /**
     * POST request/approve
     */
    public function approveRequest(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            Response::error('Method not allowed', 405);
            return;
        }

        $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

        if (empty($input['request_id'])) {
            Response::error('Missing request_id param', 400);
            return;
        }

        try {
            $result = $this->requestService->approveRequest(
                (int) $input['request_id'],
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

    /**
     * POST request/reject
     */
    public function rejectRequest(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            Response::error('Method not allowed', 405);
            return;
        }

        $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

        if (empty($input['request_id'])) {
            Response::error('Missing request_id param', 400);
            return;
        }

        try {
            $result = $this->requestService->rejectRequest(
                (int) $input['request_id'],
                (string) ($input['rejection_reason'] ?? ''),
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
