<?php
// backend/bot/commands/view_emails.php

require_once __DIR__ . '/../bot_helpers.php';

function handle_callback($bot_token, $chat_id, $callback_data) {
    $emails = fetch_user_emails();

    if (empty($emails)) {
        send_message($bot_token, $chat_id, "您的邮箱中暂时没有邮件。");
        return;
    }

    $response_text = "<b>您的最新邮件：</b>\n\n";
    foreach ($emails as $email) {
        $response_text .= "<b>发件人:</b> " . htmlspecialchars($email['from_address']) . "\n";
        $response_text .= "<b>主题:</b> " . htmlspecialchars($email['subject']) . "\n";
        $response_text .= "<b>收到时间:</b> {$email['received_at']}\n";
        $response_text .= "--------------------\n";
    }

    send_message($bot_token, $chat_id, $response_text);
}
