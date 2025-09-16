<?php
// backend/api/is_user_registered.php

// This endpoint is for the Cloudflare Worker to check if an email is registered.
// It is secured with a shared secret.

require_once __DIR__ . '/database.php';

header('Content-Type: application/json');

// --- Authentication ---
$worker_secret = $_GET['worker_secret'] ?? null;
if ($worker_secret !== 'A_VERY_SECRET_KEY') {
    http_response_code(403); // Forbidden
    echo json_encode(['success' => false, 'message' => 'Invalid or missing secret.']);
    exit();
}

$email = $_GET['email'] ?? null;

if (empty($email)) {
    http_response_code(400); // Bad Request
    echo json_encode(['success' => false, 'message' => 'Email parameter is required.']);
    exit();
}

$response = ['success' => false, 'is_registered' => false];

try {
    $pdo = getDbConnection();
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = :email");
    $stmt->execute([':email' => $email]);

    if ($stmt->fetch()) {
        $response = ['success' => true, 'is_registered' => true];
    } else {
        $response = ['success' => true, 'is_registered' => false];
    }

} catch (PDOException $e) {
    http_response_code(500);
    $response['message'] = 'Database error.';
}

echo json_encode($response);
?>
