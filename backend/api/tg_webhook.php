<?php
// --- Dou Dizhu Telegram Bot Webhook ---
// Rewritten from scratch to fix file corruption.

// --- SETUP ---
// Set error reporting for debugging during development.
// On a production server, this should be logged to a file instead.
// error_reporting(E_ALL);
// ini_set('display_errors', 1);

// Include all necessary files. The order is important.
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/game.php';
require_once __DIR__ . '/data_access.php';


// --- CONFIGURATION ---
$BOT_TOKEN = $TELEGRAM_BOT_TOKEN ?? null;
if (!$BOT_TOKEN) {
    // If the token is missing, we can't do anything. Exit gracefully.
    // A log here would be good in a real production environment.
    exit();
}
$API_URL = 'https://api.telegram.org/bot' . $BOT_TOKEN . '/';


// --- TELEGRAM API HELPERS ---

/**
 * Sends a request to the Telegram Bot API using cURL.
 * @param string $method The API method to call (e.g., 'sendMessage').
 * @param array $data The data to send with the request.
 */
function sendRequest(string $method, array $data) {
    $url = $GLOBALS['API_URL'] . $method;
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    // It's good practice to log the response from Telegram for debugging.
    // $response = curl_exec($ch);
    // error_log("Telegram Response: " . $response);
    curl_exec($ch);
    curl_close($ch);
}

/**
 * Sends a text message to a chat.
 */
function sendMessage(string $chatId, string $text, ?array $replyMarkup = null) {
    $data = [
        'chat_id' => $chatId,
        'text' => $text,
        'parse_mode' => 'Markdown'
    ];
    if ($replyMarkup) {
        $data['reply_markup'] = $replyMarkup;
    }
    sendRequest('sendMessage', $data);
}

/**
 * Answers a callback query (e.g., from a button press).
 */
function answerCallbackQuery(string $callbackQueryId, string $text) {
    $data = [
        'callback_query_id' => $callbackQueryId,
        'text' => $text,
        'show_alert' => false // Using a non-modal alert
    ];
    sendRequest('answerCallbackQuery', $data);
}

/**
 * Edits the text of a message that has already been sent.
 */
function editMessageText(string $chatId, int $messageId, string $text, ?array $replyMarkup = null) {
    $data = [
        'chat_id' => $chatId,
        'message_id' => $messageId,
        'text' => $text,
        'parse_mode' => 'Markdown'
    ];
    if ($replyMarkup) {
        $data['reply_markup'] = $replyMarkup;
    }
    sendRequest('editMessageText', $data);
}


// --- MAIN LOGIC ---

// 1. Get Input
$content = file_get_contents("php://input");
$update = json_decode($content, true);

// If there's no valid update, there's nothing to do.
if (!$update) {
    exit();
}

// 2. Handle Callback Queries (button presses)
if (isset($update["callback_query"])) {
    $callbackQuery = $update["callback_query"];
    $chatId = (string)$callbackQuery["message"]["chat"]["id"];
    $messageId = (int)$callbackQuery["message"]["message_id"];
    $callbackQueryId = (string)$callbackQuery["id"];
    $userId = (string)$callbackQuery["from"]["id"];
    $userName = $callbackQuery["from"]["first_name"];
    $data = $callbackQuery["data"];

    $parts = explode(':', $data, 2);
    $action = $parts[0];
    $gameId = (int)($parts[1] ?? 0);

    if ($action === 'join_game' && $gameId > 0) {
        $success = add_player_to_game($gameId, $userId, $userName);

        if ($success) {
            answerCallbackQuery($callbackQueryId, "你已成功加入游戏！");
            $playerCount = get_player_count($gameId);

            if ($playerCount >= 3) {
                editMessageText($chatId, $messageId, "游戏满员，对局开始！", null);
                // Game starting logic
                $game = load_game_state($gameId);
                if ($game) {
                    $firstBidderId = $game->getState()['current_turn'];
                    sendMessage($chatId, "发牌完毕！请玩家 `$firstBidderId` 开始叫地主。 (/bid [1,2,3] 或 /pass)");
                }
            } else {
                // Update the "Join Game" button with the new player count.
                $newText = "新游戏已创建 (ID: $gameId)! 等待玩家加入...";
                $newMarkup = ['inline_keyboard' => [[['text' => "加入游戏 ($playerCount/3)", 'callback_data' => 'join_game:' . $gameId]]]];
                editMessageText($chatId, $messageId, $newText, $newMarkup);
            }
        } else {
            answerCallbackQuery($callbackQueryId, "加入游戏失败！可能是游戏已满或您已加入。");
        }
    } elseif ($action === 'check_points') {
        answerCallbackQuery($callbackQueryId, "您的积分查询功能待完善。");
    }
    // We are done after handling a callback query.
    exit();
}

// 3. Handle Text Messages
if (isset($update["message"])) {
    $chatId = (string)$update["message"]["chat"]["id"];
    $userId = (string)$update["message"]["from"]["id"];
    $userName = $update["message"]["from"]["first_name"];
    $text = trim($update["message"]["text"] ?? '');
    $reply = '';
    $replyMarkup = null;

    // Command parsing
    $parts = explode(' ', $text, 2);
    $command = $parts[0];
    $args = $parts[1] ?? '';

    $activeGameId = get_active_game_id_for_chat($chatId);

    // --- Command Handling ---

    switch ($command) {
        case '/start':
            $reply = "欢迎来到斗地主云端游戏机器人！\n使用 /newgame 创建新对局。";
            $replyMarkup = ['keyboard' => [[['text' => '游戏规则']], [['text' => '联系客服']]], 'resize_keyboard' => true];
            break;

        case '/newgame':
            if ($activeGameId) {
                $reply = "此聊天中已有一个正在进行的游戏 (ID: $activeGameId)。";
            } else {
                $newGameId = create_game_and_room($chatId, $userId, $userName);
                if ($newGameId) {
                    $reply = "新游戏已创建 (ID: $newGameId)! 等待玩家加入...";
                    $replyMarkup = ['inline_keyboard' => [[['text' => '加入游戏 (1/3)', 'callback_data' => 'join_game:' . $newGameId]]]];
                } else {
                    $reply = "创建游戏失败，请稍后再试。";
                }
            }
            break;

        // --- In-Game Commands ---
        case '/bid':
        case '/play':
        case '/pass':
        case '/hand':
        case '/status':
            if (!$activeGameId) {
                $reply = "没有正在进行的游戏。请使用 /newgame 创建新对局。";
                break;
            }
            $game = load_game_state($activeGameId);
            if (!$game) {
                $reply = "错误：找不到当前游戏 (ID: $activeGameId)。";
                break;
            }

            $gameState = $game->getState();
            if ($gameState['state'] === 'finished') {
                $reply = "本局游戏已结束。";
                break;
            }

            if ($gameState['current_turn'] !== $userId) {
                $reply = "还没轮到你。当前轮到玩家 `{$gameState['current_turn']}`。";
                break;
            }

            $success = false;
            // --- Sub-switch for game actions ---
            switch ($command) {
                case '/bid':
                    if ($gameState['state'] !== 'bidding') { $reply = "现在不是叫地主阶段。"; break; }
                    $bidValue = (int)$args;
                    if (!in_array($bidValue, [1, 2, 3])) { $reply = "无效的出价。请出价 1, 2, 或 3。 (例如: /bid 1)"; break; }
                    $success = $game->processBid($userId, $bidValue);
                    if($success) {
                        $reply = "玩家 `$userId` 出价 **$bidValue**。";
                    } else {
                        $reply = "出价无效 (可能太低或者不是你的回合)。";
                    }
                    break;

                case '/pass':
                    if ($gameState['state'] === 'bidding') {
                        $success = $game->processBid($userId, 0); // 0 means pass
                        if ($success) $reply = "玩家 `$userId` 选择不叫。";
                    } else { // Playing phase
                        $success = $game->passTurn($userId);
                        if ($success) $reply = "玩家 `$userId` 选择 pass。";
                    }
                    if (!$success) $reply = "现在不能 pass。";
                    break;

                case '/play':
                    if ($gameState['state'] !== 'playing') { $reply = "现在不是出牌阶段。"; break; }
                    $cards = explode(' ', strtoupper($args));
                    $success = $game->playCards($userId, $cards);
                    if ($success) {
                        $playedCardsStr = implode(' ', $cards);
                        $reply = "玩家 `$userId` 出牌: `$playedCardsStr`";
                    } else {
                        $reply = "出牌无效！请检查你的牌或规则。";
                    }
                    break;

                case '/hand':
                    $player = $game->getPlayer($userId);
                    if ($player) {
                        $hand = $player->getHand();
                        $handStr = implode(' ', $hand);
                        sendMessage($userId, "你的手牌: `$handStr`"); // Private message
                        $reply = "你的手牌已私信发送。";
                    }
                    break;

                case '/status':
                    $turnId = $gameState['current_turn'];
                    $lastPlayStr = !empty($gameState['last_played_cards']) ? implode(' ', $gameState['last_played_cards']) : '无';
                    $reply = "轮到玩家 `$turnId` 操作。\n上一手牌: `$lastPlayStr`";
                    break;
            }

            if ($success) {
                save_game_state($game);
                $newState = $game->getState();
                // Append next player info to the reply
                if ($newState['state'] === 'bidding') {
                    $reply .= "\n\n轮到玩家 `{$newState['current_turn']}` 叫地主。 (/bid 1, 2, 3 或 /pass)";
                } elseif ($newState['state'] === 'playing') {
                    if($gameState['state'] === 'bidding') { // Bidding just ended
                        $landlordId = $newState['landlord_player_id'];
                        $bottomCardsStr = implode(' ', $game->getFullStateForDb()['landlordsCards']); // get original cards before they were added
                        $reply .= "\n\n叫地主结束！地主是玩家 `$landlordId`。";
                        $reply .= "\n底牌是: `$bottomCardsStr` (已加入地主手牌)";
                    }
                    $reply .= "\n\n轮到玩家 `{$newState['current_turn']}` 出牌。";
                } elseif ($newState['state'] === 'finished') {
                    $winnerId = $game->getFullStateForDb()['lastPlayerId'];
                    $landlordId = $newState['landlord_player_id'];
                    if ($winnerId === $landlordId) {
                        $reply .= "\n\n**游戏结束！地主 `$winnerId` 获胜！**";
                    } else {
                        $reply .= "\n\n**游戏结束！农民团队获胜！** (玩家 `$winnerId` 首先出完牌)";
                    }
                } elseif ($newState['state'] === 'misdeal') {
                     $reply .= "\n\n所有玩家都不叫，本局流局。";
                }
            }
            break;

        default:
            // Ignore unknown commands
            break;
    }

    if ($reply) {
        sendMessage($chatId, $reply, $replyMarkup);
    }
}

// Close the connection if it was opened.
$conn = get_db();
if ($conn && !$conn->connect_errno) {
    $conn->close();
}
?>
