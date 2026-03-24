<?php

// ==========================================
// GLOBAL SESSION SECURITY CONFIG
// Must be set BEFORE session_start()
// ==========================================
session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'domain'   => '',
    'secure'   => false, // set true only when HTTPS
    'httponly' => true,
    'samesite' => 'Strict'
]);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * index.php
 *
 * Purpose:
 * Application routing entry point for JSON API requests.
 *
 * Architecture Rule:
 * Router -> Controller -> Service -> Repository -> Database
 *
 * IMPORTANT RULES:
 * - Routing only
 * - No business logic
 * - No database access
 */

header('Content-Type: application/json');

$route = $_GET['route'] ?? null;

if ($route === null || $route === '') {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Route not specified'
    ]);
    return;
}

$parts = explode('/', $route);

if (count($parts) !== 2 || $parts[0] === '' || $parts[1] === '') {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Invalid route format'
    ]);
    return;
}

require_once __DIR__ . '/app/middleware/AuthMiddleware.php';

if ($route !== 'auth/login') {
    $authMiddleware = new AuthMiddleware();
    $authMiddleware->authorize($route);
}

switch ($route) {
    case 'auth/login':
        require_once __DIR__ . '/app/controllers/AuthController.php';
        $controller = new AuthController();
        $controller->login();
        return;

    case 'auth/logout':
        require_once __DIR__ . '/app/controllers/AuthController.php';
        $controller = new AuthController();
        $controller->logout();
        return;

    case 'auth/reset-password':
        require_once __DIR__ . '/app/controllers/AuthController.php';
        $controller = new AuthController();
        $controller->resetPassword();
        return;

    case 'user/create':
        require_once __DIR__ . '/app/controllers/UserController.php';
        $controller = new UserController();
        $controller->createUser();
        return;

    case 'user/update':
        require_once __DIR__ . '/app/controllers/UserController.php';
        $controller = new UserController();
        $controller->updateUser();
        return;

    case 'user/get':
        require_once __DIR__ . '/app/controllers/UserController.php';
        $controller = new UserController();
        $controller->getUserById();
        return;

    case 'user/list':
        require_once __DIR__ . '/app/controllers/UserController.php';
        $controller = new UserController();
        $controller->getAllUsers();
        return;

    case 'user/delete':
        require_once __DIR__ . '/app/controllers/UserController.php';
        $controller = new UserController();
        $controller->softDeleteUser();
        return;
}

http_response_code(400);
echo json_encode([
    'success' => false,
    'error' => 'Route not found'
]);
