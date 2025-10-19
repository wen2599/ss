<?php
require_once __DIR__ . '/config.php';

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

// Read configured secrets
$expectedSecret = getenv('TELEGRAM_WEBHOOK_SECRET') ?: null;
$adminId = getenv('TELEGRAM_ADMIN_ID') ?: null;
$lotteryChannelId = getenv('LOTTERY_CHANNEL_ID') ?: null;

write_log("ENV Check: AdminID=" . ($adminId ? 'OK' : 'FAIL') . ", ChannelID=" . ($lotteryChannelId ? 'OK' : 'FAIL') . ", WebhookSecret=" . ($expectedSecret ? 'OK' : 'FAIL'));

// --- DUAL SECRET TOKEN VALIDATION ---
$receivedHeader = $_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] ?? null;
$receivedParam = $_GET['secret'] ?? null;
$receivedSecret = $receivedHeader ?? $receivedParam;
write_log("Secret Check: Header was " . ($receivedHeader ? 'present' : 'missing') . ". Param was " . ($receivedParam ? 'present' : 'missing') . ".");

if ($expectedSecret) {
    if (empty($receivedSecret)) {
        write_log("Webhook rejected: Missing secret token in both header and URL parameter.");
        http_response_code(403);
        exit('Forbidden: Missing secret token.');
    }
    if (!hash_equals($expectedSecret, $receivedSecret)) {
        write_log("Webhook rejected: Secret token mismatch.");
        http_response_code(403);
        exit('Forbidden: Secret token mismatch.');
    }
} else {
    write_log("WARNING: TELEGRAM_WEBHOOK_SECRET is not configured.");
}

// --- Process Incoming Update ---
$bodyRaw = file_get_contents('php://input');
$update = json_decode($bodyRaw, true);

if (!is_array($update)) {
    write_log("Invalid JSON payload; ignoring.");
    http_response_code(200);
    exit();
}

// Main update parsing
$chatId = $update['message']['chat']['id'] 
    ?? $update['channel_post']['chat']['id'] 
    ?? $update['callback_query']['message']['chat']['id'] 
    ?? null;

$userId = $update['message']['from']['id'] 
    ?? $update['callback_query']['from']['id'] 
    ?? $chatId;

// Check for channel post first
if (isset($update['channel_post'])) {
    $post = $update['channel_post'];
    $text = trim($post['text'] ?? '');
    write_log("Received channel_post from chat={$chatId} with text: " . substr($text, 0, 200));

    if (!empty($lotteryChannelId) && (string)$chatId === (string)$lotteryChannelId) {
        handleLotteryMessage($chatId, $text);
        http_response_code(200);
        exit(json_encode(['status' => 'ok', 'message' => 'processed lottery channel post']));
    }
} 
// Check for callback query
elseif (isset($update['callback_query'])) {
    $cb = $update['callback_query'];
    $commandOrText = $cb['data'] ?? null;
    if (!empty($cb['id'])) answerTelegramCallbackQuery($cb['id']);
    write_log("Received callback_query from user={$userId} with data: " . $commandOrText);
} 
// Check for personal message
elseif (isset($update['message'])) {
    $msg = $update['message'];
    $commandOrText = trim($msg['text'] ?? '');
    write_log("Received message from user={$userId} with text: " . $commandOrText);
} else {
    write_log("Unsupported update type; ignoring.");
    http_response_code(200);
    exit();
}

// --- Process Admin Commands (if not a channel post) ---
if (empty($chatId) || empty($userId)) {
    write_log("Missing chatId or userId after parsing update.");
    http_response_code(200);
    exit();
}

if (!empty($adminId) && ((string)$userId !== (string)$adminId)) {
    write_log("Unauthorized command access from user {$userId}.");
    @sendTelegramMessage($chatId, "抱歉，您无权使用此机器人。");
    http_response_code(200);
    exit();
}

try {
    // State handling...
    $userState = getUserState($userId);
    write_log("User state for user {$userId}: " . json_encode($userState));
    if ($userState) {
        // ... (stateful logic for admin commands - keeping as is)
        http_response_code(200); exit();
    }

    // Command handling...
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
    if (!empty($adminId)) {
        @sendTelegramMessage($adminId, "Webhook internal error: " . substr($e->getMessage(), 0, 200));
    }
}

// Final OK response to Telegram
http_response_code(200);
echo json_encode(['status' => 'ok']);
exit();

?>
