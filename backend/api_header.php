<?php
// backend/api_header.php

// --- CORS and Session Configuration for Cross-Domain Communication ---

// Allow requests from the specific frontend origin.
// Note: Using '*' is insecure. Always specify the exact domain.
$frontend_url = getenv('FRONTEND_URL') ?: 'https://ss.wenxiuxiu.eu.org';
header("Access-Control-Allow-Origin: " . $frontend_url);

// Allow credentials (like cookies) to be sent with requests.
header("Access-Control-Allow-Credentials: true");

// Specify allowed HTTP methods.
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");

// Specify allowed headers.
header("Access-Control-Allow-Headers: Content-Type, X-Requested-With, Authorization");

// Handle pre-flight OPTIONS requests from the browser.
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204); // No Content
    exit;
}

// Set a consistent JSON content type for all API responses.
header('Content-Type: application/json');

// --- Session Cookie Configuration ---
// This MUST be done *before* session_start() is called.
session_set_cookie_params([
    'lifetime' => 86400, // 24 hours
    'path' => '/',
    // Set the parent domain to allow the cookie to be shared across subdomains if needed.
    // IMPORTANT: This requires the frontend and backend to be on subdomains of the same parent domain,
    // or you must rely on the browser's default behavior if they are completely different.
    // For ss.wenxiuxiu.eu.org and wenge.cloudns.ch, they are different, so we comment this out
    // and rely on `SameSite=None` and `Secure`.
    // 'domain' => '.your-parent-domain.com',
    'secure' => true,    // The cookie must be sent over HTTPS.
    'httponly' => true,  // The cookie cannot be accessed by client-side scripts.
    'samesite' => 'None' // Required for cross-domain cookies.
]);

// Start or resume the session to access user authentication data.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Regenerate session ID on login to prevent session fixation attacks.
// We can check for a flag set during login to trigger this.
if (isset($_SESSION['regenerate_id']) && $_SESSION['regenerate_id'] === true) {
    session_regenerate_id(true);
    unset($_SESSION['regenerate_id']);
}

// Ensure errors are handled, and config is loaded.
require_once __DIR__ . '/config.php';