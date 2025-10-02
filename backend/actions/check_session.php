<?php
// Action: Check user's session status

require_once __DIR__ . '/../init.php';

if (isset($_SESSION['user_id']) && isset($_SESSION['user_email'])) {
    echo json_encode([
        'isAuthenticated' => true,
        'user' => [
            'id' => $_SESSION['user_id'],
            'email' => $_SESSION['user_email']
        ]
    ]);
} else {
    echo json_encode(['isAuthenticated' => false]);
}
?>
