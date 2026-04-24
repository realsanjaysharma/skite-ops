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
require_once __DIR__ . '/../helpers/Csrf.php';
require_once __DIR__ . '/../helpers/Response.php';

class AuthController extends BaseController
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
        if (!$this->requireMethod('POST')) return;

        try {
            $data = $this->getInput();
            $email = $data['email'] ?? null;
            $password = $data['password'] ?? null;

            $authResult = $this->authService->login($email, $password);
            $requiresPasswordReset = (bool) ($authResult['requires_password_reset'] ?? false);
            $user = $authResult['user'] ?? [];

            session_regenerate_id(true);
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['role_id'] = $user['role_id'];
            $_SESSION['role_key'] = $user['role_key'] ?? null;
            $_SESSION['logged_in'] = true;
            $_SESSION['force_password_reset'] = $requiresPasswordReset;
            $csrfToken = Csrf::generateToken();

            Response::success([
                'user' => $user,
                'requires_password_reset' => $requiresPasswordReset,
                'csrf_token' => $csrfToken,
                'landing_module_key' => $authResult['landing_module_key'] ?? null,
                'landing_route' => $authResult['landing_route'] ?? null,
                'permission_group_key' => $authResult['permission_group_key'] ?? null,
                'allowed_module_keys' => $authResult['allowed_module_keys'] ?? []
            ]);
        } catch (Throwable $exception) {
            Response::error($exception->getMessage(), 400);
        }
    }

    public function session()
    {
        if (!$this->requireMethod('GET')) return;

        try {
            $userId = $_SESSION['user_id'] ?? null;

            if (!$userId) {
                Response::error('Unauthorized', 401);
                return;
            }

            $sessionData = $this->authService->getSessionData((int) $userId);
            $sessionUser = $sessionData['user'] ?? null;
            $_SESSION['role_id'] = $sessionUser['role_id'] ?? $_SESSION['role_id'] ?? null;
            $_SESSION['role_key'] = $sessionUser['role_key'] ?? $_SESSION['role_key'] ?? null;
            $_SESSION['force_password_reset'] = (bool) ($sessionData['requires_password_reset'] ?? false);
            $csrfToken = Csrf::generateToken();

            Response::success([
                'user' => $sessionUser,
                'requires_password_reset' => (bool) ($sessionData['requires_password_reset'] ?? false),
                'csrf_token' => $csrfToken,
                'landing_module_key' => $sessionData['landing_module_key'] ?? null,
                'landing_route' => $sessionData['landing_route'] ?? null,
                'permission_group_key' => $sessionData['permission_group_key'] ?? null,
                'allowed_module_keys' => $sessionData['allowed_module_keys'] ?? []
            ]);
        } catch (Throwable $exception) {
            Response::error($exception->getMessage(), 400);
        }
    }

    public function logout()
    {
        if (!$this->requireMethod('POST')) return;

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

    public function resetPassword()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            Response::error('Method not allowed', 405);
            return;
        }

        try {
            $data     = $this->getInput();
            $newPassword = $data['password'] ?? null;

            if (!$newPassword) {
                throw new InvalidArgumentException('Password is required');
            }

            $userId = $this->getActor()['user_id'];

            if (!$userId) {
                Response::error('Unauthorized', 401);
                return;
            }

            $this->authService->resetPassword($userId, $newPassword);
            $_SESSION['force_password_reset'] = false;

            Response::success(null);
        } catch (Throwable $e) {
            Response::error($e->getMessage(), 400);
        }
    }

}
