<?php

// --- Unified Configuration and Helpers ---
require_once __DIR__ . '/config.php';

// --- Security Validation ---
$secretToken = getenv('TELEGRAM_WEBHOOK_SECRET');
$receivedToken = $_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] ?? '';
if (empty($secretToken) || $receivedToken !== $secretToken) {
    http_response_code(403);
    exit('Forbidden: Secret token mismatch.');
}

// --- Main Webhook Logic ---
$update = json_decode(file_get_contents('php://input'), true);
if (!$update || !isset($update['message'])) {
    exit();
}

$message = $update['message'];
$chatId = $message['chat']['id'];
$userId = $message['from']['id'] ?? $chatId;
$text = trim($message['text'] ?? '');

// --- Admin Verification ---
$adminChatId = getenv('TELEGRAM_ADMIN_CHAT_ID');
if (empty($adminChatId) || (string)$chatId !== (string)$adminChatId) {
    sendTelegramMessage($chatId, "抱歉，您无权使用此机器人。");
    exit();
}

// --- State-Driven Conversation Logic ---
$userState = getUserState($userId);

if ($userState) {
    // --- State: Awaiting New API Key ---
    if (strpos($userState, 'awaiting_api_key_') === 0) {
        $keyToUpdate = substr($userState, strlen('awaiting_api_key_'));
        if (update_env_file($keyToUpdate, $text)) {
            sendTelegramMessage($chatId, "✅ API 密钥 `{$keyToUpdate}` 已成功更新！新配置已生效。", getAdminKeyboard());
        } else {
            sendTelegramMessage($chatId, "❌ 更新 API 密钥失败！请检查 `.env` 文件的权限和路径是否正确。", getAdminKeyboard());
        }
        setUserState($userId, null);

    // --- State: Awaiting Gemini Prompt ---
    } elseif ($userState === 'awaiting_gemini_prompt') {
        sendTelegramMessage($chatId, "🧠 正在思考中，请稍候...", getAdminKeyboard());
        $response = call_gemini_api($text);
        sendTelegramMessage($chatId, $response, getAdminKeyboard());
        setUserState($userId, null);
    
    // --- State: Awaiting Cloudflare Prompt ---
    } elseif ($userState === 'awaiting_cloudflare_prompt') {
        sendTelegramMessage($chatId, "🧠 正在思考中，请稍候...", getAdminKeyboard());
        $response = call_cloudflare_ai_api($text);
        sendTelegramMessage($chatId, $response, getAdminKeyboard());
        setUserState($userId, null);

    // --- State: Awaiting User Deletion ---
    } elseif ($userState === 'awaiting_user_deletion') {
        if (filter_var($text, FILTER_VALIDATE_EMAIL)) {
            if (deleteUserByEmail($text)) {
                sendTelegramMessage($chatId, "✅ 用户 `{$text}` 已成功删除。", getUserManagementKeyboard());
            } else {
                sendTelegramMessage($chatId, "⚠️ 删除失败。用户 `{$text}` 不存在或数据库出错。", getUserManagementKeyboard());
            }
        } else {
            sendTelegramMessage($chatId, "❌ 您输入的不是一个有效的邮箱地址，请重新输入或返回主菜单。", getUserManagementKeyboard());
        }
        setUserState($userId, null); // Reset state after one attempt.

    } else {
        setUserState($userId, null); // Clear invalid state
        sendTelegramMessage($chatId, "系统状态异常，已重置。请重新选择操作。", getAdminKeyboard());
    }

// This block handles initial commands when the user is not in a specific state.
} else {
    // --- Lottery Result Processing (Priority Check) ---
    if (strpos($text, '开奖') !== false || strpos($text, '特码') !== false) {
        handleLotteryResult($chatId, $text);
        exit(); // Stop further processing
    }

    $messageToSend = null;
    $keyboard = getAdminKeyboard();

    switch ($text) {
        case '/start':
        case '/':
            $messageToSend = "欢迎回来，管理员！请选择一个操作。";
            break;

        // --- User Management ---
        case '用户管理':
            $messageToSend = "请选择一个用户管理操作:";
            $keyboard = getUserManagementKeyboard();
            break;
        case '查看用户列表':
            $users = getAllUsers();
            if (empty($users)) {
                $messageToSend = "数据库中没有找到任何用户。";
            } else {
                $messageToSend = "注册用户列表:\n\n";
                foreach ($users as $user) {
                    $messageToSend .= "📧 **邮箱:** `{$user['email']}`\n";
                    $messageToSend .= "📅 **注册于:** {$user['created_at']}\n\n";
                }
            }
            $keyboard = getUserManagementKeyboard(); // Show menu again
            break;
        case '删除用户':
            setUserState($userId, 'awaiting_user_deletion');
            $messageToSend = "好的，请发送您想要删除的用户的电子邮件地址。";
            $keyboard = null; // No keyboard when asking for input
            break;

        // --- AI & API Management ---
        case '请求 Gemini':
            setUserState($userId, 'awaiting_gemini_prompt');
            $messageToSend = "好的，请直接输入您想对 Gemini 说的话。";
            $keyboard = null;
            break;
        case '请求 Cloudflare':
            setUserState($userId, 'awaiting_cloudflare_prompt');
            $messageToSend = "好的，请直接输入您想对 Cloudflare AI 说的话。";
            $keyboard = null;
            break;
        case '更换 API 密钥':
            $messageToSend = "请选择您想要更新的 API 密钥：";
            $keyboard = getApiKeySelectionKeyboard();
            break;
        case 'Gemini API Key':
            setUserState($userId, 'awaiting_api_key_GEMINI_API_KEY');
            $messageToSend = "好的，请发送您的新 Gemini API 密钥。";
            $keyboard = null;
            break;
        case '返回主菜单':
            $messageToSend = "已返回主菜单。";
            break;
        default:
            $messageToSend = "无法识别的指令，请使用下方键盘操作。";
            break;
    }

    if ($messageToSend) {
        sendTelegramMessage($chatId, $messageToSend, $keyboard);
    }
}

// Acknowledge receipt to Telegram.
http_response_code(200);
echo json_encode(['status' => 'ok']);


/**
 * Parses a message containing lottery results and saves them to the database.
 *
 * @param int $chatId The chat ID to send confirmation/error messages to.
 * @param string $text The message text from Telegram.
 */
function handleLotteryResult($chatId, $text) {
    // Example format: "265期特码, 蛇猪鸡鼠虎龙兔各数5#..."
    // Or "265期开奖号码: 01,02,03,04,05,06,07"

    // Extract Issue Number
    preg_match('/(\d+)期/', $text, $issueMatches);
    $issueNumber = $issueMatches[1] ?? null;

    if (!$issueNumber) {
        sendTelegramMessage($chatId, "❌ 无法从消息中解析期号。");
        return;
    }

    // Extract Winning Numbers (assuming a simple comma-separated list for now)
    // This regex looks for a sequence of two-digit numbers separated by commas.
    preg_match_all('/(\d{2})/', $text, $numberMatches);
    $winningNumbers = $numberMatches[0] ?? [];

    if (count($winningNumbers) < 7) {
        sendTelegramMessage($chatId, "❌ 无法从消息中解析出至少7个开奖号码。请检查格式。");
        return;
    }

    // We only want the first 7 numbers for the main result
    $winningNumbersStr = implode(',', array_slice($winningNumbers, 0, 7));

    try {
        $pdo = get_db_connection();
        $stmt = $pdo->prepare(
            "INSERT INTO lottery_results (issue_number, winning_numbers, drawing_date)
             VALUES (?, ?, CURDATE())
             ON DUPLICATE KEY UPDATE winning_numbers = VALUES(winning_numbers), drawing_date = VALUES(drawing_date)"
        );
        $stmt->execute([$issueNumber, $winningNumbersStr]);

        sendTelegramMessage($chatId, "✅ 成功记录第 {$issueNumber} 期开奖号码: `{$winningNumbersStr}`");

    } catch (PDOException $e) {
        error_log("Lottery Result DB Error: " . $e->getMessage());
        sendTelegramMessage($chatId, "❌ 保存开奖号码时发生数据库错误。");
    }
}

?>