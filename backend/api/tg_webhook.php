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
    $postFields = [
        'callback_query_id' => $callbackQueryId,
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

    $activeGameId = get_active_game_id_for_chat($chatId);

    if ($text === '/newgame') {
        if ($activeGameId) {
            $reply = "此聊天中已有一个正在进行的游戏。";
        } else {
            $newGameId = create_game_and_room($chatId, $userId, $userName);
            if ($newGameId) {
                $reply = "新游戏已创建 (ID: $newGameId)! 等待玩家加入...";
                $replyMarkup = [
                    'inline_keyboard' => [[
                        ['text' => '加入游戏 (1/3)', 'callback_data' => 'join_game:' . $newGameId]
                    ]]
                ];
            } else {
                $reply = "创建游戏失败，请稍后再试。";
            }
        }
    } elseif ($text === '/start') {
         $reply = "欢迎来到斗地主云端游戏机器人！\n使用 /newgame 创建新对局。";
         $replyMarkup = $userKeyboard;
    } elseif ($activeGameId) {
        $game = load_game_state($activeGameId);
        if (!$game) {
            sendMessage($chatId, "错误：找不到当前游戏。");
            exit;
        }

        $parts = explode(' ', $text, 2);
        $command = $parts[0];
        $args = $parts[1] ?? '';
        $success = false;

        switch ($command) {
            case '/bid':
                if ($game->getState()['state'] !== 'bidding') {
                    $reply = "现在不是叫地主阶段。";
                    break;
                }
                $bidValue = (int)($args ?? -1);
                if (!in_array($bidValue, [1, 2, 3])) {
                    $reply = "无效的出价。请出价 1, 2, 或 3。 (/bid 1)";
                    break;
                }

                $oldState = $game->getState();
                $success = $game->processBid($userId, $bidValue);

                if ($success) {
                    save_game_state($game);
                    $newState = $game->getState();
                    $reply = "玩家 `$userId` 出价 **$bidValue**。";

                    if ($newState['state'] === 'bidding') {
                        $reply .= "\n\n轮到玩家 `{$newState['current_turn']}` 叫地主。 (/bid 1, 2, 3 或 /pass)";
                    } elseif ($newState['state'] === 'playing') {
                        $landlordId = $newState['landlord_player_id'];
                        $bottomCardsStr = implode(' ', $oldState['landlords_cards']);
                        $reply .= "\n\n叫地主结束！地主是玩家 `$landlordId`。";
                        $reply .= "\n底牌是: `$bottomCardsStr` (已加入地主手牌)";
                        $reply .= "\n\n轮到地主 `$landlordId` 首先出牌。";
                    } elseif ($newState['state'] === 'misdeal') {
                        $reply .= "\n\n所有玩家都不叫，本局流局。";
                    }
                } else {
                    $reply = "出价无效 (可能太低或者不是你的回合)。";
                }
                break;

            case '/play':
                $cards = explode(' ', strtoupper($args));
                $success = $game->playCards($userId, $cards);
                if ($success) {
                    save_game_state($game);
                    $playedCardsStr = implode(' ', $cards);
                    $reply = "玩家 `$userId` 出牌: `$playedCardsStr`";
                    if ($game->getState()['state'] === 'finished') {
                        $landlordId = $game->getState()['landlord_player_id'];
                        if ($userId === $landlordId) {
                            $reply .= "\n\n**游戏结束！地主 `$userId` 获胜！**";
                        } else {
                            $reply .= "\n\n**游戏结束！农民团队获胜！** (玩家 `$userId` 首先出完牌)";
                        }
                    }
                } else {
                    $reply = "出牌无效！请检查你的牌或规则。";
                }
                break;

            case '/pass':
                if ($game->getState()['state'] === 'bidding') {
                    $success = $game->processBid($userId, 0); // 0 means pass
                    if ($success) {
                        save_game_state($game);
                        $newState = $game->getState();
                        $reply = "玩家 `$userId` 选择不叫。";
                        if ($newState['state'] === 'bidding') {
                             $reply .= "\n\n轮到玩家 `{$newState['current_turn']}` 叫地主。 (/bid 1, 2, 3 或 /pass)";
                        } elseif ($newState['state'] === 'misdeal') {
                            $reply .= "\n\n所有玩家都不叫，本局流局。";
                        }
                    } else {
                        $reply = "现在不能不叫。";
                    }
                } else { // Pass during play
                    $success = $game->passTurn($userId);
                    if ($success) {
                        save_game_state($game);
                        $reply = "玩家 `$userId` 选择 pass。";
                    } else {
                        $reply = "现在不能 pass。";
                    }
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
                $turnId = $state['current_turn'];
                $lastPlayStr = !empty($state['last_played_cards']) ? implode(' ', $state['last_played_cards']) : '无';
                $reply = "轮到玩家 `$turnId` 操作。\n上一手牌: `$lastPlayStr`";
                break;

            default:
                $reply = "未知游戏命令。";
                break;
        }

        if ($success) {
             $newState = $game->getState();
             if ($newState['state'] === 'playing') {
                 $nextPlayerId = $newState['current_turn'];
                 $reply .= "\n\n轮到玩家 `$nextPlayerId`。";
             }
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

            if ($playerCount < 3) {
                // Update the button with the new player count
                $newText = "新游戏已创建 (ID: $gameIdToJoin)! 等待玩家加入...";
                $newMarkup = [
                    'inline_keyboard' => [[
                        ['text' => "加入游戏 ($playerCount/3)", 'callback_data' => 'join_game:' . $gameIdToJoin]
                    ]]
                ];
                editMessageText($chatId, $messageId, $newText, $newMarkup);
            } else {
                // Game is full, let's start it
                editMessageText($chatId, $messageId, "游戏满员，对局开始！", null);

                $game = load_game_state($gameIdToJoin);
                if ($game) {
                    $state = $game->getState();
                    $firstBidderId = $state['current_turn'];
                    // This is tricky, I need the player's name, not just ID.
                    // For now, just use the ID.
                    sendMessage($chatId, "发牌完毕！\n请玩家 `$firstBidderId` 开始叫地主。");
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
