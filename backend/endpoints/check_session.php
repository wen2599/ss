<?php
// backend/endpoints/check_session.php

// Bootstrap the application
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/helpers.php';

// This endpoint is used to check if a user is currently logged in.
// It must be called with credentials to access the session cookie.

// Start the session to access session variables.
session_start();

if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {
    // The user is logged in.
    send_json_response([
        'loggedin' => true,
        'user' => [
            'username' => $_SESSION['username'] ?? 'Unknown'
        ]
    ]);
} else {
    // The user is not logged in.
    send_json_response(['loggedin' => false]);
}
?>