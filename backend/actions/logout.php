<?php
/**
 * Action: logout
 *
 * This script handles user logout by destroying their current session.
 *
 * HTTP Method: POST
 *
 * Response:
 * - On success: { "success": true, "message": "Logged out successfully." }
 */

// The main router (index.php) handles initialization.
// Global variables $pdo and $log are available.

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // Method Not Allowed
    $log->warning("Method not allowed for logout.", ['method' => $_SERVER['REQUEST_METHOD']]);
    echo json_encode(['success' => false, 'error' => 'Method Not Allowed']);
    exit();
}

$userId = $_SESSION['user_id'] ?? null;
$log->info("User logging out.", ['user_id' => $userId]);

// 1. Unset all session variables.
$_SESSION = [];

// 2. Delete the session cookie from the browser.
// This is done by setting a cookie with a name that matches the session name,
// a blank value, and an expiration date in the past.
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// 3. Finally, destroy the session on the server.
session_destroy();

http_response_code(200);
echo json_encode(['success' => true, 'message' => 'Logged out successfully.']);
?>