<?php
/**
 * telegramWebhook.php
 *
 * This script is the main entry point for all updates from the Telegram Bot API.
 */

// --- Environment & Logging Setup ---
define('BASE_DIR', __DIR__);

if (file_exists(BASE_DIR . '/.env')) {
    $lines = file(BASE_DIR . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0 || strpos($line, '=') === false) continue;
        list($key, $value) = explode('=', $line, 2);
        putenv(trim($key) . "=" . trim($value, "'\""));
    }
}

function write_telegram_debug_log($message) {
    $logFile = sys_get_temp_dir() . '/telegram_debug.log';
    @file_put_contents($logFile, date('[Y-m-d H:i:s]') . ' ' . $message . PHP_EOL, FILE_APPEND | LOCK_EX);
}

// --- Webhook Security Validation ---
$expectedSecret = getenv('TELEGRAM_WEBHOOK_SECRET');
$receivedHeader = $_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] ?? '';
if (!$expectedSecret || !hash_equals($expectedSecret, $receivedHeader)) {
    http_response_code(403);
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

// --- Lottery Result Parser Function ---
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
$lotteryChannelId = getenv('LOTTERY_CHANNEL_ID');
if (isset($update['channel_post']) && !empty($lotteryChannelId) && (string)($update['channel_post']['chat']['id'] ?? '') === (string)$lotteryChannelId) {
    parseAndStoreLotteryResult(trim($update['channel_post']['text'] ?? ''));
} else {
    $adminId = getenv('TELEGRAM_ADMIN_ID');
    $userId = $update['message']['from']['id'] ?? $update['callback_query']['from']['id'] ?? null;

    if ($adminId && $userId && (string)$userId === (string)$adminId) {
        $chatId = $update['message']['chat']['id'] ?? $update['callback_query']['message']['chat']['id'] ?? null;
        $commandOrText = $update['message']['text'] ?? $update['callback_query']['data'] ?? '';

        if (isset($update['callback_query']['id'])) {
            answerTelegramCallbackQuery($update['callback_query']['id']);
        }

        // Handle stateful interactions
        $userState = getUserState($userId);
        if ($userState) {
            if (strpos($userState, 'awaiting_api_key_') === 0) {
                $keyToUpdate = substr($userState, strlen('awaiting_api_key_'));
                if (update_env_file($keyToUpdate, $commandOrText)) { sendTelegramMessage($chatId, "✅ API 密钥 {$keyToUpdate} 已成功更新！", getAdminKeyboard()); }
                else { sendTelegramMessage($chatId, "❌ 更新 API 密钥失败！", getAdminKeyboard()); }
            }
            setUserState($userId, null);
        } else {
            // Handle stateless commands
            $cmd = strtolower(trim($commandOrText));
            $reply = null; $replyKeyboard = null;
            switch ($cmd) {
                case '/start': case 'main_menu':
                    $reply = "欢迎回来，管理员！"; $replyKeyboard = getAdminKeyboard(); break;
                case 'menu_api_keys':
                    $reply = "请选择要更新的 API 密钥："; $replyKeyboard = getApiKeySelectionKeyboard(); break;
                default:
                    if (strpos($cmd, 'set_api_key_') === 0) {
                        $keyToSet = substr($cmd, strlen('set_api_key_'));
                        setUserState($userId, 'awaiting_api_key_' . $keyToSet);
                        $reply = "好的，请输入新的 <b>{$keyToSet}</b> 密钥：";
                    } else {
                        $reply = "无法识别的命令。"; $replyKeyboard = getAdminKeyboard();
                    }
                    break;
            }
            if ($reply) { sendTelegramMessage($chatId, $reply, $replyKeyboard); }
        }
    }
}

// Acknowledge all updates to Telegram.
http_response_code(200);
echo json_encode(['status' => 'ok']);
?>