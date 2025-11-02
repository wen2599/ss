<?php
// backend/handlers/email_receiver.php

// 确保这是 POST 请求
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_json_response(['status' => 'error', 'message' => 'Invalid request method.'], 405);
}

// 获取从 Worker 发来的 JSON 数据
$input = json_decode(file_get_contents('php://input'), true);

$sender_email = $input['sender_email'] ?? null;
$raw_email = $input['raw_email'] ?? null;

// 基本验证
if (empty($sender_email) || !filter_var($sender_email, FILTER_VALIDATE_EMAIL)) {
    send_json_response(['status' => 'error', 'message' => 'Invalid or missing sender email.'], 400);
}

if (empty($raw_email)) {
    send_json_response(['status' => 'error', 'message' => 'Missing raw email content.'], 400);
}

// 核心逻辑：根据发件人邮箱查找用户
try {
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND status = 'active'");
    $stmt->execute([$sender_email]);
    $user = $stmt->fetch();

    if ($user) {
        // 找到了用户，将邮件存入数据库
        $user_id = $user['id'];
        
        // Parse From and Subject from raw email
        $from = ' (Unknown Sender)';
        $subject = ' (No Subject)';
        if (preg_match('/^From:\s*(.*)$/im', $raw_email, $matches)) {
            $from = mb_decode_mimeheader($matches[1]);
        }
        if (preg_match('/^Subject:\s*(.*)$/im', $raw_email, $matches)) {
            $subject = mb_decode_mimeheader($matches[1]);
        }

        $insertStmt = $pdo->prepare("INSERT INTO user_emails (user_id, from_sender, subject, raw_email) VALUES (?, ?, ?, ?)");
        $insertStmt->execute([$user_id, $from, $subject, $raw_email]);

        // 向 Worker 返回成功响应
        send_json_response(['status' => 'success', 'message' => 'Email received and stored.']);
    } else {
        // 没有找到匹配的用户，静默丢弃，但向 Worker 返回成功，避免邮件被退回
        // 这样做是为了不让非注册用户知道他们的邮件被系统处理了
        // 也可以选择返回错误，让 Worker 退信，取决于业务需求
        // 这里的200 OK表示“我们收到了，处理完了”，即使处理方式是“丢弃”
        send_json_response(['status' => 'accepted_but_discarded', 'message' => 'Sender not registered.']);
    }

} catch (PDOException $e) {
    // 记录服务器内部错误，并向 Worker 返回错误，使其可以退信
    // 在生产环境中，应该记录$e->getMessage()到日志文件
    error_log("Email receiver database error: " . $e->getMessage());
    send_json_response(['status' => 'error', 'message' => 'Internal server error.'], 500);
}