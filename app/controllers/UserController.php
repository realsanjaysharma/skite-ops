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
        header('Content-Type: application/json');
        $response = [];

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            $response = [
                'success' => false,
                'error' => 'Invalid request method'
            ];
            echo json_encode($response);
            return;
        }

        try {
            $data = $this->getRequestData();

            $user = $this->userService->createUser([
                'full_name' => $data['full_name'] ?? null,
                'email' => $data['email'] ?? null,
                'password' => $data['password'] ?? null,
                'role_id' => $data['role_id'] ?? null
            ]);

            http_response_code(200);
            $response = [
                'success' => true,
                'data' => $user
            ];
        } catch (Throwable $exception) {
            http_response_code(400);
            $response = [
                'success' => false,
                'error' => $exception->getMessage()
            ];
        }

        echo json_encode($response);
    }

    /**
     * Handle update user request.
     */
    public function updateUser()
    {
        header('Content-Type: application/json');
        $response = [];

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            $response = [
                'success' => false,
                'error' => 'Invalid request method'
            ];
            echo json_encode($response);
            return;
        }

        try {
            $data = $this->getRequestData();

            $user = $this->userService->updateUser(
                $data['user_id'] ?? null,
                [
                    'full_name' => $data['full_name'] ?? null,
                    'email' => $data['email'] ?? null,
                    'role_id' => $data['role_id'] ?? null
                ]
            );

            http_response_code(200);
            $response = [
                'success' => true,
                'data' => $user
            ];
        } catch (Throwable $exception) {
            http_response_code(400);
            $response = [
                'success' => false,
                'error' => $exception->getMessage()
            ];
        }

        echo json_encode($response);
    }

    /**
     * Handle get user by ID request.
     */
    public function getUserById()
    {
        header('Content-Type: application/json');
        $response = [];

        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            http_response_code(405);
            $response = [
                'success' => false,
                'error' => 'Invalid request method'
            ];
            echo json_encode($response);
            return;
        }

        try {
            $user = $this->userService->getUserById($_GET['user_id'] ?? null);

            http_response_code(200);
            $response = [
                'success' => true,
                'data' => $user
            ];
        } catch (Throwable $exception) {
            http_response_code(400);
            $response = [
                'success' => false,
                'error' => $exception->getMessage()
            ];
        }

        echo json_encode($response);
    }

    /**
     * Handle get all active users request.
     */
    public function getAllUsers()
    {
        header('Content-Type: application/json');
        $response = [];

        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            http_response_code(405);
            $response = [
                'success' => false,
                'error' => 'Invalid request method'
            ];
            echo json_encode($response);
            return;
        }

        try {
            $users = $this->userService->getAllActiveUsers();

            http_response_code(200);
            $response = [
                'success' => true,
                'data' => $users
            ];
        } catch (Throwable $exception) {
            http_response_code(400);
            $response = [
                'success' => false,
                'error' => $exception->getMessage()
            ];
        }

        echo json_encode($response);
    }

    /**
     * Handle soft delete user request.
     */
    public function softDeleteUser()
    {
        header('Content-Type: application/json');
        $response = [];

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            $response = [
                'success' => false,
                'error' => 'Invalid request method'
            ];
            echo json_encode($response);
            return;
        }

        try {
            $data = $this->getRequestData();

            $deleted = $this->userService->softDeleteUser(
                $data['user_id'] ?? null,
                $data['deleted_by'] ?? null
            );

            http_response_code(200);
            $response = [
                'success' => true,
                'data' => $deleted
            ];
        } catch (Throwable $exception) {
            http_response_code(400);
            $response = [
                'success' => false,
                'error' => $exception->getMessage()
            ];
        }

        echo json_encode($response);
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
