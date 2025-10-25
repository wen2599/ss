<?php

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/jwt_helper.php';

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
$token = str_replace('Bearer ', '', $authHeader);

if (!$token) {
    http_response_code(401);
    echo json_encode(["message" => "Authentication token not provided."]);
    exit;
}

try {
    $decoded = validate_jwt($token);
    $userId = $decoded['data']['userId'];

    global $db_connection;
    $stmt = $db_connection->prepare("SELECT id, from_address, subject, body, received_at, extracted_data FROM emails WHERE user_id = ? ORDER BY received_at DESC");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $records = [];
    while ($row = $result->fetch_assoc()) {
        $records[] = $row;
    }
    
    $stmt->close();
    $db_connection->close();

    http_response_code(200);
    echo json_encode($records);

} catch (Exception $e) {
    http_response_code(401);
    echo json_encode(["message" => "Authentication failed: " . $e->getMessage()]);
}
