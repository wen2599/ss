<?php
// A simple front-controller to route API requests.

require_once __DIR__ . '/init.php';

// --- Basic Router ---
$request_uri = $_SERVER['REQUEST_URI'];
$script_name = $_SERVER['SCRIPT_NAME'];

// This helps determine the path relative to the script entry point
$path = str_replace(dirname($script_name), '', $request_uri);
$path = trim($path, '/');
$path_parts = explode('/', $path);
$resource = $path_parts[0] ?? null;

// Start session for any endpoints that might need it
session_start();

// --- Set Headers ---
header("Content-Type: application/json");

// In a production environment, you would want to restrict this to your actual frontend domain.
// For now, allowing credentials from a development server.
if (isset($_SERVER['HTTP_ORIGIN']) && $_SERVER['HTTP_ORIGIN'] === 'http://localhost:5173') {
    header("Access-Control-Allow-Origin: http://localhost:5173");
    header("Access-Control-Allow-Credentials: true");
    header("Access-Control-Allow-Headers: Content-Type");
}

// --- Route Handling ---
switch ($resource) {
    case 'api':
        $endpoint = $path_parts[1] ?? null;
        require_once __DIR__ . '/api_handler.php';
        handle_api_request($endpoint);
        break;

    default:
        http_response_code(404);
        echo json_encode(['error' => 'Not Found']);
        break;
}
?>