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

// Since the authorization check is removed, we can just return true.
$response['is_authorized'] = true;
echo json_encode($response);

?>
