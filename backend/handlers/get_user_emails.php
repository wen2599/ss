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
    // This handler now only returns email metadata.
    // The raw content is fetched on demand by a different handler.
    $stmt = $pdo->prepare(
        "SELECT id, from_sender, subject, received_at, status FROM user_emails WHERE user_id = ? ORDER BY received_at DESC"
    );
    $stmt->execute([$current_user_id]);
    $emails = $stmt->fetchAll();
    
    send_json_response(['status' => 'success', 'data' => $emails]);

} catch (PDOException $e) {
    error_log("Get user emails error: " . $e->getMessage());
    send_json_response(['status' => 'error', 'message' => 'Failed to fetch emails.'], 500);
}