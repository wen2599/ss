<?php
// backend/lib/telegram_utils.php

/**
 * Sends a message to a specified Telegram chat.
 *
 * @param int|string $chat_id The ID of the chat to send the message to.
 * @param string $text The message text.
 * @param array|null $reply_markup Optional. An array representing a custom keyboard.
 * @return string|false The response from the Telegram API, or false on failure.
 */
function send_telegram_message($chat_id, $text, $reply_markup = null) {
    $url = 'https://api.telegram.org/bot' . TELEGRAM_BOT_TOKEN . '/sendMessage';
    $post_fields = [
        'chat_id' => $chat_id,
        'text' => $text,
        'parse_mode' => 'HTML',
    ];

    if ($reply_markup) {
        $post_fields['reply_markup'] = json_encode($reply_markup);
    }

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $post_fields);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    curl_close($ch);

    return $response;
}

?>