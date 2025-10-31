<?php
// backend/bot/bot_helpers.php

require_once __DIR__ . '/../db_connection.php'; // Include db_connection for database operations

function send_message($bot_token, $chat_id, $text, $reply_markup = null) {
    $url = "https://api.telegram.org/bot{$bot_token}/sendMessage";
    $data = [
        'chat_id' => $chat_id,
        'text' => $text,
        'parse_mode' => 'HTML', // Use HTML parse mode for basic formatting
    ];

    if ($reply_markup) {
        $data['reply_markup'] = $reply_markup;
    }

    $options = [
        'http' => [
            'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
            'method'  => 'POST',
            'content' => http_build_query($data),
        ],
    ];
    $context  = stream_context_create($options);
    $response = @file_get_contents($url, false, $context);

    if ($response === false) {
        error_log("Telegram sendMessage failed for chat_id: {$chat_id}. Error: " . error_get_last()['message']);
    }
}

function answer_callback_query($bot_token, $callback_query_id, $text = null, $show_alert = false) {
    $url = "https://api.telegram.org/bot{$bot_token}/answerCallbackQuery";
    $data = [
        'callback_query_id' => $callback_query_id,
    ];

    if ($text) {
        $data['text'] = $text;
    }
    if ($show_alert) {
        $data['show_alert'] = true;
    }

    $options = [
        'http' => [
            'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
            'method'  => 'POST',
            'content' => http_build_query($data),
        ],
    ];
    $context  = stream_context_create($options);
    @file_get_contents($url, false, $context);
}

function fetch_latest_lottery_results() {
    $conn = get_db_connection();
    if ($conn === null) {
        error_log("fetch_latest_lottery_results: Database connection failed.");
        return [];
    }

    $results = [];
    $sql = "SELECT issue_number, draw_date, numbers FROM lottery_results ORDER BY draw_date DESC, issue_number DESC LIMIT 5";
    
    $result = $conn->query($sql);

    if ($result === false) {
        error_log("fetch_latest_lottery_results: Database query failed: " . $conn->error);
        $conn->close();
        return [];
    }

    if ($result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $results[] = $row;
        }
    }
    $conn->close();
    return $results;
}

function fetch_user_emails() {
    $conn = get_db_connection();
    if ($conn === null) {
        error_log("fetch_user_emails: Database connection failed.");
        return [];
    }

    $emails = [];
    // Fetch the latest 5 emails, ordered by received time
    $sql = "SELECT id, from_address, subject, received_at FROM emails ORDER BY received_at DESC LIMIT 5";
    
    $result = $conn->query($sql);

    if ($result === false) {
        error_log("fetch_user_emails: Database query failed: " . $conn->error);
        $conn->close();
        return [];
    }

    if ($result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $emails[] = $row;
        }
    }
    $conn->close();
    return $emails;
}
