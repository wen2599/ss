<?php
// webhook.php - Final Version with DETAILED LOGGING

// 在脚本最开始就记录，确保它被执行了
error_log("--- [WEBHOOK START] --- Request received at " . date('Y-m-d H:i:s'));

require_once 'db.php';

$env_path = __DIR__ . '/.env';
$env = parse_ini_file($env_path);
if ($env === false) {
    http_response_code(500);
    error_log("[WEBHOOK FATAL] Could not read .env file.");
    exit;
}

// 1. 安全性检查
$expected_secret = $env['TELEGRAM_WEBHOOK_SECRET'];
$received_secret = isset($_GET['secret']) ? $_GET['secret'] : '';
if (empty($expected_secret) || $received_secret !== $expected_secret) {
    http_response_code(403);
    error_log("[WEBHOOK FORBIDDEN] Incorrect or missing secret.");
    exit;
}
error_log("[WEBHOOK INFO] Secret verification successful.");

// 2. 消息处理
$update_json = file_get_contents('php://input');
error_log("[WEBHOOK INFO] Raw payload received: " . $update_json);

$update = json_decode($update_json);
if (!$update) {
    error_log("[WEBHOOK WARN] Invalid JSON or empty payload. Exiting.");
    http_response_code(200); // 仍然返回 200，避免 Telegram 重试
    exit;
}

// 3. 消息路由
if (isset($update->channel_post) && $update->channel_post->chat->id == $env['TELEGRAM_CHANNEL_ID']) {
    error_log("[WEBHOOK INFO] Handling channel post...");
    handleChannelPost($update->channel_post);
} elseif (isset($update->message)) {
    error_log("[WEBHOOK INFO] Handling private message...");
    handlePrivateMessage($update->message);
} else {
    error_log("[WEBHOOK INFO] Ignoring unknown update type.");
    http_response_code(200);
}

error_log("--- [WEBHOOK END] --- Script finished.");
exit; // 确保脚本在这里结束

// === 函数定义 ===

function handleChannelPost($channel_post) {
    global $env;
    $message_text = trim($channel_post->text);
    error_log("[handleChannelPost] Parsing message: " . $message_text);

    $lottery_type = null; $issue_number = null; $winning_numbers = null;
    $lines = explode("\n", $message_text);

    if (count($lines) >= 3) {
        if (preg_match('/^(.*?)第:(\d+)\s*期开奖结果:$/', trim($lines[1]), $matches)) {
            $lottery_type = trim($matches[1]);
            $issue_number = trim($matches[2]);
            $potential_numbers = trim($lines[2]);
            if (preg_match('/^[\d\s]+$/', $potential_numbers)) {
                $winning_numbers = preg_replace('/\s+/', ' ', $potential_numbers);
            }
        }
    }

    if ($lottery_type && $issue_number && $winning_numbers) {
        error_log("[handleChannelPost] PARSE SUCCESS: Type=$lottery_type, Issue=$issue_number, Numbers=$winning_numbers");
        
        error_log("[handleChannelPost] Attempting to get DB connection...");
        $conn = get_db_connection();

        if ($conn) {
            error_log("[handleChannelPost] DB connection successful. Preparing statement...");
            $stmt = $conn->prepare("INSERT INTO lottery_results (lottery_type, issue_number, winning_numbers) VALUES (?, ?, ?)");
            
            if ($stmt) {
                error_log("[handleChannelPost] Statement prepared. Binding parameters...");
                $stmt->bind_param("sss", $lottery_type, $issue_number, $winning_numbers);
                
                error_log("[handleChannelPost] Executing statement...");
                if ($stmt->execute()) {
                    error_log("[handleChannelPost] DB WRITE SUCCESS! Row inserted.");
                } else {
                    error_log("[handleChannelPost] DB WRITE FAILED! stmt->execute() error: " . $stmt->error);
                }
                $stmt->close();
            } else {
                error_log("[handleChannelPost] DB PREPARE FAILED! conn->prepare() error: " . $conn->error);
            }
        } else {
            error_log("[handleChannelPost] DB CONNECT FAILED! get_db_connection() returned null.");
        }
    } else {
        error_log("[handleChannelPost] PARSE FAILED. Message did not match expected format.");
    }
    http_response_code(200);
}

function handlePrivateMessage($message) {
    $chat_id = $message->chat->id;
    $text = isset($message->text) ? trim($message->text) : '';
    error_log("[handlePrivateMessage] Received from chat_id: $chat_id, text: '$text'");
    if ($text === '/start') {
        sendMessage($chat_id, "您好！Webhook 正在运行。");
    }
    http_response_code(200);
}

function sendMessage($chat_id, $text) {
    global $env;
    // ... (这里的 sendMessage 逻辑保持不变)
    $bot_token = $env['TELEGRAM_BOT_TOKEN'];
    $url = "https://api.telegram.org/bot{$bot_token}/sendMessage";
    $params = ['chat_id' => $chat_id, 'text' => $text];
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_exec($ch);
    curl_close($ch);
}
?>