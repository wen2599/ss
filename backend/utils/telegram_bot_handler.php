<?php
// backend/utils/telegram_bot_handler.php

/**
 * 发送消息给管理员
 * @param string $message 要发送的文本消息
 * @param array|null $reply_markup 键盘菜单 (可选)
 * @return bool|string API响应或false
 */
function sendTelegramMessage($message, $reply_markup = null) {
    $admin_id = getenv('TELEGRAM_ADMIN_ID') ?: null;
    $bot_token = getenv('TELEGRAM_BOT_TOKEN') ?: null;

    error_log("sendTelegramMessage: Attempting to send message.");
    error_log("sendTelegramMessage: TELEGRAM_ADMIN_ID from .env: [" . ($admin_id ?? 'NOT SET') . "]");
    error_log("sendTelegramMessage: TELEGRAM_BOT_TOKEN from .env: [" . ($bot_token ? substr($bot_token, 0, 5) . '...' : 'NOT SET') . "]"); // Mask token in logs

    if (empty($admin_id) || empty($bot_token)) {
        error_log("sendTelegramMessage: ADMIN_ID or BOT_TOKEN is not set in .env. Cannot send message.");
        return false;
    }

    $url = "https://api.telegram.org/bot{$bot_token}/sendMessage";

    $post_fields = [
        'chat_id' => $admin_id,
        'text' => $message,
        'parse_mode' => 'Markdown'
    ];

    if ($reply_markup) {
        $post_fields['reply_markup'] = json_encode($reply_markup);
    }

    error_log("sendTelegramMessage: Sending to URL: " . $url);
    error_log("sendTelegramMessage: Post Fields: " . json_encode($post_fields));

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post_fields));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10); // 增加超时设置

    $result = curl_exec($ch);
    
    if (curl_errno($ch)) {
        $error_msg = curl_error($ch);
        error_log("sendTelegramMessage: cURL Error: " . $error_msg);
    } else {
        error_log("sendTelegramMessage: Telegram API Response: " . $result);
    }
    curl_close($ch);
    return $result;
}

/**
 * 回答回调查询 (用于处理内联按钮点击)
 * @param string $callback_query_id 回调查询的ID
 * @param string|null $text 提示文本 (可选)
 * @return bool|string API响应或false
 */
function answerCallbackQuery($callback_query_id, $text = null) {
    $bot_token = getenv('TELEGRAM_BOT_TOKEN') ?: null;

    error_log("answerCallbackQuery: Attempting to answer callback query.");
    error_log("answerCallbackQuery: TELEGRAM_BOT_TOKEN from .env: [" . ($bot_token ? substr($bot_token, 0, 5) . '...' : 'NOT SET') . "]");

    if (empty($bot_token)) {
        error_log("answerCallbackQuery: BOT_TOKEN is not set in .env. Cannot answer callback query.");
        return false;
    }

    $url = "https://api.telegram.org/bot{$bot_token}/answerCallbackQuery";
    
    $post_fields = ['callback_query_id' => $callback_query_id];
    if ($text) {
        $post_fields['text'] = $text;
        $post_fields['show_alert'] = true; // 将提示显示为弹窗
    }
    
    error_log("answerCallbackQuery: Sending to URL: " . $url);
    error_log("answerCallbackQuery: Post Fields: " . json_encode($post_fields));

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post_fields));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10); // 增加超时设置

    $result = curl_exec($ch);
    
    if (curl_errno($ch)) {
        $error_msg = curl_error($ch);
        error_log("answerCallbackQuery: cURL Error: " . $error_msg);
    } else {
        error_log("answerCallbackQuery: Telegram API Response: " . $result);
    }
    curl_close($ch);
    return $result;
}