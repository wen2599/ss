<?php
require_once __DIR__ . '/bootstrap.php';

// --- Simple Request Router ---
$request_uri = $_SERVER['REQUEST_URI'];
$request_path = parse_url($request_uri, PHP_URL_PATH);

// Normalize the path by removing the base directory if present
$base_dir = '/api/';
if (strpos($request_path, $base_dir) === 0) {
    $request_path = substr($request_path, strlen($base_dir));
}

// --- Route Definitions ---
switch ($request_path) {
    // --- Authentication Routes ---
    case 'auth':
        require_once __DIR__ . '/api/AuthController.php';
        $controller = new AuthController();
        $controller->handleRequest();
        break;

    // --- User Routes ---
    case 'users/is-registered':
        require_once __DIR__ . '/api/UserController.php';
        $controller = new UserController();
        $controller->isRegistered();
        break;

    // --- Email Routes ---
    case 'emails':
        require_once __DIR__ . '/api/EmailController.php';
        $controller = new EmailController();
        $controller->handleRequest();
        break;

    default:
        http_response_code(404);
        echo json_encode(["message" => "Endpoint not found"]);
        break;
}
