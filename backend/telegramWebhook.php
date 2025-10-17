<?php
/**
 * telegramWebhook.php
 *
 * This script is the main entry point for all updates from the Telegram Bot API.
 * It handles:
 * - Validating the webhook secret token.
 * - Loading environment variables.
 * - Parsing incoming updates (messages, channel posts, callbacks).
 * - Routing updates to appropriate handlers (lottery parsing, admin commands).
 */

// --- Environment & Logging Setup ---
// Use a more reliable way to set the base directory.
define('BASE_DIR', __DIR__);

// Load environment variables from .env file.
if (file_exists(BASE_DIR . '/.env')) {
    $lines = file(BASE_DIR . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0 || strpos($line, '=') === false) continue;
        list($key, $value) = explode('=', $line, 2);
        putenv(trim($key) . "=" . trim($value, "'\""));
    }
}

// Function for writing debug logs.
function write_telegram_debug_log($message) {
    $logFile = sys_get_temp_dir() . '/telegram_debug.log';
    @file_put_contents($logFile, date('[Y-m-d H:i:s]') . ' ' . $message . PHP_EOL, FILE_APPEND | LOCK_EX);
}

// --- Webhook Security Validation ---
$expectedSecret = getenv('TELEGRAM_WEBHOOK_SECRET');
$receivedHeader = $_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] ?? '';
if (!$expectedSecret || !hash_equals($expectedSecret, $receivedHeader)) {
    http_response_code(403);
    write_telegram_debug_log("Webhook validation failed. Secret mismatch or not provided.");
    exit('Forbidden');
}

// --- Load Dependencies ---
require_once BASE_DIR . '/db_operations.php';
require_once BASE_DIR . '/telegram_helpers.php';
require_once BASE_DIR . '/user_state_manager.php';
require_once BASE_DIR . '/env_manager.php';
require_once BASE_DIR . '/api_curl_helper.php';
require_once BASE_DIR . '/gemini_ai_helper.php';
require_once BASE_DIR . '/cloudflare_ai_helper.php';

// --- Main Update Processing ---
$update = json_decode(file_get_contents('php://input'), true);

if (!$update) {
    http_response_code(200);
    exit();
}

// --- Lottery Result Parser ---
// Moved into its own function for clarity and reusability.
function parseAndStoreLotteryResult($text) {
    write_telegram_debug_log("Attempting to parse lottery text: " . $text);

    $normalizedType = null;
    if (strpos($text, '新澳门六合彩') !== false) $normalizedType = '新澳门六合彩';
    elseif (strpos($text, '香港六合彩') !== false) $normalizedType = '香港六合彩';
    elseif (strpos($text, '老澳') !== false) $normalizedType = '老澳门六合彩';
    if (!$normalizedType) return false;

    preg_match('/第\s*:?\s*(\d+)\s*期/u', $text, $issue_matches);
    $issue_number = $issue_matches[1] ?? null;
    if (!$issue_number) return false;

    $lines = explode("\n", $text);
    $winning_numbers = ''; $zodiac_signs = ''; $colors_text = '';
    foreach ($lines as $line) { if (preg_match('/^[\d\s]+$/', trim($line))) { $winning_numbers = preg_replace('/\s+/', ',', trim($line)); break; } }
    foreach ($lines as $line) { if (preg_match('/^[\p{Han}\s]+$/u', trim($line)) && !strpos($line, '开奖结果')) { $zodiac_signs = preg_replace('/\s+/', ',', trim($line)); break; } }
    foreach ($lines as $line) { if (strpos($line, '🔵') !== false || strpos($line, '🟢') !== false || strpos($line, '🔴') !== false) { $colors_text = trim($line); break; } }

    preg_match('/(\d{4}\/\d{1,2}\/\d{1,2})/', $text, $date_matches);
    $drawing_date = isset($date_matches[1]) ? date('Y-m-d', strtotime($date_matches[1])) : date('Y-m-d');

    $number_colors_json = null;
    if (!empty($winning_numbers) && !empty($colors_text)) {
        $numbers_arr = explode(',', $winning_numbers);
        preg_match_all('/(🔵|🟢|🔴)/u', $colors_text, $color_matches);
        $colors_arr = $color_matches[0] ?? [];
        if (count($numbers_arr) === count($colors_arr)) {
            $color_map = [];
            $color_name_map = ['🔵' => 'blue', '🟢' => 'green', '🔴' => 'red'];
            foreach ($numbers_arr as $index => $number) { $color_map[trim($number)] = $color_name_map[$colors_arr[$index]] ?? 'unknown'; }
            $number_colors_json = json_encode($color_map);
        }
    }

    return storeLotteryResult($normalizedType, $issue_number, $winning_numbers, $zodiac_signs, $colors_text, $drawing_date, $number_colors_json);
}


// --- Update Routing ---
if (isset($update['channel_post'])) {
    $post = $update['channel_post'];
    $chatId = $post['chat']['id'] ?? null;
    $lotteryChannelId = getenv('LOTTERY_CHANNEL_ID');
    if (!empty($lotteryChannelId) && (string)$chatId === (string)$lotteryChannelId) {
        parseAndStoreLotteryResult(trim($post['text'] ?? ''));
    }
} elseif (isset($update['message']) || isset($update['callback_query'])) {
    $adminId = getenv('TELEGRAM_ADMIN_ID');
    $userId = $update['message']['from']['id'] ?? $update['callback_query']['from']['id'] ?? null;

    if ($adminId && $userId && (string)$userId === (string)$adminId) {
        // Handle admin commands
        // (Full admin command logic would be placed here)
    }
}

// Acknowledge the update to Telegram.
http_response_code(200);
echo json_encode(['status' => 'ok']);
?>