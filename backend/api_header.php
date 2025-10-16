<?php
// api_header.php

require_once __DIR__ . '/config.php'; // Ensure config is loaded first

// Debugging array to collect info
$debug_info = [];

// Set session cookie parameters before starting the session.
// This ensures the cookie is sent to the correct domain and path, and is secure.
session_set_cookie_params([
    'lifetime' => 3600, // Session lifetime in seconds (e.g., 1 hour)
    'path' => '/', // The path on the server in which the cookie will be available on.
    'domain' => '', // 一定要设为空字符串，保证 cookie 属于 worker 域名
    'secure' => true, // Only send the cookie over HTTPS
    'httponly' => true, // Prevent JavaScript access to the cookie
    'samesite' => 'None' // Must be 'None' for cross-site requests
]);

// Start the session.
session_start();

// Collect debugging info
$debug_info['api_header_request_origin'] = $_SERVER['HTTP_ORIGIN'] ?? 'N/A';
$debug_info['api_header_session_id'] = session_id();
$debug_info['api_header_session_data'] = $_SESSION;
error_log("API Header Debug: Session ID - " . session_id() . ", User ID - " . ($_SESSION['user_id'] ?? 'N/A') . ", Origin - " . ($_SERVER['HTTP_ORIGIN'] ?? 'N/A'));

// --- CORS and Security Headers ---
$allowed_origins = ['http://localhost:3000', getenv('FRONTEND_DOMAIN') ?: '' ];
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
?>
