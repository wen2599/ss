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
    $data = ['chat_id' => $chat_id, 'text' => $text, 'parse_mode' => 'HTML'];

    if ($reply_markup) {
        $data['reply_markup'] = $reply_markup;
    }

    // Use cURL for more robust sending
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10); // 10-second timeout
        $result = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);

        if ($result === false) {
            error_log("cURL Error sending message to chat_id {$chat_id}: {$curl_error}");
        } elseif ($http_code >= 400) {
            error_log("Telegram API Error for chat_id {$chat_id}: HTTP Code {$http_code} - Response: {$result}");
        }
    } else {
        // Fallback to file_get_contents if cURL is not available
        $options = [
            'http' => [
                'header'  => "Content-type: application/json\r\n",
                'method'  => 'POST',
                'content' => json_encode($data),
                'ignore_errors' => true,
                'timeout' => 10,
            ],
        ];
        $context  = stream_context_create($options);
        $result = file_get_contents($url, false, $context);

        if ($result === false) {
            error_log("file_get_contents Error: Failed to send message to chat_id: {$chat_id}");
        }
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
    $temp_file = $state_file . '.' . uniqid() . '.tmp';
    $state_data = [];

    // Step 1: Read the current state safely.
    if (file_exists($state_file)) {
        $file_content = file_get_contents($state_file);
        if (!empty($file_content)) {
            $decoded_data = json_decode($file_content, true);
            // Check if json_decode was successful
            if (is_array($decoded_data)) {
                $state_data = $decoded_data;
            }
        }
    }

    // Step 2: Modify the state in memory.
    if ($command) {
        $state_data[$chat_id] = ['command' => $command, 'timestamp' => time()];
    } else {
        unset($state_data[$chat_id]);
    }

    // Step 3: Write to a temporary file.
    $write_success = file_put_contents($temp_file, json_encode($state_data, JSON_PRETTY_PRINT));

    if ($write_success === false) {
        // Handle error: could not write to temporary file
        error_log("Failed to write to temporary state file: {$temp_file}");
        @unlink($temp_file); // Clean up temp file
        return;
    }

    // Step 4: Atomically rename the temporary file to the original file name.
    if (!rename($temp_file, $state_file)) {
        // Handle error: could not rename file
        error_log("Failed to rename temporary state file to {$state_file}");
        @unlink($temp_file); // Clean up temp file
    }
}
