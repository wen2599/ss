<?php
// backend/bot/commands/ping.php

require_once __DIR__ . '/../bot_helpers.php';

function handle_command($bot_token, $chat_id, $message) {
    $text = "pong";
    send_message($bot_token, $chat_id, $text);
}
