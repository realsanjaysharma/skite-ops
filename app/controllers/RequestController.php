<?php

require_once __DIR__ . '/../helpers/Response.php';
require_once __DIR__ . '/../services/RequestService.php';

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
            $actorUserId = (int) $_SESSION['user_id'];
            $actorRoleKey = $_SESSION['role_key'] ?? '';

            $filters = [
                'status' => $_GET['status'] ?? null,
                'requester_user_id' => $_GET['requester_user_id'] ?? null,
                'client_name' => $_GET['client_name'] ?? null,
            ];

            // Service handles scoping based on role internally
            $items = $this->requestService->listRequests($filters, $actorUserId, $actorRoleKey);

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
            $actorUserId = (int) $_SESSION['user_id'];
            $actorRoleKey = $_SESSION['role_key'] ?? '';
            
            $request = $this->requestService->getRequest((int) $_GET['request_id'], $actorUserId, $actorRoleKey);
            
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
        $actorUserId = (int) $_SESSION['user_id'];
        $actorRoleKey = $_SESSION['role_key'] ?? '';

        try {
            $result = $this->requestService->createRequest($input, $actorUserId, $actorRoleKey);
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

        $actorUserId = (int) $_SESSION['user_id'];
        $actorRoleKey = $_SESSION['role_key'] ?? '';

        try {
            $result = $this->requestService->approveRequest((int) $input['request_id'], $actorUserId, $actorRoleKey);
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

        $actorUserId = (int) $_SESSION['user_id'];
        $actorRoleKey = $_SESSION['role_key'] ?? '';

        try {
            $result = $this->requestService->rejectRequest(
                (int) $input['request_id'], 
                $input['rejection_reason'] ?? '',
                $actorUserId, 
                $actorRoleKey
            );
            Response::success($result);
        } catch (DomainException $e) {
            Response::error($e->getMessage(), 403);
        } catch (Throwable $e) {
            Response::error($e->getMessage(), 400);
        }
    }
}
