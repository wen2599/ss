<?php
require_once __DIR__ . '/env_loader.php';

// --- 安全性检查 ---
$auth_header = $_SERVER['HTTP_X_AUTH_TOKEN'] ?? '';
if ($auth_header !== $_ENV['WORKER_SECRET_KEY']) {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit;
}

// 只接受 POST 请求
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed']);
    exit;
}

// 获取原始 POST 数据
$json_data = file_get_contents('php://input');
$data = json_decode($json_data, true);

if (json_last_error() !== JSON_ERROR_NONE || !isset($data['from'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Bad Request: Invalid JSON']);
    exit;
}

// 连接数据库
require_once __DIR__ . '/db_connection.php';

$stmt = $conn->prepare("INSERT INTO emails (message_id, from_address, subject, body_text, body_html) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE subject=VALUES(subject)");

$message_id = $data['message_id'] ?? uniqid();
$from = $data['from'];
$subject = $data['subject'] ?? '';
$text = $data['text'] ?? '';
$html = $data['html'] ?? '';

$stmt->bind_param("sssss", $message_id, $from, $subject, $text, $html);

if ($stmt->execute()) {
    http_response_code(201);
    echo json_encode(['status' => 'success', 'message' => 'Email saved.']);
} else {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Failed to save email.']);
}

$stmt->close();
$conn->close();
?>