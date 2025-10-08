<?php
// backend/endpoints/check_session.php

session_start();

// Bootstrap the application
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../config.php';

/**
 * Sends a JSON response with the correct headers.
 *
 * @param array $data The data to encode as JSON.
 * @param int $status_code The HTTP status code to send.
 */
function send_json_response(array $data, int $status_code = 200): void {
    header('Content-Type: application/json');
    http_response_code($status_code);
    echo json_encode($data);
    exit;
}

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