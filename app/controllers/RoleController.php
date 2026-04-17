<?php

require_once __DIR__ . '/../services/RoleService.php';
require_once __DIR__ . '/../helpers/Response.php';
require_once __DIR__ . '/../../config/constants.php';

class RoleController
{
    private RoleService $roleService;

    public function __construct()
    {
        $this->roleService = new RoleService();
    }

    public function getAllRoles(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            Response::error('Method not allowed', 405);
            return;
        }

        try {
            $roles = $this->roleService->getAllRoles([
                'page' => $_GET['page'] ?? 1,
                'limit' => $_GET['limit'] ?? DEFAULT_PAGE_LIMIT,
                'is_active' => $_GET['is_active'] ?? null,
                'is_system_role' => $_GET['is_system_role'] ?? null,
            ]);

            Response::success($roles);
        } catch (Throwable $exception) {
            Response::error($exception->getMessage(), 400);
        }
    }

    public function getRoleById(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            Response::error('Method not allowed', 405);
            return;
        }

        try {
            $role = $this->roleService->getRoleById($_GET['role_id'] ?? null);
            Response::success($role);
        } catch (Throwable $exception) {
            Response::error($exception->getMessage(), 400);
        }
    }

    public function createRole(): void
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
            $role = $this->roleService->createRole($this->getRequestData(), (int) $actorUserId);
            Response::success($role);
        } catch (Throwable $exception) {
            Response::error($exception->getMessage(), 400);
        }
    }

    public function updateRole(): void
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
            $role = $this->roleService->updateRole($this->getRequestData(), (int) $actorUserId);
            Response::success($role);
        } catch (Throwable $exception) {
            Response::error($exception->getMessage(), 400);
        }
    }

    private function getRequestData()
    {
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';

        if (strpos($contentType, 'application/json') !== false) {
            $rawInput = file_get_contents('php://input');
            $data = json_decode($rawInput, true);

            if (!is_array($data)) {
                throw new InvalidArgumentException('Invalid JSON payload');
            }

            return $data;
        }

        return $_POST;
    }
}
