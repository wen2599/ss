<?php

declare(strict_types=1);

// backend/helpers.php

/**
 * Sends a message to a specified Telegram chat.
 */
function send_telegram_message($chat_id, $text, $reply_markup = null)
{
    global $bot_token;
    $url = "https://api.telegram.org/bot{$bot_token}/sendMessage";
    $data = ['chat_id' => $chat_id, 'text' => $text];

    if ($reply_markup) {
        $data['reply_markup'] = $reply_markup;
    }

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
    $lines = explode("\n", $text);

    // Default draw date to today
    $data['draw_date'] = date('Y-m-d');

    // Attempt to find a date in the first two lines
    $text_to_scan_for_date = ($lines[0] ?? '') . "\n" . ($lines[1] ?? '');
    if (preg_match('/\[(\d{4})\/(\d{2})\/(\d{2})/', $text_to_scan_for_date, $date_matches)) {
        $data['draw_date'] = "{$date_matches[1]}-{$date_matches[2]}-{$date_matches[3]}";
    }

    $period_found = false;
    $numbers_found = false;

    foreach ($lines as $index => $line) {
        // Always try to extract lottery type and period from the line
        if (!$period_found && preg_match('/^(.*?)\s*第:?\s*(\d+)\s*期开奖结果:/u', trim($line), $period_matches)) {
            $data['lottery_type'] = trim($period_matches[1]);
            $data['draw_period'] = $period_matches[2];
            $period_found = true;

            // Look for numbers in the next line
            if (isset($lines[$index + 1]) && preg_match('/^[\d\s]+$/', trim($lines[$index + 1]))) {
                $numbers_line = trim($lines[$index + 1]);
                $numbers_comma_separated = preg_replace('/\s+/', ',', $numbers_line);
                $data['numbers'] = $numbers_comma_separated;
                $numbers_found = true;
                break; // Found everything, exit the loop
            }
        }
    }

    // Check if we have all the required data
    if (!empty($data['lottery_type']) && isset($data['draw_period']) && isset($data['numbers'])) {
        return $data;
    }

    return null;
}


/**
 * Saves or updates a lottery draw record in the database.
 */
function save_lottery_draw($data): bool
{
    global $db_connection;

    // 确保lottery_type存在
    if (!isset($data['lottery_type'])) {
        error_log("Error: lottery_type is missing in data for save_lottery_draw.");
        return false;
    }

    $stmt = $db_connection->prepare("INSERT INTO lottery_draws (draw_date, lottery_type, draw_period, numbers) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE numbers = VALUES(numbers), draw_date = VALUES(draw_date)");

    if (! $stmt) {
        error_log("DB Prepare Error in save_lottery_draw: " . $db_connection->error);
        return false;
    }

    $stmt->bind_param("ssss", $data['draw_date'], $data['lottery_type'], $data['draw_period'], $data['numbers']);
    $success = $stmt->execute();

    if (! $success) {
        error_log("DB Execute Error in save_lottery_draw: " . $stmt->error);
    }

    $stmt->close();
    return $success;
}

/**
 * Gets the current command state for a user.
 */
function get_user_state($chat_id): ?string
{
    $state_file = __DIR__ . '/bot_state.json';
    if (!file_exists($state_file)) {
        return null;
    }

    $state_data = json_decode(file_get_contents($state_file), true);
    return $state_data[$chat_id]['command'] ?? null;
}

/**
 * Sets or clears the command state for a user.
 */
function set_user_state($chat_id, $command): void
{
    $state_file = __DIR__ . '/bot_state.json';
    $state_data = [];

    $fp = fopen($state_file, 'c+');
    if (flock($fp, LOCK_EX)) { // Exclusive lock
        $file_content = stream_get_contents($fp);
        if (!empty($file_content)) {
            $state_data = json_decode($file_content, true);
        }

        if ($command) {
            $state_data[$chat_id] = ['command' => $command, 'timestamp' => time()];
        } else {
            unset($state_data[$chat_id]);
        }

        ftruncate($fp, 0); // Clear the file
        rewind($fp);
        fwrite($fp, json_encode($state_data, JSON_PRETTY_PRINT));
        fflush($fp);
        flock($fp, LOCK_UN); // Release the lock
    }
    fclose($fp);
}
