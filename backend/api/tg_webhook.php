<?php
// --- Telegram Bot Webhook Handler with Admin Support ---

require_once 'db.php'; // 数
require_once 'config.php'; //

// 1. 配置区
$BOT_TOKEN = $TELEGRAM_BOT_TOKEN ?? 'YOUR_BOT_TOKEN';
$API_URL = 'https://api.telegram.org/bot' . $BOT_TOKEN . '/';
$GAME_URL = $TELEGRAM_GAME_URL ?? 'https://your-game-url.c
// 2. 工具函数
function sendMessage($chatId,
    $url = $GLOBALS['API_URL'] . 'sendMessage';
    $postFields = [
        'chat_id' => $chatId,
        'text' => $tex
        'parse_mode' => 'Markdown'
    ];
    if ($replyMarkup) {
        $postFields['reply_markup'] 
    sendRequest($url, $postFields);
}

function answerCallbackQuery($callbackQueryId, $text) {
    $url = $GLOBALS['API_URL'] . 'answerCallbackQuery';
    $postFields = [
        'callback_query_id' => $callbackQueryId,
        'text' => $text,
        'show_alert' => true
    ];
    sendRequest($url, $postFields);
}

function sendRequest($url, $postFields) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postFields));
    curl_exec($ch);
    curl_close($ch);
}

// 判断是否为管理员
function isAdmin($conn, $chatId) {
    $stmt = $conn->prepare("SELECT 1 FROM tg_admins WHERE chat_id = ?");
    $stmt->bind_param("i", $chatId);
    $stmt->execute();
    $isAdmin = $stmt->get_result()->num_rows > 0;
    $stmt->close();
    return $isAdmin;
}

// --- 自定义键盘 ---
$adminKeyboard = [
    'keyboard' => [
        [['text' => '/listusers'], ['text' => '/broadcast']],
        [['text' => '/addpoints'], ['text' => '/deluser']]
    ],
    'resize_keyboard' => true
];
$userKeyboard = [
    'keyboard' => [
        [['text' => '游戏规则'], ['text' => '联系客服']]
    ],
    'resize_keyboard' => true
];
$welcomeInlineKeyboard = [
    'inline_keyboard' => [[
        ['text' => '开始游戏', 'url' => $GAME_URL],
        ['text' => '我的积分', 'callback_data' => 'check_points']
    ]]
];

// --- 主逻辑 ---
$content = file_get_contents("php://input");
$update = json_decode($content, true);

if (isset($update["message"])) {
    $chatId = $update["message"]["chat"]["id"];
    $text = trim($update["message"]["text"] ?? '');
    $reply = '';
    $replyMarkup = isAdmin($conn, $chatId) ? $adminKeyboard : $userKeyboard;

    if ($text === '/start') {
        $reply = "欢迎来到斗地主云端游戏机器人！";
        $replyMarkup = $welcomeInlineKeyboard;
    } else {
        $parts = explode(' ', $text);
        $command = strtolower($parts[0]);

        // 管理员命令
        if (isAdmin($conn, $chatId)) {
            switch ($command) {
                case '/listusers':
                    $result = $conn->query("SELECT id, phone, points FROM users ORDER BY id ASC LIMIT 100");
                    $reply = "玩家列表:\n---------------------\n";
                    while($row = $result->fetch_assoc()) {
                        $reply .= "ID: `{$row['id']}`\n手机: `{$row['phone']}`\n积分: `{$row['points']}`\n\n";
                    }
                    break;
                case '/broadcast':
                    $msg = trim(implode(' ', array_slice($parts, 1)));
                    if (!$msg) {
                        $reply = "用法: /broadcast 消息内容";
                    } else {
                        $usersRes = $conn->query("SELECT id FROM tg_admins");
                        $count = 0;
                        while ($u = $usersRes->fetch_assoc()) {
                            sendMessage($u['id'], "[公告]\n" . $msg);
                            $count++;
                        }
                        $reply = "公告已推送给 {$count} 位TG管理员。";
                    }
                    break;
                case '/addpoints':
                    if (count($parts) < 3) {
                        $reply = "用法: /addpoints 用户ID 积分数";
                    } else {
                        $uid = intval($parts[1]);
                        $amount = intval($parts[2]);
                        if ($uid > 0 && $amount != 0) {
                            $stmt = $conn->prepare("UPDATE users SET points = points + ? WHERE id = ?");
                            $stmt->bind_param("ii", $amount, $uid);
                            $stmt->execute();
                            $stmt->close();
                            $reply = "已为用户 {$uid} 增加 {$amount} 积分。";
                        } else {
                            $reply = "参数错误。";
                        }
                    }
                    break;
                case '/deluser':
                    if (count($parts) < 2) {
                        $reply = "用法: /deluser 用户ID";
                    } else {
                        $uid = intval($parts[1]);
                        if ($uid > 0) {
                            $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
                            $stmt->bind_param("i", $uid);
                            $stmt->execute();
                            $stmt->close();
                            $reply = "已删除ID为 {$uid} 的用户。";
                        } else {
                            $reply = "参数错误。";
                        }
                    }
                    break;
                default:
                    // 普通用户命令
                    if ($command === '游戏规则') {
                        $reply = "【游戏规则】\n三人斗地主，先出完牌的一方获胜。";
                    } elseif ($command === '联系客服') {
                        $reply = "请联系 @your_support_username";
                    } else {
                        $reply = "你好！请使用下方菜单或输入命令。";
                    }
                    break;
            }
        } else {
            // 非管理员
            switch ($command) {
                case '游戏规则':
                    $reply = "【游戏规则】\n三人斗地主，先出完牌的一方获胜。";
                    break;
                case '联系客服':
                    $reply = "联系 @your_support_username";
                    break;
                default:
                    $reply = "请使用下方的菜单或输入命令。";
                    break;
            }
        }
    }
    sendMessage($chatId, $reply, $replyMarkup);

} elseif (isset($update["callback_query"])) {
    $callbackQuery = $update["callback_query"];
    $chatId = $callbackQuery["message"]["chat"]["id"];
    $callbackQueryId = $callbackQuery["id"];
    $data = $callbackQuery["data"];

    if ($data === 'check_points') {
        // 查询积分（需绑定TG chat_id与user id，暂未实现）
        $msg = "您的积分查询功能待完善";
        answerCallbackQuery($callbackQueryId, $msg);
    }
}

$conn->close();
