<?php
// api_header.php

// Debugging array to collect info
$debug_info = [];

// Determine if the environment is local development
$is_local = (isset($_SERVER['HTTP_HOST']) && strpos($_SERVER['HTTP_HOST'], 'localhost') !== false);

// Set session cookie parameters dynamically
$cookie_params = [
    'lifetime' => 3600,
    'path' => '/',
    'httponly' => true,
    'samesite' => 'Lax' // Use Lax for better cross-site request compatibility
];

// For production, set the domain and secure flag
if (!$is_local) {
    $cookie_params['domain'] = 'ss.wenxiuxiu.eu.org';
    $cookie_params['secure'] = true; // Enforce HTTPS on production
}
// For local development, the domain should not be set, and secure should be false.

session_set_cookie_params($cookie_params);

// Start the session.
session_start();

// Collect debugging info
$debug_info['api_header_request_origin'] = $_SERVER['HTTP_ORIGIN'] ?? 'N/A';
$debug_info['api_header_session_id'] = session_id();
$debug_info['api_header_session_data'] = $_SESSION;

// --- CORS and Security Headers ---
// Add all relevant local development ports to prevent CORS issues.
$allowed_origins = [
    'http://localhost:3000',
    'http://localhost:5173', // Default Vite port
    'http://localhost:5174',
    'http://localhost:5175', // Fallback Vite ports
    'https://ss.wenxiuxiu.eu.org'
];
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';

if (in_array($origin, $allowed_origins)) {
    header("Access-Control-Allow-Origin: " . $origin);
}

header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header('Content-Type: application/json');

// Handle pre-flight OPTIONS requests from browsers.
// Check if REQUEST_METHOD is set before accessing it
if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Include the main configuration file which loads environment variables and other helpers.
require_once __DIR__ . '/config.php';
?>