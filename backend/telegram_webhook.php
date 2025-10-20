<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/telegram_helpers.php'; // Specific Telegram helper functions

write_log("------ telegram_webhook.php Entry Point ------");

// --- Lottery Message Parser & Handler ---
function parse_lottery_data($text) {
    $data = [
        'lottery_type' => null, 'issue_number' => null, 'winning_numbers' => [],
        'zodiac_signs' => [], 'colors' => [], 'drawing_date' => date('Y-m-d')
    ];
    if (preg_match('/(新澳门六合彩|香港六合彩|老澳.*?)第:(\d+)期/', $text, $h)) {
        $data['lottery_type'] = (strpos($h[1], '老澳') !== false) ? '老澳门六合彩' : trim($h[1]);
        $data['issue_number'] = $h[2];
    } else { write_log("[Parser] Failed: Header match."); return null; }
    $lines = array_values(array_filter(array_map('trim', explode("\n", trim($text))), fn($l) => !empty($l)));
    if (count($lines) < 4) { write_log("[Parser] Failed: Not enough lines."); return null; }
    $data['winning_numbers'] = preg_split('/\s+/', $lines[1]);
    $data['zodiac_signs']    = preg_split('/\s+/', $lines[2]);
    $data['colors']          = preg_split('/\s+/', $lines[3]);
    if (count($data['winning_numbers']) === 0 || count($data['winning_numbers']) !== count($data['zodiac_signs']) || count($data['winning_numbers']) !== count($data['colors'])) {
        write_log("[Parser] Failed: Mismatch in data counts."); return null;
    }
    write_log("[Parser] Success: Parsed issue {$data['issue_number']} for {$data['lottery_type']}");
    return $data;
}

function handleLotteryMessage($chatId, $text) {
    write_log("Attempting to parse lottery message: " . substr($text, 0, 100) . "...");
    $parsedData = parse_lottery_data($text);
    if ($parsedData === null) {
        write_log("Failed to parse lottery message. No data will be stored.");
        return;
    }
    try {
        $numbersJson = json_encode($parsedData['winning_numbers']);
        $zodiacsJson = json_encode($parsedData['zodiac_signs']);
        $colorsJson = json_encode($parsedData['colors']);
        $success = storeLotteryResult(
            $parsedData['lottery_type'], $parsedData['issue_number'],
            $numbersJson, $zodiacsJson, $colorsJson, $parsedData['drawing_date']
        );
        if ($success) {
            write_log("Successfully stored lottery result for issue {$parsedData['issue_number']}.");
        } else {
            write_log("Failed to store lottery result. Check db_operations.php and database error logs.");
        }
    } catch (Throwable $e) {
        write_log("Exception during database storage: " . $e->getMessage());
    }
}

// --- Main Script Execution ---
$expectedSecret = getenv('TELEGRAM_WEBHOOK_SECRET') ?: null;
$adminId = getenv('TELEGRAM_ADMIN_ID') ?: null;
$lotteryChannelId = getenv('LOTTERY_CHANNEL_ID') ?: null;

write_log("ENV Check: AdminID=" . ($adminId ? 'OK' : 'FAIL') . ", ChannelID=" . ($lotteryChannelId ? 'OK' : 'FAIL') . ", WebhookSecret=" . ($expectedSecret ? 'OK' : 'FAIL'));

$receivedHeader = $_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] ?? null;
$receivedParam = $_GET['secret'] ?? null;
$receivedSecret = $receivedHeader ?? $receivedParam;
write_log("Secret Check: Header was " . ($receivedHeader ? 'present' : 'missing') . ". Param was " . ($receivedParam ? 'present' : 'missing') . ".");

if ($expectedSecret) {
    if (empty($receivedSecret)) {
        write_log("Webhook rejected: Missing secret token.");
        json_response('error', 'Forbidden: Missing secret token.', 403);
    }
    if (!hash_equals($expectedSecret, $receivedSecret)) {
        write_log("Webhook rejected: Secret token mismatch.");
        json_response('error', 'Forbidden: Secret token mismatch.', 403);
    }
} else {
    write_log("WARNING: TELEGRAM_WEBHOOK_SECRET is not configured.");
}

$bodyRaw = file_get_contents('php://input');
$update = json_decode($bodyRaw, true);

if (!is_array($update)) {
    write_log("Invalid JSON payload; ignoring.");
    json_response('success', 'Invalid JSON payload; ignoring.'); // 200 OK for ignored updates
}

// --- Process Different Update Types ---

// 1. Channel Post for Lottery Data
if (isset($update['channel_post'])) {
    $chatId = $update['channel_post']['chat']['id'] ?? null;
    $text = trim($update['channel_post']['text'] ?? '');
    write_log("Received channel_post from chat={$chatId}");

    if (!empty($lotteryChannelId) && (string)$chatId === (string)$lotteryChannelId) {
        handleLotteryMessage($chatId, $text);
    } else {
        write_log("Ignoring channel_post from non-lottery channel.");
    }
    json_response('success', 'processed channel post');
}

// 2. Callback Query or Message from Admin
$chatId = $update['message']['chat']['id'] ?? $update['callback_query']['message']['chat']['id'] ?? null;
$userId = $update['message']['from']['id'] ?? $update['callback_query']['from']['id'] ?? null;
$commandOrText = null;

if (isset($update['callback_query'])) {
    $cb = $update['callback_query'];
    $commandOrText = $cb['data'] ?? null;
    if (!empty($cb['id'])) answerTelegramCallbackQuery($cb['id']);
    write_log("Received callback_query from user={$userId} with data: " . $commandOrText);
} elseif (isset($update['message'])) {
    $commandOrText = trim($update['message']['text'] ?? '');
    write_log("Received message from user={$userId} with text: " . $commandOrText);
} else {
    write_log("Unsupported update type or already handled; ignoring.");
    json_response('success', 'Unsupported update type or already handled; ignoring.');
}

// --- Process Admin Commands ---
if (empty($chatId) || empty($userId)) {
    write_log("Missing chatId or userId for command processing.");
    json_response('success', 'Missing chatId or userId for command processing.');
}

// IMPORTANT: Admin-only check
if (empty($adminId) || (string)$userId !== (string)$adminId) {
    write_log("Unauthorized access attempt from user {$userId}.");
    // Only send a message if it's a direct command attempt, not just random text.
    if (strpos(trim($commandOrText), '/') === 0) {
       @sendTelegramMessage($chatId, "抱歉，您无权使用此机器人。");
    }
    json_response('error', 'Unauthorized access.', 403);
}

write_log("Processing admin command from user {$userId}.");

try {
    // Ensure user_state_manager.php is effectively included (via bootstrap or directly if needed)
    // Assuming getUserState and getAdminKeyboard are available globally or via requires.
    $userState = getUserState($userId);
    write_log("User state for admin {$userId}: " . json_encode($userState));
    if ($userState) {
        // ... (stateful logic for admin commands - keeping as is)
        json_response('success', 'Stateful command processed.');
    }

    $cmd = strtolower(trim($commandOrText ?? ''));
    write_log("Processing command: " . $cmd);
    $reply = null;
    $replyKeyboard = null;

    switch ($cmd) {
        case '/start':
        case 'main_menu':
            $reply = "欢迎回来，管理员！";
            $replyKeyboard = getAdminKeyboard();
            break;
        // ... (all other admin command cases - keeping as is)
        default:
            if (!empty($cmd)) {
                $reply = "无法识别的命令。";
                $replyKeyboard = getAdminKeyboard();
            }
            break;
    }

    if ($reply) {
        write_log("Replying with: " . $reply);
        sendTelegramMessage($chatId, $reply, $replyKeyboard);
    }
} catch (Throwable $e) {
    write_log("Exception in admin command processing: " . $e->getMessage());
    @sendTelegramMessage($adminId, "Webhook internal error: " . substr($e->getMessage(), 0, 200));
    json_response('error', 'An internal error occurred during command processing.', 500);
}

json_response('success', 'Webhook processed successfully.');

?>