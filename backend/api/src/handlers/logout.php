<?php
// Included from /api/index.php

// This script handles user logout.

// --- Session Start ---
// A session must be started to access and then destroy it.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// --- Logout Logic ---
// Unset all of the session variables.
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

// --- Success Response ---
jsonResponse(200, ['status' => 'success', 'message' => 'Logout successful.']);
