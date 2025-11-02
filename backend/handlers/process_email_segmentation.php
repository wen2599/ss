<?php
// backend/handlers/process_email_segmentation.php

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_json_response(['status' => 'error', 'message' => 'Invalid request method.'], 405);
}
if (!isset($current_user_id)) {
     send_json_response(['status' => 'error', 'message' => 'Authentication required.'], 401);
}

$input = json_decode(file_get_contents('php://input'), true);
$email_id = $input['email_id'] ?? null;
if (empty($email_id)) {
    send_json_response(['status' => 'error', 'message' => 'Email ID is required.'], 400);
}

try {
    $pdo->beginTransaction();

    // 1. 验证邮件所有权并获取内容
    $stmt = $pdo->prepare("SELECT raw_email FROM user_emails WHERE id = ? AND user_id = ? AND status = 'new'");
    $stmt->execute([$email_id, $current_user_id]);
    $email = $stmt->fetch();

    if (!$email) {
        throw new Exception('Email not found, is not new, or access denied.');
    }

    // 2. 简单地提取邮件正文 (这是一个简化的解析，实际可能更复杂)
    $raw_content = $email['raw_email'];
    $body = $raw_content;
    if (strpos($raw_content, "\r\n\r\n") !== false) {
        $parts = explode("\r\n\r\n", $raw_content, 2);
        $body = $parts[1];
    }
    // 清理常见的MIME编码和HTML标签
    $body = preg_replace('/=\s*(\r\n|\n)/', '', $body); // 移除MIME的软换行
    $body = quoted_printable_decode($body);
    $body = strip_tags($body);
    $body = trim($body);

    // 3. 定义分段的正则表达式 (识别时间点)
    $pattern = '/((\d{1,2}|[一二三四五六七八九十壹贰叁肆伍陆柒捌玖拾])\s*点\s*(\d{1,2}|[一二三四五六七八九十\d零〇拾]{1,3})?\s*分?)/u';
    $parts = preg_split($pattern, $body, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
    
    // 4. 处理分割后的片段并创建批次
    $current_timestamp = null;
    $batches_created = 0;
    foreach ($parts as $part) {
        $part = trim($part);
        if (empty($part)) continue;

        if (preg_match($pattern, $part)) {
            // 这是一个时间戳
            $current_timestamp = $part;
        } else {
            // 这是一个投注内容
            $batch_content = $part;
            
            $insertStmt = $pdo->prepare(
                "INSERT INTO email_batches (email_id, user_id, batch_content, timestamp_in_email, status) VALUES (?, ?, ?, ?, 'new')"
            );
            $insertStmt->execute([$email_id, $current_user_id, $batch_content, $current_timestamp]);
            $batches_created++;
            $current_timestamp = null; // 时间戳只关联其后的第一个内容块
        }
    }

    if ($batches_created > 0) {
        // 5. 更新邮件主状态
        $updateStmt = $pdo->prepare("UPDATE user_emails SET status = 'processing' WHERE id = ?");
        $updateStmt->execute([$email_id]);
    }
    
    $pdo->commit();
    send_json_response(['status' => 'success', 'message' => "Successfully created {$batches_created} batches."]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Email segmentation error: " . $e->getMessage());
    send_json_response(['status' => 'error', 'message' => $e->getMessage()], 500);
}