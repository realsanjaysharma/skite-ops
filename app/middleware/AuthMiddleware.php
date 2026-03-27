<?php

require_once __DIR__ . '/../helpers/Csrf.php';
require_once __DIR__ . '/../helpers/Response.php';

class AuthMiddleware
{
    /**
     * Centralized route permission map.
     * Route keys match index.php route strings exactly.
     */
    private array $routePermissions = [
        'user/create' => [1],
        'user/update' => [1],
        'user/delete' => [1],
        'user/activate' => [1],
        'user/deactivate' => [1],
        'user/restore' => [1],
        'user/list'   => [1],
        'user/get'    => [1],
    ];

    /**
     * Routes allowed while force password reset is pending.
     */
    private array $forceResetAllowedRoutes = [
        'auth/logout',
        'auth/reset-password',
    ];

    public function authorize(string $route): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $userId = $_SESSION['user_id'] ?? null;
        $roleId = $_SESSION['role_id'] ?? null;

        // Requests without an authenticated session must never reach controllers.
        if (!$userId) {
            Response::error('Unauthorized', 401);
            exit;
        }

        $forceReset = $_SESSION['force_password_reset'] ?? false;

        if ($forceReset) {
            if (!in_array($route, $this->forceResetAllowedRoutes)) {
                Response::error('Password reset required', 403);
                exit;
            }
        }

        if (in_array($_SERVER['REQUEST_METHOD'], ['POST', 'PUT', 'DELETE'])) {
            if ($route !== 'auth/login' && $route !== 'auth/logout') {
                $headers = array_change_key_case(getallheaders(), CASE_LOWER);
                $token = $headers['x-csrf-token'] ?? null;

                if (!Csrf::validateToken($token)) {
                    Response::error('Invalid CSRF token', 403);
                    exit;
                }
            }
        }

        // Routes missing from the permission map are treated as unrestricted.
        if (!isset($this->routePermissions[$route])) {
            return;
        }

        $allowedRoles = $this->routePermissions[$route];

        if (!in_array($roleId, $allowedRoles)) {
            Response::error('Forbidden', 403);
            exit;
        }
    }
}
