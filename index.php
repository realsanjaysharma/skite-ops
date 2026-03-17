<?php

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

$route = $_GET['route'] ?? '';
$routes = [
    'auth/login' => ['AuthController', 'login']
];

if ($route === '') {
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

if (isset($routes[$route])) {
    $controllerName = $routes[$route][0];
    $methodName = $routes[$route][1];

    require_once __DIR__ . '/app/controllers/' . $controllerName . '.php';

    $controller = new $controllerName();
    $controller->$methodName();
    return;
}

http_response_code(400);
echo json_encode([
    'success' => false,
    'error' => 'Route not found'
]);
