<?php
// backend/endpoints/validate_user_email.php

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json');

// 1. Authenticate the worker's request
$auth_header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
if ($auth_header !== 'Bearer ' . WORKER_SECRET) {
    http_response_code(403); // Forbidden
    echo json_encode(['is_valid' => false, 'error' => 'Invalid or missing authorization token.']);
    exit;
}

// 2. Get the email from the query parameter
$email = filter_input(INPUT_GET, 'email', FILTER_VALIDATE_EMAIL);
if (!$email) {
    http_response_code(400); // Bad Request
    echo json_encode(['is_valid' => false, 'error' => 'Email parameter is missing or invalid.']);
    exit;
}

// 3. Check the database
$conn = get_db_connection();
if (!$conn) {
    http_response_code(500); // Server Error
    echo json_encode(['is_valid' => false, 'error' => 'Database connection failed.']);
    exit;
}

$stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
$stmt->bind_param('s', $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    // Email exists in the users table
    echo json_encode(['is_valid' => true]);
} else {
    // Email does not exist
    echo json_encode(['is_valid' => false, 'error' => 'Email not registered.']);
}

$stmt->close();
$conn->close();
?>
