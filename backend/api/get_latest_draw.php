<?php
// backend/api/get_latest_draw.php
require_once 'config.php';
require_once 'db_connect.php'; // Use the new connector
header('Content-Type: application/json');

$conn = db_connect();
if (!$conn) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection failed.']);
    exit;
}

try {
    $result = $conn->query("SELECT period, winning_numbers, draw_time FROM draws ORDER BY draw_time DESC LIMIT 1");
    $latest_draw = $result->fetch_assoc();

    if ($latest_draw) {
        echo json_encode(['success' => true, 'data' => $latest_draw]);
    } else {
        echo json_encode(['success' => false, 'message' => 'No draw data found.']);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database query failed.']);
}

$conn->close();
?>
