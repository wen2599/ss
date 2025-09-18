<?php
// backend/api/index.php
// This script acts as a Front Controller for all API requests.

// The api_header includes configuration, error handlers, and session start.
require_once __DIR__ . '/api_header.php';

/**
 * A whitelist of scripts that are allowed to be executed through this router.
 * This is a security measure to prevent arbitrary file inclusion attacks.
 * Only public-facing API endpoints called by the frontend should be here.
 * @var array
 */
$allowed_endpoints = [
    'api.php',                 // Handles file uploads for new bets
    'check_session.php',       // Checks if a user is logged in
    'get_bets.php',            // Gets a user's bet history
    'get_latest_draws.php',    // Gets the latest lottery results
    'get_rules.php',           // Gets lottery rules (e.g., for parsing)
    'is_user_registered.php',  // Checks if an email is already registered
    'login.php',               // Handles user login
    'logout.php',              // Handles user logout
    'register.php',            // Handles new user registration
    'update_rules.php',        // Admin action to update rules
];

// Get the requested endpoint from the query string passed by the Cloudflare Worker.
$endpoint = $_GET['endpoint'] ?? null;

// Validate the requested endpoint.
if ($endpoint && in_array($endpoint, $allowed_endpoints)) {
    // If the endpoint is in the whitelist, execute it.
    // The included script will have access to all constants and functions from api_header.php
    require_once __DIR__ . '/' . $endpoint;
} else {
    // If the endpoint is not provided or not in the whitelist, return a 404 Not Found error.
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'API endpoint not found.']);
}

// The included script is responsible for generating its own output.
// No code should run after the require_once call.
?>
