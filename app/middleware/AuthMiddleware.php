<?php

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
        'user/list'   => [1],
        'user/get'    => [1],
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
            http_response_code(401);
            echo json_encode([
                'success' => false,
                'error' => 'Unauthorized'
            ]);
            exit;
        }

        // Routes missing from the permission map are treated as unrestricted.
        if (!isset($this->routePermissions[$route])) {
            return;
        }

        $allowedRoles = $this->routePermissions[$route];

        if (!in_array($roleId, $allowedRoles)) {
            http_response_code(403);
            echo json_encode([
                'success' => false,
                'error' => 'Forbidden'
            ]);
            exit;
        }
    }
}
