<?php
require_once __DIR__ . '/bootstrap.php';

// --- Simple Request Router ---
$route = $_GET['route'] ?? null;
$request_method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// --- Fallback for direct file access (legacy endpoints) ---
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
        if ($request_method === 'POST') {
            require_once __DIR__ . '/api/AuthController.php';
            $controller = new AuthController();
            $controller->handleRequest();
        } else {
            http_response_code(405); // Method Not Allowed
            echo json_encode(["message" => "Method Not Allowed"]);
        }
        break;

    case 'users/is-registered':
        if ($request_method === 'GET') {
            require_once __DIR__ . '/api/UserController.php';
            $controller = new UserController();
            $controller->isRegistered();
        } else {
            http_response_code(405); // Method Not Allowed
            echo json_encode(["message" => "Method Not Allowed"]);
        }
        break;

    case 'emails':
        if ($request_method === 'POST') {
            require_once __DIR__ . '/api/EmailController.php';
            $controller = new EmailController();
            $controller->handleRequest();
        } else {
            http_response_code(405); // Method Not Allowed
            echo json_encode(["message" => "Method Not Allowed"]);
        }
        break;

    default:
        http_response_code(404);
        echo json_encode(["message" => "Endpoint not found"]);
        break;
}
