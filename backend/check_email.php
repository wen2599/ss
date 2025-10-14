<?php

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); // Allow requests from any origin (adjust for production)
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle pre-flight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit();
}

require_once __DIR__ . '/db_operations.php';

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
