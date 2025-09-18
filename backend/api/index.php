<?php
// backend/api/index.php
// This script acts as a Front Controller for all API requests.

// The api_header includes configuration, error handlers, and session start.
require_once __DIR__ . '/api_header.php';

/**
 * Maps a public 'action' name to a specific PHP script on the backend.
 * This is a security measure to prevent arbitrary file inclusion and to create
 * a clean, stable API interface. Only actions defined here are executable.
 * @var array
 */
$allowed_actions = [
    'api' => 'api.php',                 // Handles file uploads for new bets
    'check_session' => 'check_session.php',       // Checks if a user is logged in
    'get_bets' => 'get_bets.php',            // Gets a user's bet history
    'get_latest_draws' => 'get_latest_draws.php',    // Gets the latest lottery results
    'get_rules' => 'get_rules.php',           // Gets lottery rules (e.g., for parsing)
    'is_user_registered' => 'is_user_registered.php',  // Checks if an email is already registered
    'login' => 'login.php',               // Handles user login
    'logout' => 'logout.php',              // Handles user logout
    'register' => 'register.php',            // Handles new user registration
    'update_rules' => 'update_rules.php',        // Admin action to update rules
];

// Get the requested action from the query string passed by the Cloudflare Worker.
$action = $_GET['action'] ?? null;

// Validate the requested action.
if ($action && isset($allowed_actions[$action])) {
    // If the action is in the whitelist, execute the corresponding script.
    $script_to_include = __DIR__ . '/' . $allowed_actions[$action];

    // Double-check that the file actually exists before trying to include it.
    if (file_exists($script_to_include)) {
        require_once $script_to_include;
    } else {
        // This case should ideally not be reached if the map is correct.
        log_error("Router error: Action '{$action}' is allowed but file '{$allowed_actions[$action]}' does not exist.");
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Server configuration error.']);
    }
} else {
    // If the action is not provided or not in the whitelist, return a 404 Not Found error.
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'API action not found.']);
}

// The included script is responsible for generating its own output.
// No code should run after the require_once call.
?>
