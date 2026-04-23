<?php

require_once __DIR__ . '/../helpers/Csrf.php';
require_once __DIR__ . '/../helpers/Response.php';
require_once __DIR__ . '/../services/RbacService.php';

class AuthMiddleware
{
    private RbacService $rbacService;

    public function __construct()
    {
        $this->rbacService = new RbacService();
    }

    public function authorize(string $route, array $routeConfig): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $userId = $_SESSION['user_id'] ?? null;
        // Requests without an authenticated session must never reach controllers.
        if (!$userId) {
            error_log('[SKITE AUTH] Unauthenticated request to: ' . $route);
            Response::error('Unauthorized', 401);
            exit;
        }

        try {
            $accessContext = $this->rbacService->getUserAccessContext((int) $userId);
        } catch (Throwable $exception) {
            $this->destroySession();
            Response::error('Unauthorized', 401);
            exit;
        }

        $_SESSION['role_id'] = $accessContext['role_id'];
        $_SESSION['role_key'] = $accessContext['role_key'];
        $_SESSION['force_password_reset'] = $accessContext['force_password_reset'];
        $_SESSION['logged_in'] = true;

        $forceReset = (bool) ($accessContext['force_password_reset'] ?? false);
        $isForceResetAllowedRoute = (bool) ($routeConfig['allow_during_force_reset'] ?? false);

        if ($forceReset && !$isForceResetAllowedRoute) {
            Response::error('Password reset required', 403);
            exit;
        }

        if (in_array($_SERVER['REQUEST_METHOD'], ['POST', 'PUT', 'DELETE'])) {
            if (!(bool) ($routeConfig['csrf_exempt'] ?? false)) {
                $headers = array_change_key_case(getallheaders(), CASE_LOWER);
                $token = $headers['x-csrf-token'] ?? null;

                if (!Csrf::validateToken($token)) {
                    Response::error('Invalid CSRF token', 403);
                    exit;
                }
            }
        }

        $moduleKey = $routeConfig['module_key'] ?? null;

        if (!is_string($moduleKey) || trim($moduleKey) === '') {
            return;
        }

        try {
            $this->rbacService->authorizeModuleAccess(
                $accessContext,
                $moduleKey,
                $routeConfig['capability'] ?? 'read'
            );
        } catch (Throwable $exception) {
            error_log('[SKITE RBAC] Forbidden: user=' . $userId . ' route=' . $route . ' module=' . $moduleKey . ' cap=' . ($routeConfig['capability'] ?? 'read'));
            Response::error('Forbidden', 403);
            exit;
        }
    }

    private function destroySession(): void
    {
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
    }
}
