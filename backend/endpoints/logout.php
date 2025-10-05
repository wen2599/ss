<?php
// backend/endpoints/logout.php

// This endpoint logs the user out by destroying their session.

// Start the session to access and destroy it.
session_start();

// Unset all session variables.
$_SESSION = [];

// Destroy the session cookie.
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Finally, destroy the session.
session_destroy();

send_json_response(['success' => true, 'message' => 'Logged out successfully.']);
?>