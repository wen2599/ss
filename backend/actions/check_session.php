<?php
// Checks if a user session is active
require_once __DIR__ . '/../init.php';

if (isset($_SESSION['user_id']) && isset($_SESSION['username'])) {
    // Session is active, return user data
    json_response([
        'is_logged_in' => true,
        'user' => [
            'id' => $_SESSION['user_id'],
            'username' => $_SESSION['username'],
            'is_admin' => $_SESSION['is_admin'] ?? false
        ]
    ], 200);
} else {
    // No active session
    json_response(['is_logged_in' => false], 200);
}
?>