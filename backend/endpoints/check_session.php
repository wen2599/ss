<?php
// backend/endpoints/check_session.php

session_start();

// Bootstrap the application
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/helpers.php';

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