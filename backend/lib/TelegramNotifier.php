<?php

class TelegramNotifier {

    /**
     * Sends a message to a given Telegram chat.
     *
     * @param int|string $chat_id The ID of the chat to send the message to.
     * @param string $text The message text.
     * @param string $bot_token The Telegram bot token.
     * @param array|null $reply_markup Optional reply markup (e.g., keyboard).
     */
    public static function sendMessage($chat_id, $text, $bot_token, $reply_markup = null) {
        $url = "https://api.telegram.org/bot" . $bot_token . "/sendMessage";
        $data = [
            'chat_id' => $chat_id,
            'text' => $text,
            'parse_mode' => 'Markdown'
        ];

        if ($reply_markup) {
            // The reply_markup should be a JSON string if it's a keyboard
            $data['reply_markup'] = $reply_markup;
        }

        $options = [
            'http' => [
                'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
                'method'  => 'POST',
                'content' => http_build_query($data),
                'ignore_errors' => true // To see error responses from Telegram
            ]
        ];

        $context  = stream_context_create($options);
        $result = file_get_contents($url, false, $context);

        // Optional: Log errors from Telegram API
        if ($result === FALSE) {
            error_log("Telegram API request failed.");
        } else {
            $response_data = json_decode($result, true);
            if (!$response_data['ok']) {
                error_log("Telegram API Error: " . $response_data['description']);
            }
        }
    }
}
?>
