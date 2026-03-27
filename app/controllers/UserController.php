<?php

/**
 * UserController
 *
 * Purpose:
 * Handles user CRUD HTTP requests and JSON responses.
 *
 * Architecture Rule:
 * Controller -> Service -> Repository -> Database
 *
 * IMPORTANT RULES:
 * - Controller handles request/response only
 * - No business logic is allowed here
 * - No SQL is allowed here
 */

require_once __DIR__ . '/../services/UserService.php';
require_once __DIR__ . '/../helpers/Response.php';

class UserController
{
    /**
     * @var UserService
     */
    private $userService;

    public function __construct()
    {
        $this->userService = new UserService();
    }

    /**
     * Handle create user request.
     */
    public function createUser()
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

            $user = $this->userService->createUser(
                [
                    'full_name' => $data['full_name'] ?? null,
                    'email' => $data['email'] ?? null,
                    'password' => $data['password'] ?? null,
                    'role_id' => $data['role_id'] ?? null
                ],
                (int) $actorUserId
            );

            Response::success($user);
        } catch (Throwable $exception) {
            Response::error($exception->getMessage(), 400);
        }
    }

    /**
     * Handle update user request.
     */
    public function updateUser()
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

            $user = $this->userService->updateUser(
                [
                    'user_id' => $data['user_id'] ?? null,
                    'full_name' => $data['full_name'] ?? null,
                    'email' => $data['email'] ?? null,
                    'role_id' => $data['role_id'] ?? null
                ],
                (int) $actorUserId
            );

            Response::success($user);
        } catch (Throwable $exception) {
            Response::error($exception->getMessage(), 400);
        }
    }

    /**
     * Handle get user by ID request.
     */
    public function getUserById()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            Response::error('Method not allowed', 405);
            return;
        }

        try {
            $user = $this->userService->getUserById($_GET['user_id'] ?? null);

            Response::success($user);
        } catch (Throwable $exception) {
            Response::error($exception->getMessage(), 400);
        }
    }

    /**
     * Handle get all non-deleted users request.
     */
    public function getAllUsers()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            Response::error('Method not allowed', 405);
            return;
        }

        try {
            $users = $this->userService->getAllUsers();

            Response::success($users);
        } catch (Throwable $exception) {
            Response::error($exception->getMessage(), 400);
        }
    }

    /**
     * Handle soft delete user request.
     */
    public function softDeleteUser()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            Response::error('Method not allowed', 405);
            return;
        }

        $deletedBy = $_SESSION['user_id'] ?? null;

        if (!$deletedBy) {
            Response::error('Unauthorized', 401);
            return;
        }

        try {
            $data = $this->getRequestData();

            $deleted = $this->userService->softDeleteUser(
                $data['user_id'] ?? null,
                $deletedBy
            );

            Response::success($deleted);
        } catch (Throwable $exception) {
            Response::error($exception->getMessage(), 400);
        }
    }

    public function activateUser()
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

            if (!isset($data['user_id']) || !is_numeric($data['user_id'])) {
                throw new InvalidArgumentException('Invalid user_id');
            }

            $userId = (int) $data['user_id'];
            $this->userService->activateUser($userId, (int) $actorUserId);
            Response::success(null);
        } catch (Throwable $exception) {
            Response::error($exception->getMessage(), 400);
        }
    }

    public function deactivateUser()
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

            if (!isset($data['user_id']) || !is_numeric($data['user_id'])) {
                throw new InvalidArgumentException('Invalid user_id');
            }

            $userId = (int) $data['user_id'];
            $this->userService->deactivateUser($userId, (int) $actorUserId);
            Response::success(null);
        } catch (Throwable $exception) {
            Response::error($exception->getMessage(), 400);
        }
    }

    public function restoreUser()
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

            if (!isset($data['user_id']) || !is_numeric($data['user_id'])) {
                throw new InvalidArgumentException('Invalid user_id');
            }

            $userId = (int) $data['user_id'];
            $this->userService->restoreUser($userId, (int) $actorUserId);
            Response::success(null);
        } catch (Throwable $exception) {
            Response::error($exception->getMessage(), 400);
        }
    }

    /**
     * Get request data from JSON or standard form submission.
     */
    private function getRequestData()
    {
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';

        // PHP does not populate $_POST for JSON requests,
        // so we manually decode php://input when needed.
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
