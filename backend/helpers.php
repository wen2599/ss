<?php
// backend/helpers.php

/**
 * Sends a message to a specified Telegram chat.
 */
function send_telegram_message($chat_id, $text) {
    global $bot_token;
    $url = "https://api.telegram.org/bot{$bot_token}/sendMessage";
    $data = ['chat_id' => $chat_id, 'text' => $text];
    
    $options = [
        'http' => [
            'header'  => "Content-type: application/json\r\n",
            'method'  => 'POST',
            'content' => json_encode($data),
            'ignore_errors' => true
        ],
    ];
    $context  = stream_context_create($options);
    $result = file_get_contents($url, false, $context);

    if ($result === FALSE) {
        error_log("Failed to send message to chat_id: {$chat_id}");
    }
}

/**
 * Retrieves system statistics from the database.
 */
function get_system_stats() {
    global $db_connection;
    $stats = ['users' => 0, 'emails' => 0, 'lottery_draws' => 0];

    if ($result = $db_connection->query("SELECT COUNT(*) as count FROM users")) {
        $stats['users'] = $result->fetch_assoc()['count'] ?? 0;
        $result->free();
    }
    if ($result = $db_connection->query("SELECT COUNT(*) as count FROM emails")) {
        $stats['emails'] = $result->fetch_assoc()['count'] ?? 0;
        $result->free();
    }
    if ($result = $db_connection->query("SELECT COUNT(*) as count FROM lottery_draws")) {
        $stats['lottery_draws'] = $result->fetch_assoc()['count'] ?? 0;
        $result->free();
    }
    
    return $stats;
}

/**
 * Parses lottery information from a multi-format channel message.
 */
function parse_lottery_message($text) {
    $data = [];
    $lines = explode("\n", $text);
    $data['draw_date'] = date('Y-m-d');

    if (preg_match('/\[(\d{4})\/(\d{2})\/(\d{2})/', $lines[0], $date_matches)) {
        $data['draw_date'] = "{$date_matches[1]}-{$date_matches[2]}-{$date_matches[3]}";
    }

    $period_found = false;
    $numbers_found = false;

    foreach ($lines as $index => $line) {
        if (!$period_found && preg_match('/第:?(\d+)\s?期开奖结果:/', $line, $period_matches)) {
            $data['draw_period'] = $period_matches[1];
            $period_found = true;
            
            if (isset($lines[$index + 1]) && preg_match('/^[\d\s]+$/', trim($lines[$index + 1]))) {
                $numbers_line = trim($lines[$index + 1]);
                $numbers_comma_separated = preg_replace('/\s+/', ',', $numbers_line);
                $data['numbers'] = $numbers_comma_separated;
                $numbers_found = true;
                break; 
            }
        }
    }

    if (isset($data['draw_period']) && isset($data['numbers'])) {
        return $data;
    }

    return null;
}

/**
 * Saves or updates a lottery draw record in the database.
 */
function save_lottery_draw($data) {
    global $db_connection;
    
    $stmt = $db_connection->prepare("INSERT INTO lottery_draws (draw_date, draw_period, numbers) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE numbers = VALUES(numbers), draw_date = VALUES(draw_date)");
    
    if (!$stmt) {
        error_log("DB Prepare Error in save_lottery_draw: " . $db_connection->error);
        return false;
    }
    
    $stmt->bind_param("sss", $data['draw_date'], $data['draw_period'], $data['numbers']);
    $success = $stmt->execute();
    
    if(!$success) {
        error_log("DB Execute Error in save_lottery_draw: " . $stmt->error);
    }
    
    $stmt->close();
    return $success;
}

?>