<?php
// backend/bot/commands/start.php

function handle_command($bot_token, $chat_id, $message) {
    $text = "Welcome to the bot!";
    send_message($bot_token, $chat_id, $text);
}

function send_message($bot_token, $chat_id, $text) {
    $url = "https://api.telegram.org/bot{$bot_token}/sendMessage";
    $data = [
        'chat_id' => $chat_id,
        'text' => $text,
    ];

    $options = [
        'http' => [
            'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
            'method'  => 'POST',
            'content' => http_build_query($data),
        ],
    ];
    $context  = stream_context_create($options);
    file_get_contents($url, false, $context);
}
