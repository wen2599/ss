<?php
// backend/api/save_result.php

require_once __DIR__ . '/../config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: ' . ALLOWED_ORIGIN);
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// 验证 API 密钥
$auth_header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
if ($auth_header !== 'Bearer ' . API_KEY) {
    http_response_code(401);
    echo json_encode(['error' => '未授权访问']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['numbers']) || !isset($input['draw_time'])) {
    http_response_code(400);
    echo json_encode(['error' => '缺少必要参数: numbers 和 draw_time']);
    exit;
}

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER, 
        DB_PASS
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $stmt = $pdo->prepare("
        INSERT INTO lottery_results (draw_time, numbers, channel_name, message_id) 
        VALUES (?, ?, ?, ?)
    ");
    
    $success = $stmt->execute([
        $input['draw_time'],
        $input['numbers'],
        $input['channel_name'] ?? null,
        $input['message_id'] ?? null
    ]);
    
    if ($success) {
        echo json_encode(['success' => true, 'id' => $pdo->lastInsertId()]);
    } else {
        http_response_code(500);
        echo json_encode(['error' => '保存失败']);
    }
    
} catch(PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => '数据库错误: ' . $e->getMessage()]);
}
?>
