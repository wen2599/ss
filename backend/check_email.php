<?php

require_once __DIR__ . '/api_header.php';

$response = ['is_authorized' => false];

$data = json_decode(file_get_contents('php://input'), true);
$email = $data['email'] ?? null;

if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid or missing email']);
    exit();
}

// Use the existing function from db_operations.php
if (isEmailAuthorized($email)) {
    $response['is_authorized'] = true;
}

echo json_encode($response);

?>
