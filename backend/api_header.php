<?php
// backend/api_header.php

// --- CORS and Session Configuration for Cross-Domain Communication ---

// The Cloudflare Worker does not handle CORS, so the PHP backend must.
// We allow requests from the specific frontend origin.
$frontend_url = getenv('FRONTEND_URL') ?: 'https://ss.wenxiuxiu.eu.org';
header("Access-Control-Allow-Origin: " . $frontend_url);
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, X-Requested-With, Authorization");

// Handle pre-flight OPTIONS requests sent by browsers.
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204); // No Content
    exit;
}

// Set a consistent JSON content type for all API responses.
header('Content-Type: application/json');

// --- Session Cookie Configuration ---
// This configuration MUST be set before session_start() is called.
session_set_cookie_params([
    'lifetime' => 86400, // 24 hours
    'path' => '/',
    // 'domain' is intentionally left out to let the browser handle it,
    // as the domains are different and not subdomains of a parent.
    'secure' => true,    // Cookie must be sent over HTTPS.
    'httponly' => true,  // Cookie cannot be accessed by client-side scripts.
    'samesite' => 'None' // Required for any cross-domain cookie usage.
]);

// Start or resume the session.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// After a successful login, a 'regenerate_id' flag is set.
// On the *next* request, we see the flag and regenerate the session ID
// to protect against session fixation attacks.
if (isset($_SESSION['regenerate_id']) && $_SESSION['regenerate_id'] === true) {
    session_regenerate_id(true);
    unset($_SESSION['regenerate_id']);
}

// Ensure the main application configuration is loaded.
require_once __DIR__ . '/config.php';