<?php
require_once '../db.php';
require_once '../functions.php';

function handleWebhook() {
    global $dotenv;
    $update = json_decode(file_get_contents('php://input'), true);
    $chatId = $update['message']['chat']['id'];
    $text = $update['message']['text'];

    if ($chatId != $dotenv['ADMIN_CHAT_ID']) {
        sendTelegram('Only admin allowed', $chatId);
        return;
    }

    // 命令解析
    if (strpos($text, '/delete_user ') === 0) {
        $userEmail = substr($text, 13);
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$userEmail]);
        $userId = $stmt->fetchColumn();
        if ($userId) {
            // 调用 user.php delete
            $ch = curl_init('https://wenge.cloudns.ch/api/user/delete');
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['user_id' => $userId]));
            curl_exec($ch);
            sendTelegram('User deleted', $chatId);
        }
    } elseif (strpos($text, '/authorize_email ') === 0) {
        $email = substr($text, 17);
        $pdo->prepare("INSERT INTO authorized_emails (email, authorized_by_admin) VALUES (?, ?)")->execute([$email, $chatId]);
        sendTelegram('Email authorized', $chatId);
    } elseif (strpos($text, '/get_channel_lottery') === 0) {
        // 监听频道 (用 Telegram API poll 或 webhook，但简化为手动触发)
        // 假设频道消息已知，解析并存
        $channelMessage = getChannelMessage();  // 自定义函数，用 curl 调用 Telegram API
        parseAndInsertLottery($channelMessage);
        sendTelegram('Lottery updated', $chatId);
    }
}

function sendTelegram($msg, $chatId) {
    global $dotenv;
    $url = "https://api.telegram.org/bot{$dotenv['TELEGRAM_BOT_TOKEN']}/sendMessage?chat_id=$chatId&text=" . urlencode($msg);
    file_get_contents($url);
}

function getChannelMessage() {
    // 用 curl 调用 Telegram getUpdates 或 exportChatInviteLink 等，假设实现
    return '示例频道消息: 六合彩第20251030期: 正选 1 2 3 4 5 6 特码 7';  // 实际替换
}

function parseAndInsertLottery($msg) {
    global $pdo;
    // 解析 msg 到 period, numbers, special
    $period = '20251030';
    $numbers = json_encode([1,2,3,4,5,6]);
    $special = 7;
    $pdo->prepare("INSERT IGNORE INTO lottery_results (period, numbers, special, draw_time) VALUES (?, ?, ?, NOW())")
        ->execute([$period, $numbers, $special]);
}