<?php
// backend/endpoints/logout.php

// Bootstrap the application
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/helpers.php';

// This endpoint logs the user out by destroying their session.

// Session is started globally in index.php.

// Unset all session variables.
$_SESSION = [];

// Destroy the session cookie by setting its expiration to the past.
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Destroy the session data on the server.
session_destroy();

send_json_response(['success' => true, 'message' => 'Logged out successfully.']);
?>