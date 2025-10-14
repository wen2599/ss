<?php
// api_header.php

// --- CORS and Security Headers ---
// Set these headers before any output, including session_start()
$allowed_origins = ['http://localhost:3000', 'https://ss.wenxiuxiu.eu.org', 'http://localhost:5173'];
// Use null coalescing operator to avoid warnings in CLI environment
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';

if (in_array($origin, $allowed_origins)) {
    header("Access-Control-Allow-Origin: " . $origin);
}

header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header('Content-Type: application/json');

// --- Session Configuration ---
// Configure session cookie parameters for better security and cross-site compatibility.
// This is crucial for the frontend and backend on different domains.
session_set_cookie_params([
    'lifetime' => 86400, // 24 hours
    'path' => '/',
    'domain' => '', // Auto-detect domain
    'secure' => true,   // Only send cookie over HTTPS
    'httponly' => true, // Prevent client-side script access
    'samesite' => 'None' // Allow cross-site cookie usage (required for different domains)
]);

// Start the session *after* setting headers and cookie params.
session_start();

// Handle pre-flight OPTIONS requests from browsers.
// Use null coalescing operator to avoid warnings in CLI environment
$request_method = $_SERVER['REQUEST_METHOD'] ?? '';
if ($request_method === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Include the main configuration file which loads environment variables and other helpers.
require_once __DIR__ . '/config.php';