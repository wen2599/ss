<?php
// backend/handlers/get_user_emails.php

// (这个文件需要被 api.php 包含在一个受保护的路由下)

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    send_json_response(['status' => 'error', 'message' => 'Invalid request method.'], 405);
}

// $current_user_id 会在 api.php 的路由保护逻辑中设置
if (!isset($current_user_id)) {
     send_json_response(['status' => 'error', 'message' => 'Authentication required.'], 401);
}

try {
    // 为了提高效率，我们在这里解析邮件的From和Subject
    // 尽管MIME解析可能很复杂，我们可以用一个简单的正则来提取基本信息
    $stmt = $pdo->prepare(
        "SELECT id, raw_email, received_at, status FROM user_emails WHERE user_id = ? ORDER BY received_at DESC"
    );
    $stmt->execute([$current_user_id]);
    $emails_raw = $stmt->fetchAll();

    $emails_processed = array_map(function($email) {
        $subject = ' (No Subject)';
        $from = ' (Unknown Sender)';

        // Simple regex to extract Subject and From headers
        if (preg_match('/^Subject:\s*(.*)$/im', $email['raw_email'], $matches)) {
            // Decode subject if it's encoded (e.g., =?UTF-8?B?...?=)
            $subject = mb_decode_mimeheader($matches[1]);
        }
        if (preg_match('/^From:\s*(.*)$/im', $email['raw_email'], $matches)) {
            $from = $matches[1];
        }

        return [
            'id' => $email['id'],
            'from' => $from,
            'subject' => $subject,
            'received_at' => $email['received_at'],
            'status' => $email['status']
        ];
    }, $emails_raw);
    
    send_json_response(['status' => 'success', 'data' => $emails_processed]);

} catch (PDOException $e) {
    error_log("Get user emails error: " . $e->getMessage());
    send_json_response(['status' => 'error', 'message' => 'Failed to fetch emails.'], 500);
}