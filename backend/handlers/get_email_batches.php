<?php
// backend/handlers/get_email_batches.php

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    send_json_response(['status' => 'error', 'message' => 'Invalid request method.'], 405);
}

if (!isset($current_user_id)) {
     send_json_response(['status' => 'error', 'message' => 'Authentication required.'], 401);
}

$email_id = $_GET['email_id'] ?? null;
if (empty($email_id)) {
    send_json_response(['status' => 'error', 'message' => 'Email ID is required.'], 400);
}

try {
    // 验证这封邮件是否属于当前用户
    $stmt = $pdo->prepare("SELECT id FROM user_emails WHERE id = ? AND user_id = ?");
    $stmt->execute([$email_id, $current_user_id]);
    if (!$stmt->fetch()) {
        send_json_response(['status' => 'error', 'message' => 'Email not found or access denied.'], 404);
    }
    
    // 获取该邮件下的所有批次
    $stmt = $pdo->prepare(
        "SELECT * FROM email_batches WHERE email_id = ? ORDER BY id ASC"
    );
    $stmt->execute([$email_id]);
    $batches = $stmt->fetchAll();
    
    send_json_response(['status' => 'success', 'data' => $batches]);

} catch (PDOException $e) {
    error_log("Get email batches error: " . $e->getMessage());
    send_json_response(['status' => 'error', 'message' => 'Failed to fetch email batches.'], 500);
}