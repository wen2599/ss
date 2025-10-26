<?php

declare(strict_types=1);

// backend/helpers.php

/**
 * Sends a message to a specified Telegram chat.
 */
function send_telegram_message($chat_id, $text)
{
    global $bot_token;
    $url = "https://api.telegram.org/bot{$bot_token}/sendMessage";
    $data = ['chat_id' => $chat_id, 'text' => $text];

    $options = [
        'http' => [
            'header'  => "Content-type: application/json\r\n",
            'method'  => 'POST',
            'content' => json_encode($data),
            'ignore_errors' => true,
        ],
    ];
    $context  = stream_context_create($options);
    $result = file_get_contents($url, false, $context);

    if ($result === false) {
        error_log("Failed to send message to chat_id: {$chat_id}");
    }
}

/**
 * Retrieves system statistics from the database.
 */
function get_system_stats(): array
{
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
function parse_lottery_message($text): ?array
{
    $data = [];
    $lines = array_map('trim', explode("\n", $text));
    $data['draw_date'] = date('Y-m-d'); // Default to today

    // --- 1. Extract Lottery Type and Period ---
    if (preg_match('/(新澳门六合彩|香港六合彩|老澳\d{2}\.\d{2})第:?(\d+)\s?期开奖结果:/', $lines[0], $matches)) {
        $data['lottery_type'] = $matches[1];
        $data['draw_period'] = $matches[2];
    } else {
        return null; // Essential info missing
    }

    // --- 2. Extract Numbers, Zodiacs, and Colors ---
    // The data is expected in the next three lines
    if (isset($lines[1]) && isset($lines[2]) && isset($lines[3])) {
        // Numbers: "22 20 49 37 39 35 23" -> "22,20,49,37,39,35,23"
        $data['numbers'] = preg_replace('/\s+/', ',', trim($lines[1]));

        // Zodiacs: "猴 狗 蛇 蛇 兔 羊 羊" -> "猴,狗,蛇,蛇,兔,羊,羊"
        $data['zodiacs'] = preg_replace('/\s+/', ',', trim($lines[2]));

        // Colors: "🟢 🔵 🟢 🔵 🟢 🔴 🔴" -> "🟢,🔵,🟢,🔵,🟢,🔴,🔴"
        $data['colors'] = preg_replace('/\s+/', ',', trim($lines[3]));
    } else {
        return null; // Data format is incorrect
    }

    // --- 3. Override Date if present ---
    if (preg_match('/\[(\d{4})\/(\d{2})\/(\d{2})/', $lines[0], $date_matches)) {
        $data['draw_date'] = "{$date_matches[1]}-{$date_matches[2]}-{$date_matches[3]}";
    }

    return $data;
}


/**
 * Saves or updates a lottery draw record in the database.
 */
function save_lottery_draw($data): bool
{
    global $db_connection;

    $stmt = $db_connection->prepare(
        "INSERT INTO lottery_draws (lottery_type, draw_period, draw_date, numbers, zodiacs, colors)
         VALUES (?, ?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE
         draw_date = VALUES(draw_date),
         numbers = VALUES(numbers),
         zodiacs = VALUES(zodiacs),
         colors = VALUES(colors)"
    );

    if (! $stmt) {
        error_log("DB Prepare Error in save_lottery_draw: " . $db_connection->error);
        return false;
    }

    $stmt->bind_param("ssssss",
        $data['lottery_type'],
        $data['draw_period'],
        $data['draw_date'],
        $data['numbers'],
        $data['zodiacs'],
        $data['colors']
    );

    $success = $stmt->execute();

    if (! $success) {
        error_log("DB Execute Error in save_lottery_draw: " . $stmt->error);
    }

    $stmt->close();
    return $success;
}
