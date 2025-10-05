<?php
// backend/index.php

// --- Simple Request Logging for Debugging ---
$log_message = date('[Y-m-d H:i:s]') . " " . $_SERVER['REQUEST_METHOD'] . " " . $_SERVER['REQUEST_URI'] . "\n";
file_put_contents(__DIR__ . '/request.log', $log_message, FILE_APPEND);

// Bootstrap the application by loading environment variables and configuration.
require_once __DIR__ . '/bootstrap.php';

// --- CORS and Preflight Request Handling ---
$allowed_origins = [
    'http://localhost:5173',
    'https://ss.wenxiuxiu.eu.org'
];
if (isset($_SERVER['HTTP_ORIGIN']) && in_array($_SERVER['HTTP_ORIGIN'], $allowed_origins)) {
    header("Access-Control-Allow-Origin: {$_SERVER['HTTP_ORIGIN']}");
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
}
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD'])) {
        header("HTTP/1.1 200 OK");
    }
    exit(0);
}

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/helpers.php';

// --- Flexible Routing ---
$request_uri = $_SERVER['REQUEST_URI'];
$base_path = '/backend';

// Remove the base path to isolate the endpoint part.
$request_path = str_replace($base_path, '', $request_uri);
// Remove any query string.
$request_path = parse_url($request_path, PHP_URL_PATH);
// Remove the .php extension if it exists to handle both clean and old URLs.
$request_path = preg_replace('/\.php$/', '', $request_path);

// Sanitize the path to prevent directory traversal and invalid characters.
$endpoint = preg_replace('/[^a-zA-Z0-9_]/', '', trim($request_path, '/'));

if (empty($endpoint)) {
    // Handle root requests if necessary, otherwise default to not_found.
    $endpoint = 'not_found';
}

$endpoint_file = __DIR__ . '/endpoints/' . $endpoint . '.php';

if (file_exists($endpoint_file)) {
    require_once $endpoint_file;
} else {
    // Log the missing endpoint for debugging.
    error_log("Endpoint not found for request: {$request_uri}. Resolved to: {$endpoint_file}");
    send_json_response(['error' => "API endpoint '{$endpoint}' not found."], 404);
}
?>