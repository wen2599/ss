<?php
// File: backend/index.php
require_once __DIR__ . '/api_header.php';

$endpoint = $_GET['endpoint'] ?? null;
$routes = [
    'register' => 'auth/register.php',
    'login' => 'auth/login.php',
    'logout' => 'auth/logout.php',
    'check_session' => 'auth/check_session.php',
    'get_lottery_results' => 'lottery/get_results.php',
];

if (isset($routes[$endpoint])) {
    require_once __DIR__ . '/' . $routes[$endpoint];
} else {
    http_response_code(404);
    echo json_encode(['status' => 'error', 'message' => 'Endpoint not found.']);
}