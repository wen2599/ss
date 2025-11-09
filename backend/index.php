<?php
// File: backend/index.php (Centralized Dependency Loading)

// --- 1. Load ALL core dependencies in one place, in the correct order ---
require_once __DIR__ . '/config.php';       // Defines config()
require_once __DIR__ . '/db_operations.php'; // Defines get_db_connection(), uses config()
require_once __DIR__ . '/api_header.php';    // Handles headers and session, uses config()

// --- 2. Get endpoint and route ---
$endpoint = $_GET['endpoint'] ?? null;

if ($endpoint === null) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Missing endpoint parameter.']);
    exit();
}

$routes = [
    'register' => 'auth/register.php',
    'login' => 'auth/login.php',
    'logout' => 'auth/logout.php',
    'check_session' => 'auth/check_session.php',
    'get_lottery_results' => 'lottery/get_results.php',
];

if (isset($routes[$endpoint])) {
    // Now, when we require the endpoint file, all necessary functions are already defined.
    require_once __DIR__ . '/' . $routes[$endpoint];
} else {
    http_response_code(404);
    echo json_encode(['status' => 'error', 'message' => 'Endpoint not found.']);
}
?>