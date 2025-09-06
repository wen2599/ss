<?php
// --- Telegram Bot Webhook Handler with Admin Support ---

require_once 'db.php'; // 数
require_once 'config.php'; //

// 1. 配置区
$BOT_TOKEN = $TELEGRAM_BOT_TOKEN ?? 'YOUR_BOT_TOKEN';
$API_URL = 'https://api.telegram.org/b
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
    $postFields =
        'text' => $text,
        'show_alert' => true
    ];
    sendRequest($url, $postFields);
}

function editMessageText($chatId, $messageId, $text, $replyMarkup = null) {
    $url = $GLOBALS['API_URL'] . 'editMessageText';
    $postFields = [
        'chat_id' => $chatId,
        'message_id' => $messageId,
        'text' => $text,
        'parse_mode' => 'Markdown'
    ];
    if ($replyMarkup) {
        $postFields['reply_markup'] = $replyMarkup;
    }
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

require_once 'data_access.php';

// --- 主逻辑 ---
$content = file_get_contents("php://input");
$update = json_decode($content, true);

if (isset($update["message"])) {
    $chatId = (string)$update["message"]["chat"]["id"];
    $userId = (string)$update["message"]["from"]["id"];
    $userName = $update["message"]["from"]["first_name"];
    $text = trim($update["message"]["text"] ?? '');
    $reply = '';
    $replyMarkup = null;

    $activeRoomId = get_active_room_id_for_chat($chatId);

    if ($text === '/newgame') {
        if ($activeRoomId) {
            $reply = "此聊天中已有一个正在进行的游戏。";
        } else {
            $newRoomId = create_game_and_room($chatId, $userId, $userName);
            if ($newRoomId) {
                $reply = "新游戏已创建 (ID: $newRoomId)! 等待玩家加入...";
                $replyMarkup = [
                    'inline_keyboard' => [[
                        ['text' => '加入游戏 (1/4)', 'callback_data' => 'join_game:' . $newRoomId]
                    ]]
                ];
            } else {
                $reply = "创建游戏失败，请稍后再试。";
            }
        }
    } elseif ($text === '/start') {
         $reply = "欢迎来到十三张云端游戏机器人！\n使用 /newgame 创建新对局。";
         $replyMarkup = $userKeyboard;
    } elseif ($activeRoomId) {
        $game = load_game_state($activeRoomId);
        if (!$game) {
            sendMessage($chatId, "错误：找不到当前游戏。");
            exit;
        }

        $parts = explode(' ', $text, 2);
        $command = $parts[0];
        $args = $parts[1] ?? '';
        $success = false;

        switch ($command) {
            case '/sethand':
                if ($game->getState()['state'] !== 'arranging') {
                    $reply = "现在不是理牌阶段。";
                    break;
                }
                // Expected format: /sethand <3 cards> | <5 cards> | <5 cards>
                $handParts = explode('|', $args);
                if (count($handParts) !== 3) {
                    $reply = "格式错误。请使用: /sethand S2 S3 S4 | S5 S6 S7 S8 S9 | SA SK SQ SJ S10";
                    break;
                }

                $front = array_filter(array_map('strtoupper', explode(' ', trim($handParts[0]))));
                $middle = array_filter(array_map('strtoupper', explode(' ', trim($handParts[1]))));
                $back = array_filter(array_map('strtoupper', explode(' ', trim($handParts[2]))));


                if (count($front) !== 3 || count($middle) !== 5 || count($back) !== 5) {
                    $reply = "牌数错误。前墩3张，中墩5张，底墩5张。";
                    break;
                }

                $success = $game->setPlayerHand($userId, $front, $middle, $back);
                if ($success) {
                    save_game_state($game);
                    $reply = "玩家 `$userName` 已确认牌型。";

                    $newState = $game->getState();
                    if ($newState['state'] === 'finished') {
                        $reply .= "\n\n所有玩家已准备就绪，游戏结束！\n\n**比分结果:**\n";
                        $player_scores = [];
                        foreach ($newState['players'] as $p_id => $p_data) {
                            $player_scores[$p_id] = 0;
                        }

                        foreach($newState['comparison_results'] as $key => $score) {
                            list($p1_id, $vs, $p2_id) = explode('_', $key);
                            $player_scores[$p1_id] += $score;
                            $player_scores[$p2_id] -= $score;
                        }

                        foreach($player_scores as $p_id => $score) {
                            $playerName = $newState['players'][$p_id]['name'] ?? $p_id;
                             $score_str = $score > 0 ? "+".$score : $score;
                            $reply .= "玩家 `$playerName`: $score_str\n";
                        }
                    }
                } else {
                    $reply = "设置牌型失败，请检查牌是否正确或重复。";
                }
                break;

            case '/hand':
                $player = $game->getPlayer($userId);
                if ($player) {
                    $hand = $player->getHand();
                    $handStr = implode(' ', $hand);
                    sendMessage($userId, "你的手牌: `$handStr`");
                    $reply = "你的手牌已私信发送。";
                }
                break;

            case '/status':
                $state = $game->getState();
                $reply = "当前游戏状态: " . $state['state'] . ".";
                if ($state['state'] === 'arranging') {
                    $readyPlayers = 0;
                    foreach ($state['players'] as $p) {
                        if ($p['hand_is_set']) $readyPlayers++;
                    }
                    $reply .= "\n" . $readyPlayers . "/" . count($state['players']) . " 位玩家已准备就绪。";
                }
                break;

            default:
                $reply = "未知游戏命令。";
                break;
        }

    } else {
        $reply = "没有正在进行的游戏。使用 /newgame 创建新对局。";
    }

    if ($reply) {
        sendMessage($chatId, $reply, $replyMarkup);
    }

} elseif (isset($update["callback_query"])) {
    $callbackQuery = $update["callback_query"];
    $chatId = (string)$callbackQuery["message"]["chat"]["id"];
    $callbackQueryId = $callbackQuery["id"];
    $userId = (string)$callbackQuery["from"]["id"];
    $userName = $callbackQuery["from"]["first_name"];
    $data = $callbackQuery["data"];

    $parts = explode(':', $data);
    $action = $parts[0];

    if ($action === 'join_game') {
        $gameIdToJoin = (int)$parts[1];
        $messageId = $callbackQuery["message"]["message_id"];

        $success = add_player_to_game($gameIdToJoin, $userId, $userName);

        if ($success) {
            answerCallbackQuery($callbackQueryId, "你已成功加入游戏！");

            $playerCount = get_player_count($gameIdToJoin);

            if ($playerCount < 4) {
                // Update the button with the new player count
                $newText = "新游戏已创建 (ID: $gameIdToJoin)! 等待玩家加入...";
                $newMarkup = [
                    'inline_keyboard' => [[
                        ['text' => "加入游戏 ($playerCount/4)", 'callback_data' => 'join_game:' . $gameIdToJoin]
                    ]]
                ];
                editMessageText($chatId, $messageId, $newText, $newMarkup);
            } else {
                // Game is full, let's start it
                editMessageText($chatId, $messageId, "游戏满员，对局开始！", null);

                $game = load_game_state($gameIdToJoin);
                if ($game) {
                     sendMessage($chatId, "发牌完毕！请检查私信手牌并使用 /sethand 命令理牌。");
                }
            }
        } else {
            answerCallbackQuery($callbackQueryId, "加入游戏失败！可能是游戏已满或您已加入。");
        }
    } elseif ($action === 'check_points') {
        $msg = "您的积分查询功能待完善";
        answerCallbackQuery($callbackQueryId, $msg);
    }
}

$conn->close();
