<?php

class AuthMiddleware
{
    public static function check()
    {
        // Session must be started before checking authentication
        // This ensures consistent session handling across all protected routes
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
            http_response_code(401);
            echo json_encode([
                'success' => false,
                'error' => 'Unauthorized'
            ]);
            exit;
        }
    }

    public static function checkRole(array $allowedRoles)
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Authentication check
        if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
            http_response_code(401);
            echo json_encode([
                'success' => false,
                'error' => 'Unauthorized'
            ]);
            exit;
        }

        // Role check
        if (!isset($_SESSION['role_id']) || !in_array($_SESSION['role_id'], $allowedRoles)) {
            http_response_code(403);
            echo json_encode([
                'success' => false,
                'error' => 'Forbidden'
            ]);
            exit;
        }
    }
}
