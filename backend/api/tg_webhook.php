<?php
// backend/api/tg_webhook.php

// Include the main configuration file
require_once __DIR__ . '/config.php';

/**
 * Sends a message to a specific Telegram chat, optionally with a custom keyboard.
 *
 * @param int      $chat_id The ID of the chat to send the message to.
 * @param string   $text    The message text to send.
 * @param array|null $keyboard Optional. A 2D array defining the keyboard layout.
 */
function sendMessage(int $chat_id, string $text, ?array $keyboard = null): void {
    $bot_token = TELEGRAM_BOT_TOKEN;
    if (empty($bot_token) || $bot_token === 'YOUR_TELEGRAM_BOT_TOKEN') {
        error_log("Telegram Bot Token is not configured.");
        return;
    }

    $url = "https://api.telegram.org/bot{$bot_token}/sendMessage";
    $post_fields = [
        'chat_id' => $chat_id,
        'text'    => $text,
        'parse_mode' => 'HTML'
    ];

    if ($keyboard) {
        $reply_markup = [
            'keyboard' => $keyboard,
            'resize_keyboard' => true,
            'one_time_keyboard' => false // Keep the keyboard open
        ];
        $post_fields['reply_markup'] = json_encode($reply_markup);
    }

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    // Force IPv4 resolution, which can help in some network environments.
    curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post_fields));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if (curl_errno($ch) || $http_code !== 200) {
        // Log errors if sending fails, but don't output to user
        error_log("Failed to send message to Telegram. HTTP Code: {$http_code}. Response: {$response}");
    }
    curl_close($ch);
}

// Immediately acknowledge the request to Telegram to prevent timeouts
http_response_code(200);
echo json_encode(['status' => 'ok']);
if (function_exists('fastcgi_finish_request')) {
    fastcgi_finish_request();
}

// Get the raw POST data from the webhook
$update_json = file_get_contents('php://input');
$update = json_decode($update_json, true);

if (!$update || !isset($update['message']['text'])) {
    exit();
}

$message = $update['message'];
$chat_id = $message['chat']['id'];
$user_id = $message['from']['id'];
$text = trim($message['text']);

// --- Security Check: Only allow the Super Admin ---
$super_admin_id = defined('TELEGRAM_SUPER_ADMIN_ID') ? TELEGRAM_SUPER_ADMIN_ID : 0;
if ($user_id != $super_admin_id) {
    sendMessage($chat_id, "<b>权限不足。</b> 您无权使用此机器人。");
    exit();
}

// --- Command Processing ---
if (strpos($text, '/') === 0) {
    $parts = explode(' ', $text, 2);
    $command = $parts[0];
    $argument = trim($parts[1] ?? '');

    require_once __DIR__ . '/database.php';

    try {
        $pdo = getDbConnection();

        $keyboard = null; // Initialize keyboard as null
        switch ($command) {
            case '/start':
                $response_text = "欢迎您，管理员！请使用下面的键盘或直接输入命令。";
                $keyboard = [
                    ['/listusers']
                ];
                break;

            case '/listusers':
                $stmt = $pdo->query("SELECT id, email, created_at FROM users ORDER BY id ASC");
                $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
                if (empty($users)) {
                    $response_text = "数据库中未找到用户。";
                } else {
                    $response_text = "<b>用户列表 (" . count($users) . "):</b>\n\n";
                    foreach ($users as $user) {
                        $response_text .= "<b>ID:</b> " . htmlspecialchars($user['id']) . "\n";
                        $response_text .= "<b>邮箱:</b> <code>" . htmlspecialchars($user['email']) . "</code>\n";
                        $response_text .= "<b>创建时间:</b> " . htmlspecialchars($user['created_at']) . "\n\n";
                    }
                }
                break;

            case '/deleteuser':
                if (empty($argument) || !filter_var($argument, FILTER_VALIDATE_EMAIL)) {
                    $response_text = "请输入有效的用户邮箱以删除。\n用法: <code>/deleteuser user@example.com</code>";
                } else {
                    $email_to_delete = $argument;
                    $stmt = $pdo->prepare("DELETE FROM users WHERE email = :email");
                    $stmt->execute([':email' => $email_to_delete]);

                    if ($stmt->rowCount() > 0) {
                        $response_text = "✅ 成功删除用户: <code>" . htmlspecialchars($email_to_delete) . "</code>";
                    } else {
                        $response_text = "⚠️ 未找到用户: <code>" . htmlspecialchars($email_to_delete) . "</code>";
                    }
                }
                break;

            default:
                $response_text = "未知命令: " . htmlspecialchars($command);
                break;
        }

        sendMessage($chat_id, $response_text, $keyboard);

    } catch (Exception $e) {
        error_log("Bot command failed: " . $e->getMessage());
        sendMessage($chat_id, "<b>错误:</b> 处理您的命令时发生内部错误。");
    }
}
?>
