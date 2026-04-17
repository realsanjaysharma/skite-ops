<?php

require_once __DIR__ . '/config/constants.php';

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

if (is_string($route)) {
    $route = strtolower(trim($route));
    $route = trim($route, '/');
}

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
$routeRegistry = require __DIR__ . '/config/route_registry.php';
$routeConfig = $routeRegistry[$route] ?? null;

if ($routeConfig === null) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Route not found'
    ]);
    return;
}

if (!(bool) ($routeConfig['public'] ?? false)) {
    $authMiddleware = new AuthMiddleware();
    $authMiddleware->authorize($route, $routeConfig);
}

$controllerName = $routeConfig['controller'] ?? null;
$controllerMethod = $routeConfig['method'] ?? null;

if (!is_string($controllerName) || !is_string($controllerMethod)) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Route handler not configured'
    ]);
    return;
}

$controllerFile = __DIR__ . '/app/controllers/' . $controllerName . '.php';

if (!file_exists($controllerFile)) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Controller file not found'
    ]);
    return;
}

require_once $controllerFile;

if (!class_exists($controllerName)) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Controller class not found'
    ]);
    return;
}

$controller = new $controllerName();

if (!method_exists($controller, $controllerMethod)) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Controller method not found'
    ]);
    return;
}

$controller->{$controllerMethod}();
return;
