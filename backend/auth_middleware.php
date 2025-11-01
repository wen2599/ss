<?php
require_once __DIR__ . '/db_connection.php';

function validateToken() {
    $headers = getallheaders();
    if (!isset($headers['Authorization'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit;
    }

    $token = str_replace('Bearer ', '', $headers['Authorization']);
    $conn = getDbConnection();

    // In a real application, you'd have a more secure token validation mechanism
    $stmt = $conn->prepare("SELECT user_id FROM tokens WHERE token = ? AND expires_at > NOW()");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();
    $token_data = $result->fetch_assoc();

    if (!$token_data) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit;
    }

    return $token_data['user_id'];
}
