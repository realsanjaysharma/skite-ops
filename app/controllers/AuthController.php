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
require_once __DIR__ . '/../helpers/Response.php';

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
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            Response::error('Method not allowed', 405);
            return;
        }

        try {
            $data = $this->getRequestData();
            $email = $data['email'] ?? null;
            $password = $data['password'] ?? null;

            $user = $this->authService->login($email, $password);
            session_regenerate_id(true);
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['role_id'] = $user['role_id'];
            $_SESSION['logged_in'] = true;
            Response::success($user);
        } catch (Throwable $exception) {
            Response::error($exception->getMessage(), 400);
        }
    }

    public function logout()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            Response::error('Method not allowed', 405);
            return;
        }

        $_SESSION = [];
        session_destroy();
        session_write_close();

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();

            setcookie(session_name(), '', [
                'expires' => time() - 42000,
                'path' => $params['path'],
                'domain' => $params['domain'],
                'secure' => $params['secure'],
                'httponly' => $params['httponly'],
                'samesite' => 'Strict'
            ]);
        }

        Response::success(null);
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
