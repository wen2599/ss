<?php
// backend/index.php

// --- Simple Request Logging for Debugging ---
$log_message = date('[Y-m-d H:i:s]') . " " . $_SERVER['REQUEST_METHOD'] . " " . $_SERVER['REQUEST_URI'] . "\n";
file_put_contents(__DIR__ . '/request.log', $log_message, FILE_APPEND);

// Bootstrap the application by loading environment variables and configuration.
require_once __DIR__ . '/bootstrap.php';

// --- CORS and Preflight Request Handling ---
// A list of allowed origins for CORS.
$allowed_origins = [
    'http://localhost:5173',      // Vite dev server
    'https://ss.wenxiuxiu.eu.org' // Production frontend
];

if (isset($_SERVER['HTTP_ORIGIN']) && in_array($_SERVER['HTTP_ORIGIN'], $allowed_origins)) {
    header("Access-Control-Allow-Origin: {$_SERVER['HTTP_ORIGIN']}");
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
}

// Handle preflight 'OPTIONS' requests and exit early.
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD'])) {
        header("HTTP/1.1 200 OK");
    }
    exit(0);
}

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/helpers.php';

// Get the requested path from the server's request URI.
// The RewriteRule in .htaccess ensures that all requests go here.
$request_uri = $_SERVER['REQUEST_URI'];
$base_path = '/backend'; // Adjust if your app is in a subdirectory

// Remove the base path and query string from the request URI to get the endpoint path.
$request_path = parse_url(str_replace($base_path, '', $request_uri), PHP_URL_PATH);

// Sanitize the path to prevent directory traversal attacks.
// Allow only alphanumeric characters and underscores in the endpoint name.
$endpoint = preg_replace('/[^a-zA-Z0-9_]/', '', trim($request_path, '/'));

// Default to a 'not_found' endpoint if the requested path is empty.
if (empty($endpoint)) {
    $endpoint = 'not_found';
}

// Construct the path to the endpoint file.
$endpoint_file = __DIR__ . '/endpoints/' . $endpoint . '.php';

// If the endpoint file exists, include it. Otherwise, handle as a 404 Not Found error.
if (file_exists($endpoint_file)) {
    require_once $endpoint_file;
} else {
    send_json_response(['error' => 'API endpoint not found.'], 404);
}
?>