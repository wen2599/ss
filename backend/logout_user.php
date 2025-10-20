<?php
require_once __DIR__ . '/bootstrap.php';

write_log("------ logout_user.php Entry Point ------");

// --- Logout Logic ---
// Unset all session variables.
$_SESSION = [];

// If it's desired to kill the session, also delete the session cookie.
// Note: This will destroy the session, and not just the session data!
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Finally, destroy the session.
session_destroy();

write_log("User logged out successfully. Session destroyed.");
json_response('success', 'Logout successful!');

write_log("------ logout_user.php Exit Point ------");

?>