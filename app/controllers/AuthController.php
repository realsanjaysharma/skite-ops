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
            echo json_encode([
                'success' => false,
                'error' => 'Method not allowed'
            ]);
            return;
        } else {
            try {
            $email = $_POST['email'] ?? null;
            $password = $_POST['password'] ?? null;

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
        }

        echo json_encode($response);
    }
}
