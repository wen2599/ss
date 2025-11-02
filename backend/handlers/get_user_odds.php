<?php
// backend/handlers/get_user_odds.php

// (此文件被 api.php 在受保护的路由中包含)
if (!isset($current_user_id)) {
     send_json_response(['status' => 'error', 'message' => 'Authentication required.'], 401);
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    send_json_response(['status' => 'error', 'message' => 'Invalid request method.'], 405);
}

try {
    $stmt = $pdo->prepare("SELECT odds_settings FROM users WHERE id = ?");
    $stmt->execute([$current_user_id]);
    $odds_json = $stmt->fetchColumn();
    
    // 如果数据库中是NULL或空字符串，返回一个空对象，确保前端总能收到一个对象
    $odds_data = ($odds_json) ? json_decode($odds_json, true) : new stdClass();

    send_json_response(['status' => 'success', 'data' => $odds_data]);

} catch (PDOException $e) {
    error_log("Get user odds error for user {$current_user_id}: " . $e->getMessage());
    send_json_response(['status' => 'error', 'message' => 'Failed to fetch user settings.'], 500);
}
