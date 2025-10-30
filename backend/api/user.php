<?php
require_once '../db.php';

function deleteUser() {
    global $pdo;
    $data = json_decode(file_get_contents('php://input'), true);
    $userId = $data['user_id'];
    // 认证: 只管理员 (从 bot 调用，检查 key 或 something)
    $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$userId]);
    echo json_encode(['success' => true]);
}