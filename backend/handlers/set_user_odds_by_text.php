<?php
// backend/handlers/set_user_odds_by_text.php

if (!isset($current_user_id)) {
     send_json_response(['status' => 'error', 'message' => 'Authentication required.'], 401);
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_json_response(['status' => 'error', 'message' => 'Invalid request method.'], 405);
}

// 引入AI解析工具
require_once __DIR__ . '/../utils/ai_parsers.php'; 

// 获取前端发送的原始JSON请求体
$input = json_decode(file_get_contents('php://input'), true);
$odds_text = $input['odds_text'] ?? null;

if (empty($odds_text)) {
    send_json_response(['status' => 'error', 'message' => 'Odds text field cannot be empty.'], 400);
}

try {
    // 调用我们在上一步创建的AI解析函数
    $parsed_odds_json = parseOddsWithAI($odds_text);
    
    // AI函数内部已经处理了JSON验证和异常，这里我们只需确保存储
    $stmt = $pdo->prepare("UPDATE users SET odds_settings = ? WHERE id = ?");
    $stmt->execute([$parsed_odds_json, $current_user_id]);

    // 检查是否有行受到影响
    if ($stmt->rowCount() > 0) {
        send_json_response(['status' => 'success', 'message' => 'Your odds have been updated successfully using AI.']);
    } else {
        // 理论上，如果用户存在，这不应该发生，但作为一种健壮性检查
        send_json_response(['status' => 'error', 'message' => 'Failed to update settings. User not found or settings unchanged.'], 500);
    }

} catch (Exception $e) {
    // 捕获来自 parseOddsWithAI 的异常 (例如AI未配置, cURL错误, AI返回错误等)
    error_log("Set user odds by text error for user {$current_user_id}: " . $e->getMessage());
    send_json_response(['status' => 'error', 'message' => "An error occurred while processing with AI: " . $e->getMessage()], 500);
}
