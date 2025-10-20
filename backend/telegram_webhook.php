<?php
// backend/telegram_webhook.php

// Bootstrap logging - MUST be the very first thing.
// This helps debug cases where the script is not even being executed.
$log_file = __DIR__ . '/../telegram_bootstrap.log';
$timestamp = date("Y-m-d H:i:s");
$request_body = file_get_contents('php://input');
// Use FILE_APPEND to not overwrite the log on each request.
file_put_contents($log_file, "[$timestamp] Webhook received.\n", FILE_APPEND);
if (empty($request_body)) {
    file_put_contents($log_file, "[$timestamp] Request body was empty.\n", FILE_APPEND);
} else {
    file_put_contents($log_file, "[$timestamp] Request body: $request_body\n", FILE_APPEND);
}

// Handles incoming updates from the Telegram Bot API, with stateful interactions.

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/telegram_helpers.php';
require_once __DIR__ . '/user_state_manager.php';
require_once __DIR__ . '/db_operations.php';

// --- Security Check ---
// Verify the request is coming from Telegram using the secret token.
// The token can be provided in a header or as a URL parameter.
$expectedToken = $_ENV['TELEGRAM_WEBHOOK_SECRET'] ?? '';
$providedToken = '';

// Check header first
if (isset($_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'])) {
    $providedToken = $_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'];
}
// If not in header, check URL parameter (for services that don't support custom headers)
elseif (isset($_GET['secret'])) {
    $providedToken = $_GET['secret'];
}

if (empty($expectedToken) || $providedToken !== $expectedToken) {
    http_response_code(403);
    // Log the failed attempt for debugging, but be careful not to log sensitive info
    custom_log('Forbidden: Invalid or missing secret token. Header: ' . ($_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] ?? 'Not Set') . ', Query: ' . ($_GET['secret'] ?? 'Not Set'), 'WARNING');
    die('Forbidden: Invalid secret token.');
}

// Get the update from the request body.
$update = json_decode(file_get_contents('php://input'), true);

if (!$update) {
    // No update data received.
    exit();
}

// --- Process Update ---
// Check if the update is a message and contains text.
if (isset($update['message'])) {
    $message = $update['message'];
    $chatId = $message['chat']['id'];
    $text = trim($message['text']);
    $userId = $message['from']['id']; // Telegram user ID

    // Check if the user exists in our database, if not, create a basic entry
    $dbUser = fetchOne($pdo, "SELECT id, telegram_chat_id, user_state, state_data FROM users WHERE telegram_chat_id = :chat_id", [':chat_id' => $chatId]);
    if (!$dbUser) {
        // Create a temporary user entry if it's a new Telegram chat ID
        insert($pdo, 'users', [
            'username' => 'telegram_user_' . $chatId,
            'password' => password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT), // Dummy password
            'email' => 'telegram_user_' . $chatId . '@example.com', // Dummy email
            'telegram_chat_id' => $chatId,
            'user_state' => STATE_NONE
        ]);
        $dbUser = fetchOne($pdo, "SELECT id, telegram_chat_id, user_state, state_data FROM users WHERE telegram_chat_id = :chat_id", [':chat_id' => $chatId]);
    }

    $currentStateAndData = getUserStateAndData($pdo, $chatId);
    $currentState = $currentStateAndData['state'];
    $stateData = $currentStateAndData['data'];

    // --- Command Handling ---
    if ($text[0] === '/') {
        // Clear state if a new command is issued
        clearUserStateAndData($pdo, $chatId);
        $command = explode(' ', $text)[0];
        switch ($command) {
            case '/start':
                $responseText = "欢迎！我是一个可以帮助您管理账单的机器人。\n\n"
                              . "您可以使用以下命令：\n"
                              . "/register - 创建一个新账户\n"
                              . "/login - 将您的 Telegram 关联到现有账户\n"
                              . "/bills - 查看您的最新账单";
                sendTelegramMessage($chatId, $responseText);
                break;

            case '/register':
                setUserStateAndData($pdo, $chatId, STATE_AWAITING_REGISTER_USERNAME);
                sendTelegramMessage($chatId, "让我们开始创建账户。请输入您期望的用户名：");
                break;

            case '/login':
                setUserStateAndData($pdo, $chatId, STATE_AWAITING_LOGIN_USERNAME);
                sendTelegramMessage($chatId, "好的，让我们关联您的账户。请输入您的用户名或邮箱：");
                break;

            case '/bills':
                $userFromDb = fetchOne($pdo, "SELECT id, username FROM users WHERE telegram_chat_id = :chat_id AND username IS NOT NULL AND email IS NOT NULL AND password IS NOT NULL", [':chat_id' => $chatId]);
                if ($userFromDb && !str_starts_with($userFromDb['username'], 'telegram_user_')) {
                    $bills = fetchAll($pdo, "SELECT subject, amount, due_date FROM bills WHERE user_id = :user_id ORDER BY received_at DESC LIMIT 5", [':user_id' => $userFromDb['id']]);
                    if ($bills) {
                        $responseText = "<b>您的最新账单：</b>\n\n";
                        foreach ($bills as $bill) {
                            $responseText .= "- " . htmlspecialchars($bill['subject']) . " - 金额：" . ($bill['amount'] ?? '未知') . " (到期日: " . ($bill['due_date'] ?? '未知') . ")\n";
                        }
                    } else {
                        $responseText = "您还没有任何账单。";
                    }
                } else {
                    $responseText = "您尚未完成注册或登录。请先使用 /register 或 /login。";
                }
                sendTelegramMessage($chatId, $responseText);
                break;

            default:
                sendTelegramMessage($chatId, "未知命令。请输入 /start 查看可用的命令列表。");
                break;
        }
    } else {
        // --- State-based Input Handling ---
        switch ($currentState) {
            case STATE_AWAITING_REGISTER_USERNAME:
                $stateData['username'] = $text;
                setUserStateAndData($pdo, $chatId, STATE_AWAITING_REGISTER_EMAIL, $stateData);
                sendTelegramMessage($chatId, "好的。现在，请输入您的邮箱地址：");
                break;
            case STATE_AWAITING_REGISTER_EMAIL:
                if (!filter_var($text, FILTER_VALIDATE_EMAIL)) {
                    sendTelegramMessage($chatId, "这个邮箱地址格式似乎不正确，请再试一次：");
                    break;
                }
                $stateData['email'] = $text;
                setUserStateAndData($pdo, $chatId, STATE_AWAITING_REGISTER_PASSWORD, $stateData);
                sendTelegramMessage($chatId, "谢谢。最后，请设置一个安全的密码：");
                break;
            case STATE_AWAITING_REGISTER_PASSWORD:
                $stateData['password'] = $text;
                
                // Attempt to register the user
                $newUserId = registerUserViaTelegram($pdo, $stateData['username'], $stateData['email'], $stateData['password'], $chatId);

                if ($newUserId) {
                    sendTelegramMessage($chatId, "注册成功！您的账户已经成功关联。现在您可以使用 /bills 命令了。");
                    clearUserStateAndData($pdo, $chatId);
                } else {
                    sendTelegramMessage($chatId, "注册失败。用户名或邮箱可能已被占用。请使用 /register 命令尝试其他信息，或者如果您已有账户，请使用 /login。");
                    clearUserStateAndData($pdo, $chatId);
                }
                break;

            case STATE_AWAITING_LOGIN_USERNAME:
                $stateData['username_or_email'] = $text;
                setUserStateAndData($pdo, $chatId, STATE_AWAITING_LOGIN_PASSWORD, $stateData);
                sendTelegramMessage($chatId, "请输入您的密码：");
                break;
            case STATE_AWAITING_LOGIN_PASSWORD:
                $user = authenticateTelegramUser($pdo, $stateData['username_or_email'], $text);
                if ($user) {
                    // Link Telegram chat ID to existing user
                    linkTelegramUser($pdo, $user['id'], $chatId);
                    sendTelegramMessage($chatId, "登录成功！您的 Telegram 已成功关联到您的账户。");
                    clearUserStateAndData($pdo, $chatId);
                } else {
                    sendTelegramMessage($chatId, "登录失败。用户名/邮箱或密码无效。请重新使用 /login 命令。");
                    clearUserStateAndData($pdo, $chatId);
                }
                break;

            case STATE_NONE:
            default:
                sendTelegramMessage($chatId, "我不明白您的意思。请使用 /start, /register, 或 /login 等命令。");
                break;
        }
    }
}

// Acknowledge receipt of the update to Telegram.
http_response_code(200);
echo json_encode(['status' => 'ok']);
