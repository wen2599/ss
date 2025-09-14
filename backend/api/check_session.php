<?php
// backend/api/check_session.php
require_once 'config.php'; // Not strictly needed, but good practice
header('Content-Type: application/json');

session_set_cookie_params([
    'lifetime' => 86400,
    'path' => '/',
    'secure' => true,
    'httponly' => true,
    'samesite' => 'None'
]);
session_start();

if (isset($_SESSION['user_id']) && isset($_SESSION['username'])) {
    // User is logged in
    echo json_encode([
        'success' => true,
        'loggedIn' => true,
        'user' => [
            'id' => $_SESSION['user_id'],
            'username' => $_SESSION['username']
        ]
    ]);
} else {
    // User is not logged in
    echo json_encode([
        'success' => true,
        'loggedIn' => false
    ]);
}
?>
