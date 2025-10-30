<?php
require_once '../db.php';

function insertEmail() {
    global $pdo;
    $data = json_decode(file_get_contents('php://input'), true);  // 从 Workers POST
    $body = $data['body'];
    $subject = $data['subject'];
    $from = $data['from'];
    // 检查 subject 是否含 token (注册验证)
    if (strpos($subject, 'token:') === 0) {
        $token = substr($subject, 6);
        // 验证 token，创建用户
        $stmt = $pdo->prepare("SELECT email FROM temp_tokens WHERE token = ?");
        $stmt->execute([$token]);
        $email = $stmt->fetchColumn();
        if ($email) {
            $pdo->prepare("INSERT INTO users (email) VALUES (?)")->execute([$email]);
            $userId = $pdo->lastInsertId();
            $pdo->prepare("DELETE FROM temp_tokens WHERE token = ?")->execute([$token]);
        } else {
            json_error('Invalid token');
        }
    } else {
        // 正常邮件，找用户 (from_email 匹配 users.email 或 authorized_emails)
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? UNION SELECT user_id FROM authorized_emails WHERE email = ?");
        $stmt->execute([$from, $from]);
        $userId = $stmt->fetchColumn() ?: null;  // 如果无用户，存 null
    }
    $stmt = $pdo->prepare("INSERT INTO emails (user_id, subject, body, from_email) VALUES (?, ?, ?, ?)");
    $stmt->execute([$userId, $subject, $body, $from]);
    echo json_encode(['success' => true]);

    // 如果是授权邮箱，自动触发识别
    $emailId = $pdo->lastInsertId();
    if ($pdo->query("SELECT COUNT(*) FROM authorized_emails WHERE email = '$from'")->fetchColumn() > 0) {
        recognizeEmail($emailId);  // 调用 ai.php 函数
    }
}

function getEmails() {
    global $pdo;
    $userId = validateJWT($_SERVER['HTTP_AUTHORIZATION'])['id'];  // 需认证
    $stmt = $pdo->prepare("SELECT * FROM emails WHERE user_id = ?");
    $stmt->execute([$userId]);
    echo json_encode($stmt->fetchAll());
}