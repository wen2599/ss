<?php
// backend/check_session.php
// Checks if a user session is active and valid using the refactored function.

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/db_operations.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $user = verify_user_session($pdo);

    if ($user) {
        http_response_code(200);
        echo json_encode(['isLoggedIn' => true, 'user' => $user]);
    } else {
        http_response_code(401);
        echo json_encode(['isLoggedIn' => false, 'error' => 'Invalid or expired session.']);
    }
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Invalid request method.']);
}
