<?php
require_once __DIR__ . '/bootstrap.php';

// --- Simple Request Router ---
$route = $_GET['route'] ?? null;

// --- Fallback for direct file access ---
// This allows old endpoints to continue working during the transition.
if ($route === null) {
    $script_name = basename($_SERVER['SCRIPT_NAME']);
    $file_path = __DIR__ . '/api/' . $script_name;
    if (file_exists($file_path) && is_file($file_path)) {
        require_once $file_path;
        exit;
    }
}

// --- New Controller-based Routing ---
switch ($route) {
    case 'auth':
        require_once __DIR__ . '/api/AuthController.php';
        $controller = new AuthController();
        $controller->handleRequest();
        break;

    case 'users/is-registered':
        require_once __DIR__ . '/api/UserController.php';
        $controller = new UserController();
        $controller->isRegistered();
        break;

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
