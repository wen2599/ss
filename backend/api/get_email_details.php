<?php
require_once __DIR__ . '/cors_headers.php';
require_once __DIR__ . '/../db_connection.php';

$email_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$email_id) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid email ID.']);
    exit;
}

$stmt = $conn->prepare("SELECT * FROM emails WHERE id = ?");
$stmt->bind_param("i", $email_id);
$stmt->execute();
$result = $stmt->get_result();
$email = $result->fetch_assoc();

if (!$email) {
    http_response_code(404);
    echo json_encode(['error' => 'Email not found.']);
} else {
    echo json_encode($email);
}

$stmt->close();
$conn->close();