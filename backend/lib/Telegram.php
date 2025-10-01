<?php

class Telegram {

    public static function sendMessage($chat_id, $text, $reply_markup = null) {
        global $bot_token;
        $url = "https://api.telegram.org/bot" . $bot_token . "/sendMessage";
        $data = [
            'chat_id' => $chat_id,
            'text' => $text,
            'parse_mode' => 'Markdown'
        ];
        if ($reply_markup) {
            $data['reply_markup'] = $reply_markup;
        }
        $options = ['http' => ['header'  => "Content-type: application/x-www-form-urlencoded\r\n", 'method'  => 'POST', 'content' => http_build_query($data)]];
        $context  = stream_context_create($options);
        file_get_contents($url, false, $context);
    }

    public static function editMessageText($chat_id, $message_id, $text) {
        global $bot_token;
        $url = "https://api.telegram.org/bot" . $bot_token . "/editMessageText";
        $data = [
            'chat_id' => $chat_id,
            'message_id' => $message_id,
            'text' => $text,
            'parse_mode' => 'Markdown'
        ];
        $options = ['http' => ['header'  => "Content-type: application/x-www-form-urlencoded\r\n", 'method'  => 'POST', 'content' => http_build_query($data)]];
        $context  = stream_context_create($options);
        file_get_contents($url, false, $context);
    }

    public static function answerCallbackQuery($callback_query_id) {
        global $bot_token;
        $url = "https://api.telegram.org/bot" . $bot_token . "/answerCallbackQuery";
        $data = ['callback_query_id' => $callback_query_id];
        $options = ['http' => ['header'  => "Content-type: application/x-www-form-urlencoded\r\n", 'method'  => 'POST', 'content' => http_build_query($data)]];
        $context  = stream_context_create($options);
        file_get_contents($url, false, $context);
    }
}
?>