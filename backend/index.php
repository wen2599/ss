<?php
// A simple front-controller to route API requests using a query string.

// --- Query String Router ---
// This is the most reliable method and avoids server configuration issues.
// It expects URLs like /index.php?endpoint=login.php
$endpoint = $_GET['endpoint'] ?? null;

// If no endpoint is specified, return a 404 error.
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