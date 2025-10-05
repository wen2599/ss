<?php
// backend/index.php

// --- Force Error Logging for Debugging ---
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error.log');

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

// --- Query-Based Routing ---
// This is the most compatible routing method, expecting URLs like:
// /backend/index.php?endpoint=get_numbers
$endpoint = $_GET['endpoint'] ?? 'not_found';

// Sanitize the endpoint name to prevent directory traversal and invalid characters.
$endpoint = basename($endpoint, '.php');
$endpoint = preg_replace('/[^a-zA-Z0-9_]/', '', $endpoint);

if (empty($endpoint)) {
    $endpoint = 'not_found';
}

$endpoint_file = __DIR__ . '/endpoints/' . $endpoint . '.php';

if (file_exists($endpoint_file)) {
    require_once $endpoint_file;
} else {
    error_log("Endpoint not found for request: {$_SERVER['REQUEST_URI']}. Resolved to: {$endpoint_file}");
    send_json_response(['error' => "API endpoint '{$endpoint}' not found."], 404);
}
?>