<?php
// A simple front-controller to route API requests.

require_once __DIR__ . '/init.php';

// --- Basic Router ---
$request_uri = $_SERVER['REQUEST_URI'];
$script_path = dirname($_SERVER['SCRIPT_NAME']);

// Remove the base path from the request URI to get the endpoint path
if (strpos($request_uri, $script_path) === 0) {
    $path = substr($request_uri, strlen($script_path));
} else {
    $path = $request_uri;
}

// Get the final part of the path as the endpoint (e.g., 'login.php')
$endpoint = basename(trim($path, '/'));

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
// Pass the determined endpoint to the handler.
require_once __DIR__ . '/api_handler.php';
handle_api_request($endpoint);
?>