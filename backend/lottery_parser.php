<?php
/**
 * lottery_parser.php
 *
 * Contains the logic for parsing lottery result messages from the Telegram channel.
 */

require_once __DIR__ . '/db_operations.php';

/**
 * Parses the text of a lottery result message and stores it in the database.
 *
 * @param string $text The message text from the Telegram channel post.
 */
function handleLotteryMessage($text) {
    error_log("Attempting to parse lottery message...");
    $parsedData = parse_lottery_data($text);

    if ($parsedData === null) {
        error_log("Failed to parse lottery message. No data will be stored.");
        return;
    }

    if (!function_exists('storeLotteryResult')) {
        error_log("CRITICAL ERROR: function storeLotteryResult() does not exist! Is db_operations.php included?");
        return;
    }

    try {
        $success = storeLotteryResult(
            $parsedData['lottery_type'],
            $parsedData['issue_number'],
            json_encode($parsedData['winning_numbers']),
            json_encode($parsedData['zodiac_signs']),
            json_encode($parsedData['colors']),
            $parsedData['drawing_date']
        );

        if ($success) {
            error_log("Successfully stored lottery result for issue {$parsedData['issue_number']}.");
        } else {
            error_log("Failed to store lottery result. Check db_operations.php and database error logs.");
        }
    } catch (Throwable $e) {
        error_log("Exception during database storage for lottery result: " . $e->getMessage());
    }
}

/**
 * The core parsing logic. Extracts structured data from the message text.
 *
 * @param string $text The raw message text.
 * @return array|null The structured data or null on failure.
 */
function parse_lottery_data($text) {
    $data = [
        'lottery_type' => null,
        'issue_number' => null,
        'winning_numbers' => [],
        'zodiac_signs' => [],
        'colors' => [],
        'drawing_date' => date('Y-m-d') // Default to today
    ];

    // Match the header like "新澳门六合彩 第:2024001期" or "老澳21.30 第:2024001期"
    if (preg_match('/(新澳门六合彩|香港六合彩|老澳.*?)第:(\d+)期/', $text, $header_matches)) {
        $lottery_name = trim($header_matches[1]);
        // Normalize "老澳..." to "老澳门六合彩"
        if (strpos($lottery_name, '老澳') !== false) {
            $data['lottery_type'] = '老澳门六合彩';
        } else {
            $data['lottery_type'] = $lottery_name;
        }
        $data['issue_number'] = $header_matches[2];
    } else {
        error_log("[Parser] Failed: Could not match lottery name and issue number.");
        return null;
    }

    // Split the text into lines and remove empty ones
    $lines = array_values(array_filter(array_map('trim', explode("\n", trim($text)))));

    if (count($lines) < 4) {
        error_log("[Parser] Failed: Not enough lines in message body.");
        return null;
    }

    // The data is expected to be on specific lines after the header
    // Line 1: Winning Numbers (e.g., "11 23 34 45 01 09 18")
    // Line 2: Zodiac Signs (e.g., "猪 鼠 虎 兔 龙 蛇 狗")
    // Line 3: Colors (e.g., "红 蓝 绿 红 蓝 绿 红")
    $data['winning_numbers'] = preg_split('/\s+/', $lines[1]);
    $data['zodiac_signs']    = preg_split('/\s+/', $lines[2]);
    $data['colors']          = preg_split('/\s+/', $lines[3]);

    // Basic validation
    if (count($data['winning_numbers']) === 0 || count($data['winning_numbers']) !== count($data['zodiac_signs']) || count($data['winning_numbers']) !== count($data['colors'])) {
        error_log("[Parser] Failed: Mismatch in counts of numbers, zodiacs, or colors.");
        return null;
    }

    error_log("[Parser] Success: Parsed issue {$data['issue_number']} for {$data['lottery_type']}");
    return $data;
}
?>