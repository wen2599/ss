<?php
// A simple front-controller to route API requests using PATH_INFO.

require_once __DIR__ . '/init.php';

// --- PATH_INFO Router ---
// This method is more reliable than URL rewriting on some shared hosts.
// It expects URLs like /index.php/login.php
$path_info = $_SERVER['PATH_INFO'] ?? '';
$endpoint = basename(trim($path_info, '/'));

// If no endpoint is found, it's a bad request.
if (empty($endpoint)) {
    http_response_code(404);
    echo json_encode(['error' => 'Not Found: No API endpoint specified.']);
    exit;
}

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