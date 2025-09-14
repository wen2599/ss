<?php
/**
 * Telegram Bot for Lottery Management
 *
 * This bot handles two primary functions:
 * 1. Automatically parsing winning numbers from a specified channel.
 * 2. Settling bets based on a command with a pasted chat log.
 *
 * This script should be run periodically (e.g., via a cron job).
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

// --- Main Execution ---

function run_bot() {
    error_log("Bot is running...");
    $bot_token = TELEGRAM_BOT_TOKEN;
    if (!$bot_token || $bot_token === 'YOUR_BOT_TOKEN') {
        error_log("Telegram bot token is not configured. Exiting.");
        return;
    }

    $db = get_db();
    $last_update_id = get_last_update_id($db);

    $updates = get_telegram_updates($bot_token, $last_update_id + 1);

    if (empty($updates)) {
        error_log("No new updates.");
        return;
    }

    foreach ($updates as $update) {
        process_update($update, $db, $bot_token);
        $last_update_id = $update['update_id'];
    }

    save_last_update_id($db, $last_update_id);
    error_log("Bot run finished. Last update ID: " . $last_update_id);
}

// --- Core Logic ---

function process_update($update, $db, $bot_token) {
    if (!isset($update['message']['text'])) {
        return; // Skip updates without a text message
    }

    $message = $update['message'];
    $chat_id = $message['chat']['id'];
    $text = $message['text'];

    // Check for winning number announcements
    $parsed_numbers = parse_winning_numbers($text);
    if ($parsed_numbers) {
        error_log("Parsed winning numbers for draw " . $parsed_numbers['draw_number'] . ". Updating database...");
        $result = update_draw_in_db($db, $parsed_numbers);
        if ($result) {
            send_telegram_message($bot_token, $chat_id, "✅ Draw " . $parsed_numbers['draw_number'] . " has been updated with winning numbers.");
        } else {
            send_telegram_message($bot_token, $chat_id, "⚠️ Could not update draw " . $parsed_numbers['draw_number'] . ". It might not exist in the database or is already settled.");
        }
        return;
    }

    // Check for settlement commands
    if (strpos($text, '/settle') === 0) {
        error_log("Received a /settle command from chat ID " . $chat_id);
        handle_settle_command($text, $db, $bot_token, $chat_id);
        return;
    }
}

function get_zodiac_map() {
    return [
        '蛇' => ['01', '13', '25', '37', '49'],
        '龍' => ['02', '14', '26', '38'],
        '兔' => ['03', '15', '27', '39'],
        '虎' => ['04', '16', '28', '40'],
        '牛' => ['05', '17', '29', '41'],
        '鼠' => ['06', '18', '30', '42'],
        '猪' => ['07', '19', '31', '43'],
        '狗' => ['08', '20', '32', '44'],
        '鸡' => ['09', '21', '33', '45'],
        '猴' => ['10', '22', '34', '46'],
        '羊' => ['11', '23', '35', '47'],
        '马' => ['12', '24', '36', '48'],
    ];
}

function parse_bets_from_chat_log($chat_log, $db) {
    $bets = [];
    $lines = explode("\n", $chat_log);
    $zodiac_map = get_zodiac_map();
    $all_zodiacs = implode('', array_keys($zodiac_map));

    foreach ($lines as $line) {
        // Assumed format: Username (1234): message
        if (!preg_match('/\((\d+)\):(.+)/', $line, $line_matches)) {
            continue;
        }
        $display_id = $line_matches[1];
        $message = trim($line_matches[2]);

        // Bet format: 鸡狗猴各数50
        if (!preg_match('/([' . $all_zodiacs . ']+)各数(\d+)/u', $message, $bet_matches)) {
            continue;
        }

        $zodiac_chars = preg_split('//u', $bet_matches[1], -1, PREG_SPLIT_NO_EMPTY);
        $amount_per_number = (int)$bet_matches[2];

        if (!isset($bets[$display_id])) {
            $bets[$display_id] = ['total_cost' => 0, 'bets' => []];
        }

        $numbers_to_bet = [];
        foreach ($zodiac_chars as $char) {
            if (isset($zodiac_map[$char])) {
                $numbers_to_bet = array_merge($numbers_to_bet, $zodiac_map[$char]);
            }
        }
        $numbers_to_bet = array_unique($numbers_to_bet);

        foreach ($numbers_to_bet as $number) {
            $bets[$display_id]['bets'][] = ['number' => $number, 'amount' => $amount_per_number];
            $bets[$display_id]['total_cost'] += $amount_per_number;
        }
    }
    return $bets;
}


function handle_settle_command($text, $db, $bot_token, $chat_id) {
    preg_match('/\/settle\s+(\d+)\s*([\s\S]*)/', $text, $matches);
    if (!$matches) {
        send_telegram_message($bot_token, $chat_id, "Invalid command format. Use: /settle <draw_id>\\n<chat log>");
        return;
    }

    $draw_number = $matches[1];
    $chat_log = trim($matches[2]);

    // 1. Fetch draw details
    $stmt = $db->prepare("SELECT * FROM lottery_draws WHERE draw_number = ?");
    $stmt->execute([$draw_number]);
    $draw = $stmt->fetch();

    if (!$draw) {
        send_telegram_message($bot_token, $chat_id, "Error: Draw #{$draw_number} not found.");
        return;
    }
    if ($draw['status'] !== 'closed') {
        send_telegram_message($bot_token, $chat_id, "Error: Draw #{$draw_number} is not closed. Current status: " . $draw['status']);
        return;
    }
    $winning_numbers = json_decode($draw['winning_numbers'], true);

    // 2. Parse bets from chat log
    $parsed_bets_by_user = parse_bets_from_chat_log($chat_log, $db);
    if (empty($parsed_bets_by_user)) {
        send_telegram_message($bot_token, $chat_id, "No valid bets found in the provided chat log for draw #{$draw_number}.");
        return;
    }

    $report = "Settlement Report for Draw #{$draw_number}\n";
    $report .= "Winning Numbers: " . implode(', ', $winning_numbers) . "\n\n";

    $db->beginTransaction();
    try {
        // 3. Process bets for each user
        foreach ($parsed_bets_by_user as $display_id => $data) {
            $total_cost = $data['total_cost'];
            $user_bets = $data['bets'];

            // Fetch user
            $stmt = $db->prepare("SELECT * FROM users WHERE display_id = ?");
            $stmt->execute([$display_id]);
            $user = $stmt->fetch();

            if (!$user) {
                $report .= "User {$display_id}: Not found. Bets ignored.\n";
                continue;
            }
            if ($user['points'] < $total_cost) {
                $report .= "User {$display_id}: Insufficient points. Has {$user['points']}, needs {$total_cost}. Bets ignored.\n";
                continue;
            }

            // Deduct total cost
            $stmt = $db->prepare("UPDATE users SET points = points - ? WHERE id = ?");
            $stmt->execute([$total_cost, $user['id']]);

            $total_winnings = 0;
            $payout_multiplier = PAYOUT_MULTIPLIER;

            foreach ($user_bets as $bet_info) {
                $bet_number = $bet_info['number'];
                $bet_amount = $bet_info['amount'];
                $winnings = 0;
                $status = 'lost';

                if (in_array($bet_number, $winning_numbers)) {
                    $winnings = $bet_amount * $payout_multiplier;
                    $total_winnings += $winnings;
                    $status = 'won';
                }

                // Insert into bets table
                $stmt = $db->prepare("INSERT INTO bets (user_id, draw_id, bet_type, bet_numbers, bet_amount, winnings, status) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$user['id'], $draw['id'], 'zodiac', json_encode([$bet_number]), $bet_amount, $winnings, $status]);
            }

            $report .= "User {$display_id}: Bet {$total_cost}, Won {$total_winnings}. ";
            if ($total_winnings > 0) {
                // Add winnings to user's points
                $stmt = $db->prepare("UPDATE users SET points = points + ? WHERE id = ?");
                $stmt->execute([$total_winnings, $user['id']]);
                 $report .= "Net: +" . ($total_winnings - $total_cost) . "\n";
            } else {
                 $report .= "Net: -{$total_cost}\n";
            }
        }

        // 4. Update draw status to settled
        $stmt = $db->prepare("UPDATE lottery_draws SET status = 'settled' WHERE id = ?");
        $stmt->execute([$draw['id']]);

        $db->commit();
        $report .= "\nSettlement complete.";

    } catch (Exception $e) {
        $db->rollBack();
        error_log("Settlement Error: " . $e->getMessage());
        $report = "A critical error occurred during settlement. The transaction has been rolled back. Details: " . $e->getMessage();
    }

    send_telegram_message($bot_token, $chat_id, $report);
}

/**
 * Parses the winning numbers from a message text based on predefined formats.
 *
 * @param string $text The message text from Telegram.
 * @return array|null An array with 'draw_number' and 'winning_numbers' or null if no match.
 */
function parse_winning_numbers($text) {
    $patterns = [
        // 新澳门六合彩
        '/新澳门六合彩第:(\d+)期开奖结果:\s*([\d\s]+)/',
        // 香港六合彩
        '/香港六合彩第:(\d+)期开奖结果:\s*([\d\s]+)/',
        // 老澳21.30
        '/老澳21\.30第:(\d+) *期开奖结果:\s*([\d\s]+)/'
    ];

    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $text, $matches)) {
            $draw_number = $matches[1];
            // The numbers are in the first line of the numbers block
            $numbers_line = trim(strtok($matches[2], "\n"));
            // Split by space and filter out any empty values
            $winning_numbers = array_filter(explode(' ', $numbers_line));

            // Ensure we have exactly 7 numbers
            if (count($winning_numbers) === 7) {
                return [
                    'draw_number' => $draw_number,
                    'winning_numbers' => array_values($winning_numbers) // Re-index array
                ];
            }
        }
    }

    return null;
}

/**
 * Updates a lottery draw in the database with the winning numbers.
 *
 * @param PDO $db The database connection object.
 * @param array $parsed_data The data from parse_winning_numbers.
 * @return bool True on success, false on failure.
 */
function update_draw_in_db($db, $parsed_data) {
    $draw_number = $parsed_data['draw_number'];
    $winning_numbers_json = json_encode($parsed_data['winning_numbers']);

    // We update the draw from 'open' to 'closed'. Settlement will move it to 'settled'.
    $stmt = $db->prepare(
        "UPDATE lottery_draws
         SET winning_numbers = ?, status = 'closed', updated_at = datetime('now','localtime')
         WHERE draw_number = ? AND status = 'open'"
    );

    $stmt->execute([$winning_numbers_json, $draw_number]);

    // rowCount() returns the number of affected rows.
    // If it's 1, the update was successful. If 0, no matching row was found (or it wasn't 'open').
    return $stmt->rowCount() > 0;
}


// --- Telegram API Helpers ---

function get_telegram_updates($token, $offset) {
    $url = "https://api.telegram.org/bot{$token}/getUpdates?offset={$offset}&timeout=10";
    $response_json = file_get_contents($url);
    if (!$response_json) {
        error_log("Failed to get updates from Telegram.");
        return [];
    }
    $response = json_decode($response_json, true);
    return $response['result'] ?? [];
}

function send_telegram_message($token, $chat_id, $text) {
    $url = "https://api.telegram.org/bot{$token}/sendMessage";
    $options = [
        'http' => [
            'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
            'method'  => 'POST',
            'content' => http_build_query(['chat_id' => $chat_id, 'text' => $text]),
        ],
    ];
    $context  = stream_context_create($options);
    $result = file_get_contents($url, false, $context);
    return $result !== false;
}


// --- Database Helpers ---

function get_last_update_id($db) {
    // We need a way to persist the last processed update_id.
    // The bot_settings table should be created by schema.sql.
    $stmt = $db->prepare("SELECT value FROM bot_settings WHERE key = 'last_update_id'");
    $stmt->execute();
    return (int)($stmt->fetchColumn() ?: 0);
}

function save_last_update_id($db, $update_id) {
    $stmt = $db->prepare("INSERT OR REPLACE INTO bot_settings (key, value) VALUES ('last_update_id', ?)");
    $stmt->execute([$update_id]);
}

// --- Entry Point ---
// To run this bot, this script would be executed by a cron job.
// For testing, you can call run_bot() directly or via a web request to a trigger script.
run_bot();
?>
