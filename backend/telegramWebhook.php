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
 * Parses a complex, multi-line message containing lottery results and saves them to the database.
 *
 * @param int $chatId The chat ID to send confirmation/error messages to.
 * @param string $text The message text from Telegram.
 */
function handleLotteryResult($chatId, $text) {
    // Determine Lottery Type
    $lotteryType = '';
    if (strpos($text, '新澳门六合彩') !== false) {
        $lotteryType = '新澳门六合彩';
    } elseif (strpos($text, '香港六合彩') !== false) {
        $lotteryType = '香港六合彩';
    } elseif (strpos($text, '老澳') !== false) {
        $lotteryType = '老澳门六合彩';
    } else {
        sendTelegramMessage($chatId, "⚠️ 无法识别开奖类型。");
        return;
    }

    // Extract Issue Number
    preg_match('/第:?(\d+)\s*期/', $text, $issueMatches);
    $issueNumber = $issueMatches[1] ?? null;
    if (!$issueNumber) {
        sendTelegramMessage($chatId, "❌ 无法从消息中解析期号。");
        return;
    }

    $lines = explode("\n", trim($text));
    if (count($lines) < 3) {
        sendTelegramMessage($chatId, "❌ 消息格式不完整，至少需要3行（开奖结果，号码，生肖）。");
        return;
    }

    // Extract Numbers
    $numbersLine = $lines[1];
    preg_match_all('/\b(\d{2})\b/', $numbersLine, $numberMatches);
    $winningNumbers = $numberMatches[0];

    // Validation
    if (count($winningNumbers) < 7) {
        sendTelegramMessage($chatId, "❌ 解析失败: 号码数量不足7个。");
        return;
    }

    // We only want the first 7 numbers
    $winningNumbers = array_slice($winningNumbers, 0, 7);

    // Determine Zodiacs and Colors based on the numbers
    $zodiacs = array_map(function($num) {
        return get_zodiac_for_number($num);
    }, $winningNumbers);

    $colors = array_map(function($num) {
        return get_emoji_for_color(get_color_for_number($num));
    }, $winningNumbers);

    $winningNumbersStr = implode(',', $winningNumbers);
    $zodiacSignsStr = implode(',', $zodiacs);
    $colorsStr = implode(',', $colors);

    try {
        $pdo = get_db_connection();
        $stmt = $pdo->prepare(
            "INSERT INTO lottery_results (lottery_type, issue_number, winning_numbers, zodiac_signs, colors, drawing_date)
             VALUES (:lottery_type, :issue_number, :winning_numbers, :zodiac_signs, :colors, CURDATE())
             ON DUPLICATE KEY UPDATE
                winning_numbers = VALUES(winning_numbers),
                zodiac_signs = VALUES(zodiac_signs),
                colors = VALUES(colors),
                drawing_date = VALUES(drawing_date)"
        );

        $stmt->execute([
            ':lottery_type' => $lotteryType,
            ':issue_number' => $issueNumber,
            ':winning_numbers' => $winningNumbersStr,
            ':zodiac_signs' => $zodiacSignsStr,
            ':colors' => $colorsStr
        ]);

        $report = "✅ *开奖结果记录成功*\n\n";
        $report .= "类型: `{$lotteryType}`\n";
        $report .= "期号: `{$issueNumber}`\n";
        $report .= "号码: `{$winningNumbersStr}`\n";
        $report .= "生肖: `{$zodiacSignsStr}`\n";
        $report .= "波色: `{$colorsStr}`\n";
        sendTelegramMessage($chatId, $report);

    } catch (PDOException $e) {
        error_log("Lottery Result DB Error: " . $e->getMessage());
        $error_report = "❌ *数据库错误*\n\n";
        $error_report .= "在保存【{$lotteryType}】第 {$issueNumber} 期开奖结果时发生错误。\n\n";
        $error_report .= "错误信息: `{$e->getMessage()}`";
        sendTelegramMessage($chatId, $error_report);
    }
}

?>