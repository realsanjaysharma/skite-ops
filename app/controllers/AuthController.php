<?php

/**
 * AuthController
 *
 * Purpose:
 * Handles authentication HTTP requests and JSON responses.
 *
 * Architecture Rule:
 * Controller -> Service -> Repository -> Database
 *
 * IMPORTANT RULES:
 * - Controller handles request/response only
 * - No business logic is allowed here
 * - No SQL is allowed here
 */

require_once __DIR__ . '/../services/AuthService.php';

class AuthController
{
    /**
     * @var AuthService
     */
    private $authService;

    public function __construct()
    {
        $this->authService = new AuthService();
    }

    /**
     * Handle login request and return JSON response.
     */
    public function login()
    {
        header('Content-Type: application/json');
        $response = [];

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            $response = [
                'success' => false,
                'error' => 'Method not allowed'
            ];
            echo json_encode($response);
            return;
        }

        try {
            $data = $this->getRequestData();
            $email = $data['email'] ?? null;
            $password = $data['password'] ?? null;

            $user = $this->authService->login($email, $password);
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
