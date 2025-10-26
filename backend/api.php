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

    // --- Add other routes here as needed ---

    default:
        // Fallback to old file-based routing for now
        $file_path = __DIR__ . '/api/' . $request_path;
        if (file_exists($file_path) && is_file($file_path)) {
            require_once $file_path;
        } else {
            http_response_code(404);
            echo json_encode(["message" => "Endpoint not found"]);
        }
        break;
}
