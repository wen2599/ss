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
    $lines = explode("\n", $text);

    // 提取日期和彩票类型 (从第一行或第二行)
    $first_line = $lines[0] ?? '';
    $second_line = $lines[1] ?? '';

    // 尝试从第一行获取彩票类型和日期
    if (preg_match('/(.*?)\,\s*\[(\d{4})\/(\d{2})\/(\d{2})/', $first_line, $matches)) {
        $data['lottery_type'] = trim($matches[1]);
        $data['draw_date'] = "{$matches[2]}-{$matches[3]}-{$matches[4]}";
    } else {
        // 如果第一行没有，尝试从第二行获取日期 (彩票类型会在期号行获取)
        if (preg_match('/\[(\d{4})\/(\d{2})\/(\d{2})/', $second_line, $date_matches)) {
            $data['draw_date'] = "{$date_matches[1]}-{$date_matches[2]}-{$date_matches[3]}";
        }
        $data['draw_date'] = $data['draw_date'] ?? date('Y-m-d'); // 默认当前日期
    }

    $period_found = false;
    $numbers_found = false;

    foreach ($lines as $index => $line) {
        // 提取期号和彩票类型 (如果之前没有提取)
        if (! $period_found && preg_match('/(.*?)第:?(\d+)\s?期开奖结果:/', $line, $period_matches)) {
            if (!isset($data['lottery_type'])) {
                $data['lottery_type'] = trim($period_matches[1]);
            }
            $data['draw_period'] = $period_matches[2];
            $period_found = true;

            // 提取开奖号码
            if (isset($lines[$index + 1]) && preg_match('/^[\d\s]+$/', trim($lines[$index + 1]))) {
                $numbers_line = trim($lines[$index + 1]);
                $numbers_comma_separated = preg_replace('/\s+/', ',', $numbers_line);
                $data['numbers'] = $numbers_comma_separated;
                $numbers_found = true;
            }
        }
    }

    // 确保所有必需的数据都已提取
    if (isset($data['lottery_type']) && isset($data['draw_period']) && isset($data['numbers'])) {
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