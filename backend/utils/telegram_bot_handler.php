<?php
// backend/utils/telegram_bot_handler.php

/**
 * 发送消息给管理员
 * @param string $message 要发送的文本消息
 * @param array|null $reply_markup 键盘菜单 (可选)
 * @return bool|string API响应或false
 */
function sendTelegramMessage($message, $reply_markup = null) {
    $admin_id = $_ENV['TELEGRAM_ADMIN_ID'];
    $bot_token = $_ENV['TELEGRAM_BOT_TOKEN'];
    $url = "https://api.telegram.org/bot{$bot_token}/sendMessage";

    $post_fields = [
        'chat_id' => $admin_id,
        'text' => $message,
        'parse_mode' => 'Markdown' // 允许使用Markdown格式化文本
    ];

    if ($reply_markup) {
        $post_fields['reply_markup'] = json_encode($reply_markup);
    }

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post_fields));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $result = curl_exec($ch);
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
    $bot_token = $_ENV['TELEGRAM_BOT_TOKEN'];
    $url = "https://api.telegram.org/bot{$bot_token}/answerCallbackQuery";
    
    $post_fields = ['callback_query_id' => $callback_query_id];
    if ($text) {
        $post_fields['text'] = $text;
        $post_fields['show_alert'] = true; // 将提示显示为弹窗
    }
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post_fields));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $result = curl_exec($ch);
    curl_close($ch);
    return $result;
}