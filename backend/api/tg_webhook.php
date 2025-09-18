<?php
// ============== Unified Telegram Webhook Script ==============
// This single file contains all necessary functions to run the bot.
// It is designed to be deployed as a single artifact to avoid `require_once` path issues.

// --- Core Configuration & Setup ---

// 1. Environment Variable Loader (from env_loader.php)
function loadEnv($path) {
    if (!file_exists($path)) {
        http_response_code(500);
        error_log("FATAL: .env file not found at " . $path);
        exit("FATAL: .env file not found.");
    }
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        list($name, $value) = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value);
        if (!array_key_exists($name, $_SERVER) && !array_key_exists($name, $_ENV)) {
            putenv(sprintf('%s=%s', $name, $value));
            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
        }
    }
}
// Load the .env file from the same directory as this script
loadEnv(__DIR__ . '/../.env');


// 2. Application Configuration (from config.php)
define('DB_HOST', getenv('DB_HOST') ?: '127.0.0.1');
define('DB_PORT', getenv('DB_PORT') ?: '3306');
define('DB_DATABASE', getenv('DB_DATABASE') ?: null);
define('DB_USERNAME', getenv('DB_USERNAME') ?: null);
define('DB_PASSWORD', getenv('DB_PASSWORD') ?: null);
define('TELEGRAM_BOT_TOKEN', getenv('TELEGRAM_BOT_TOKEN') ?: null);
define('TELEGRAM_CHAT_ID', getenv('TELEGRAM_CHAT_ID') ?: null);
define('TELEGRAM_SUPER_ADMIN_ID', 1878794912); // System constant

// 3. Error Logger (from error_logger.php)
function log_error($message) {
    $log_file = __DIR__ . '/../logs/error.log';
    $log_dir = dirname($log_file);
    if (!is_dir($log_dir)) {
        mkdir($log_dir, 0755, true);
    }
    $timestamp = date("Y-m-d H:i:s");
    file_put_contents($log_file, "[$timestamp] " . $message . "\n", FILE_APPEND);
}

// Set global error/exception handlers
set_error_handler(function($severity, $message, $file, $line) {
    if (!(error_reporting() & $severity)) { return; }
    log_error("Error: [$severity] $message in $file on line $line");
    return true; // Don't execute PHP internal error handler
});
set_exception_handler(function($exception) {
    log_error("Exception: " . $exception->getMessage() . " in " . $exception->getFile() . " on line " . $exception->getLine());
});


// --- Database ---

// 4. Database Connection (from database.php)
function getDbConnection() {
    $dsn = "mysql:host=".DB_HOST.";port=".DB_PORT.";dbname=".DB_DATABASE.";charset=utf8mb4";
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];
    try {
        return new PDO($dsn, DB_USERNAME, DB_PASSWORD, $options);
    } catch (\PDOException $e) {
        log_error('Database Connection Error: ' . $e->getMessage());
        // Do not echo errors back to Telegram
        exit();
    }
}


// --- Core Bot Logic ---

// 5. Bet Parser (from parser.php)
function parseBets(string $inputText, $pdo): array {
    $stmt = $pdo->query("SELECT rule_key, rule_value FROM lottery_rules");
    $rules_raw = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $rules = [];
    foreach ($rules_raw as $row) {
        $rules[$row['rule_key']] = json_decode($row['rule_value'], true);
    }
    $zodiac_mappings = $rules['zodiac_mappings'] ?? [];
    $color_mappings = $rules['color_mappings'] ?? [];

    $bets = [];
    $remainingText = trim($inputText);
    $patterns = [
        'special' => '/^特(\d+)[\sx*](\d+)/u',
        'zodiac' => '/^((?:[\p{Han}](?!波))+?)各数(\d+)/u',
        'color' => '/^([\p{Han}]+波)各(\d+)/u',
    ];

    while (strlen($remainingText) > 0) {
        $matchFound = false;
        foreach ($patterns as $type => $pattern) {
            if (preg_match($pattern, $remainingText, $matches)) {
                $matchFound = true;
                $fullMatch = $matches[0];
                switch ($type) {
                    case 'special':
                        $bets[] = ['type' => 'special', 'number' => $matches[1], 'amount' => (int)$matches[2], 'display_name' => '特码'];
                        break;
                    case 'zodiac':
                        $items_str = $matches[1];
                        $amount = (int)$matches[2];
                        $items = preg_split('//u', $items_str, -1, PREG_SPLIT_NO_EMPTY);
                        foreach ($items as $item) {
                            if (isset($zodiac_mappings[$item])) {
                                $bets[] = ['type' => 'zodiac', 'name' => $item, 'numbers' => $zodiac_mappings[$item], 'amount' => $amount, 'display_name' => '生肖'];
                            }
                        }
                        break;
                    case 'color':
                        $item = $matches[1];
                        $amount = (int)$matches[2];
                        if (isset($color_mappings[$item])) {
                            $bets[] = ['type' => 'color', 'name' => $item, 'numbers' => $color_mappings[$item], 'amount' => $amount, 'display_name' => '波色'];
                        }
                        break;
                }
                $remainingText = ltrim(substr($remainingText, strlen($fullMatch)));
                break;
            }
        }
        if (!$matchFound) {
            log_error("Unparseable text remaining: " . $remainingText);
            break;
        }
    }
    return $bets;
}

// 6. Bet Settlement Logic (from settle_bets.php)
function settleBetsForIssue($pdo, $issue_number) {
    try {
        // First, get the winning numbers for this issue
        $stmt = $pdo->prepare("SELECT numbers, special_number FROM lottery_draws WHERE issue_number = ?");
        $stmt->execute([$issue_number]);
        $draw = $stmt->fetch();

        if (!$draw) {
            return "结算失败：找不到期号为 `{$issue_number}` 的开奖结果。";
        }

        $winning_numbers = json_decode($draw['numbers'], true);
        $special_number = $draw['special_number'];

        // Get odds
        $stmt = $pdo->query("SELECT rule_value FROM lottery_rules WHERE rule_key = 'odds'");
        $odds_data = json_decode($stmt->fetchColumn(), true);
        $odds_special = $odds_data['special'] ?? 47;
        $odds_default = $odds_data['default'] ?? 45; // A default for zodiac/color if not specified

        // Get all pending bets for this issue
        $stmt = $pdo->prepare("SELECT * FROM bets WHERE issue_number = ? AND status = 'pending'");
        $stmt->execute([$issue_number]);
        $pending_bets = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($pending_bets)) {
            return "无需结算：期号 `{$issue_number}` 无待处理的投注。";
        }

        $pdo->beginTransaction();

        $update_stmt = $pdo->prepare("UPDATE bets SET status = 'settled', settlement_data = ? WHERE id = ?");
        $settled_count = 0;
        $total_payout_all_bets = 0;

        foreach ($pending_bets as $bet_row) {
            $parsed_bets = json_decode($bet_row['parsed_data'], true);
            $bet_total_payout = 0;
            $winning_details = [];

            foreach ($parsed_bets as $individual_bet) {
                $is_win = false;
                $payout = 0;
                $bet_amount = $individual_bet['amount'];

                switch ($individual_bet['type']) {
                    case 'special':
                        if ($individual_bet['number'] == $special_number) {
                            $is_win = true;
                            $payout = $bet_amount * $odds_special;
                        }
                        break;
                    case 'zodiac':
                    case 'color':
                        $common_numbers = array_intersect($individual_bet['numbers'], $winning_numbers);
                        if (!empty($common_numbers)) {
                            $is_win = true;
                            $payout = $bet_amount * $odds_default;
                        }
                        break;
                }

                if ($is_win) {
                    $bet_total_payout += $payout;
                    $winning_details[] = ['bet' => $individual_bet, 'payout' => $payout, 'is_win' => true];
                }
            }

            // Only update if there was a payout
            if ($bet_total_payout > 0) {
                 $total_payout_all_bets += $bet_total_payout;
            }

            $settlement_data_to_save = json_encode([
                'total_payout' => $bet_total_payout,
                'details' => $winning_details,
                'settled_at' => date('Y-m-d H:i:s'),
                'winning_numbers' => $winning_numbers,
                'special_number' => $special_number
            ]);

            $update_stmt->execute([$settlement_data_to_save, $bet_row['id']]);
            $settled_count++;
        }

        $pdo->commit();

        return "结算完成！\n期号: `$issue_number`\n- 处理了 `{$settled_count}` 条投注。\n- 总赔付金额: `{$total_payout_all_bets}`。";

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        log_error("Error during settlement for issue {$issue_number}: " . $e->getMessage());
        return "结算时发生严重错误。请检查日志。";
    }
}


// 7. Telegram API Communication
function sendMessage($chatId, $text, $keyboard = null) {
    $url = 'https://api.telegram.org/bot' . TELEGRAM_BOT_TOKEN . '/sendMessage';
    $post_fields = [
        'chat_id' => $chatId,
        'text' => $text,
        'parse_mode' => 'Markdown',
    ];
    if ($keyboard) {
        $post_fields['reply_markup'] = json_encode($keyboard);
    }
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_HTTPHEADER, array("Content-Type: application/json"));
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($post_fields));
    $output = curl_exec($ch);
    curl_close($ch);
    return $output;
}

function answerCallbackQuery($callbackQueryId, $text = null) {
    $url = 'https://api.telegram.org/bot' . TELEGRAM_BOT_TOKEN . '/answerCallbackQuery';
    $post_fields = ['callback_query_id' => $callbackQueryId];
    if ($text) {
        $post_fields['text'] = $text;
        $post_fields['show_alert'] = true;
    }
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_HTTPHEADER, array("Content-Type: application/json"));
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($post_fields));
    curl_exec($ch);
    curl_close($ch);
}

// --- Main Webhook Execution ---

$update = json_decode(file_get_contents('php://input'), true);

if (!$update) {
    exit();
}

// Initialize PDO connection
$pdo = getDbConnection();

// Handle callback queries (from inline keyboards)
if (isset($update['callback_query'])) {
    $callbackQuery = $update['callback_query'];
    $callbackId = $callbackQuery['id'];
    $chatId = $callbackQuery['message']['chat']['id'];
    $data = $callbackQuery['data'];

    if ($chatId != TELEGRAM_SUPER_ADMIN_ID) {
        answerCallbackQuery($callbackId, "您无权操作。");
        exit();
    }

    $message = "这是一个回调功能";
    switch($data) {
        case 'parse_latest':
            $message = "您点击了“解析最新投注”按钮。请直接发送需要解析的投注文本。";
            break;
        case 'settle_bets':
            $message = "请回复 `/settle [期号]` 来结算指定期数的投注。\n例如: `/settle 2025101`";
            break;
        case 'manual_draw':
            $message = "请回复 `/draw [名称] [号码]` 来手动录入开奖结果。\n例如: `/draw 新澳门六合彩 1,2,3,4,5,6,7`";
            break;
    }
    answerCallbackQuery($callbackId); // Acknowledge the tap
    sendMessage($chatId, $message); // Send a follow-up message
    exit();
}

// Standard message handling
$message = $update['message'] ?? null;
$chatId = $message['chat']['id'] ?? null;
$text = $message['text'] ?? '';
$userId = $message['from']['id'] ?? null;

// Only respond to the super admin
if ($userId != TELEGRAM_SUPER_ADMIN_ID) {
    sendMessage($chatId, "抱歉，您无权使用此机器人。");
    exit();
}

if (strpos($text, '/start') === 0) {
    $keyboard = [
        'inline_keyboard' => [
            [
                ['text' => '解析最新投注', 'callback_data' => 'parse_latest'],
                ['text' => '结算投注', 'callback_data' => 'settle_bets']
            ],
            [
                ['text' => '手动开奖', 'callback_data' => 'manual_draw']
            ]
        ]
    ];
    sendMessage($chatId, "欢迎使用投注管理机器人。请选择一个操作：", $keyboard);

} elseif (strpos($text, '/parse') === 0) {
    $parts = explode(' ', $text, 3);
    if (count($parts) < 3) {
        sendMessage($chatId, "格式错误。用法: `/parse [期号] [投注内容]`");
        exit();
    }
    $issueNumber = $parts[1];
    $betContent = $parts[2];

    $parsedBets = parseBets($betContent, $pdo);

    if (empty($parsedBets)) {
        sendMessage($chatId, "解析失败：无法从您的文本中识别任何投注。");
        exit();
    }

    // Save to database
    $stmt = $pdo->prepare("INSERT INTO bets (issue_number, original_content, parsed_data, status) VALUES (?, ?, ?, 'pending')");
    $stmt->execute([$issueNumber, $betContent, json_encode($parsedBets)]);

    sendMessage($chatId, "投注解析成功并已保存！\n期号: `$issueNumber`\n共解析出 `" . count($parsedBets) . "` 条投注。");

} elseif (strpos($text, '/settle') === 0) {
    $parts = explode(' ', $text, 2);
    if (count($parts) < 2 || !is_numeric($parts[1])) {
        sendMessage($chatId, "格式错误。用法: `/settle [期号]`");
        exit();
    }
    $issueNumber = trim($parts[1]);

    // Show a "processing" message
    sendMessage($chatId, "正在结算期号 `{$issueNumber}`，请稍候...");

    $result_message = settleBetsForIssue($pdo, $issueNumber);
    sendMessage($chatId, $result_message);

} elseif (strpos($text, '/draw') === 0) {
    $parts = explode(' ', $text, 3);
    if (count($parts) < 3) {
        sendMessage($chatId, "格式错误。用法: `/draw [开奖名称] [号码,用逗号隔开]`\n例如: `/draw 新澳门六合彩 1,2,3,4,5,6,7`");
        exit();
    }
    $lotteryName = trim($parts[1]);
    $numbersStr = trim($parts[2]);
    $numbers = array_map('trim', explode(',', $numbersStr));

    if (count($numbers) < 7) {
        sendMessage($chatId, "号码错误：必须提供至少7个号码。");
        exit();
    }

    // The last number is the special number
    $special_number = array_pop($numbers);
    // The rest are the main numbers
    $main_numbers = $numbers;

    // Use the current date as the issue number
    $issueNumber = date('Ymd');

    try {
        $stmt = $pdo->prepare("INSERT INTO lottery_draws (issue_number, lottery_name, numbers, special_number, draw_time) VALUES (?, ?, ?, ?, NOW())");
        $stmt->execute([$issueNumber, $lotteryName, json_encode($main_numbers), $special_number]);

        sendMessage($chatId, "开奖结果已手动保存！\n期号: `$issueNumber`\n名称: `$lotteryName`\n号码: `" . implode(', ', $main_numbers) . "`\n特别号: `$special_number`");

        // Automatically trigger settlement for this new draw
        $settle_message = settleBetsForIssue($pdo, $issueNumber);
        sendMessage($chatId, $settle_message);

    } catch (Exception $e) {
        log_error("Manual draw error: " . $e->getMessage());
        sendMessage($chatId, "保存开奖结果时发生错误。");
    }

} else {
    // Default behavior for any other text: assume it's a bet to be parsed
    // For simplicity, we require an issue number to be set somehow.
    // Let's assume for now it must be on the first line.
    $lines = explode("\n", $text, 2);
    $issueNumber = trim($lines[0]);
    $betContent = $lines[1] ?? '';

    if (!is_numeric($issueNumber) || empty($betContent)) {
        sendMessage($chatId, "自动解析失败。请确保第一行为期号，后面为投注内容。或使用 /start 命令。");
        exit();
    }

    $parsedBets = parseBets($betContent, $pdo);

    if (empty($parsedBets)) {
        sendMessage($chatId, "解析失败：无法从您的文本中识别任何投注。");
        exit();
    }

    // Save to database
    $stmt = $pdo->prepare("INSERT INTO bets (issue_number, original_content, parsed_data, status) VALUES (?, ?, ?, 'pending')");
    $stmt->execute([$issueNumber, $betContent, json_encode($parsedBets)]);

    sendMessage($chatId, "投注自动解析成功并已保存！\n期号: `$issueNumber`\n共解析出 `" . count($parsedBets) . "` 条投注。");
}

?>
