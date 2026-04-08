<?php

require_once __DIR__ . '/../helpers/Csrf.php';
require_once __DIR__ . '/../helpers/Response.php';
require_once __DIR__ . '/../repositories/UserRepository.php';
require_once __DIR__ . '/../services/UserService.php';

class AuthMiddleware
{
    /**
     * Centralized route permission map.
     * Route keys match index.php route strings exactly.
     */
    private array $routePermissions = [
        'user/create' => ['OPS_MANAGER'],
        'user/update' => ['OPS_MANAGER'],
        'user/delete' => ['OPS_MANAGER'],
        'user/activate' => ['OPS_MANAGER'],
        'user/deactivate' => ['OPS_MANAGER'],
        'user/restore' => ['OPS_MANAGER'],
        'user/list'   => ['OPS_MANAGER'],
        'user/get'    => ['OPS_MANAGER'],
    ];

    /**
     * Routes allowed while force password reset is pending.
     */
    private array $forceResetAllowedRoutes = [
        'auth/session',
        'auth/logout',
        'auth/reset-password',
    ];

    public function authorize(string $route): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $userId = $_SESSION['user_id'] ?? null;
        // Requests without an authenticated session must never reach controllers.
        if (!$userId) {
            Response::error('Unauthorized', 401);
            exit;
        }

        $userRepository = new UserRepository();
        $userService = new UserService();
        $user = $userRepository->getUserByIdIncludingDeleted((int) $userId);

        if (!$user || !$userService->isUserActive($user)) {
            $_SESSION = [];
            session_unset();

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

            session_destroy();
            Response::error('Unauthorized', 401);
            exit;
        }

        $role = $userRepository->getRoleById((int) ($user['role_id'] ?? 0));
        $roleKey = $role['role_key'] ?? null;

        $isUtilityRoute = in_array($route, $this->forceResetAllowedRoutes, true);

        if (!$isUtilityRoute && !isset($this->routePermissions[$route])) {
            Response::error('Forbidden', 403);
            exit;
        }

        $forceReset = $_SESSION['force_password_reset'] ?? false;

        if ($forceReset) {
            if (!$isUtilityRoute) {
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

        // Utility routes bypass RBAC intentionally:
        // - logout must remain accessible to any authenticated user
        // - reset-password must remain accessible during forced reset flow
        if ($isUtilityRoute) {
            return;
        }

        $allowedRoles = $this->routePermissions[$route];

        if (!is_string($roleKey) || !in_array($roleKey, $allowedRoles, true)) {
            Response::error('Forbidden', 403);
            exit;
        }
    }
}
