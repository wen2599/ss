<?php
// backend/api/tg_webhook.php
// This webhook receives updates from Telegram.
// It's designed to parse winning number announcements from a specific channel.

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/config.php';

// --- Helper Functions ---
function parse_lottery_result($text) {
    $lottery_data = [
        'lottery_name' => null,
        'issue_number' => null,
        'winning_numbers' => null,
    ];

    // Regex for the different lottery formats
    $patterns = [
        '新澳门六合彩' => '/新澳门六合彩第:(\d+)期开奖结果:\s*([\d\s]+)\s*([\p{Han}\s]+)\s*([🔴🟢🔵\s]+)/u',
        '香港六合彩' => '/香港六合彩第:(\d+)期开奖结果:\s*([\d\s]+)\s*([\p{Han}\s]+)\s*([🔴🟢🔵\s]+)/u',
        '老澳21.30' => '/老澳21\.30第:(\d+)\s*期开奖结果:\s*([\d\s]+)\s*([\p{Han}\s]+)\s*([🔴🟢🔵\s]+)/u',
    ];

    foreach ($patterns as $name => $pattern) {
        if (preg_match($pattern, $text, $matches)) {
            $lottery_data['lottery_name'] = $name;
            $lottery_data['issue_number'] = $matches[1];

            $numbers = preg_split('/\s+/', trim($matches[2]), -1, PREG_SPLIT_NO_EMPTY);
            $zodiacs = preg_split('/\s+/', trim($matches[3]), -1, PREG_SPLIT_NO_EMPTY);

            // Convert emoji to text
            $color_emojis = preg_split('/\s+/', trim($matches[4]), -1, PREG_SPLIT_NO_EMPTY);
            $color_map = ['🔴' => 'red', '🟢' => 'green', '🔵' => 'blue'];
            $colors = array_map(fn($emoji) => $color_map[$emoji] ?? 'unknown', $color_emojis);

            if (count($numbers) === 7 && count($zodiacs) === 7 && count($colors) === 7) {
                $lottery_data['winning_numbers'] = [
                    'numbers' => array_map('intval', $numbers),
                    'zodiacs' => $zodiacs,
                    'colors' => $colors,
                    'special_number' => (int)end($numbers),
                ];
            }
            break; // Found a match, no need to check other patterns
        }
    }

    return $lottery_data;
}


// --- Main Webhook Logic ---
$update_json = file_get_contents('php://input');
$update = json_decode($update_json, true);

if (!$update) {
    exit(); // Not a valid update
}

// We are only interested in messages from our specific channel
if (isset($update['message']['chat']['id']) && $update['message']['chat']['id'] == TELEGRAM_CHANNEL_ID) {
    $message_text = $update['message']['text'] ?? '';

    $result = parse_lottery_result($message_text);

    if ($result['lottery_name'] && $result['issue_number'] && $result['winning_numbers']) {
        try {
            $pdo = getDbConnection();
            $sql = "INSERT INTO lottery_draws (lottery_name, issue_number, winning_numbers) VALUES (:lottery_name, :issue_number, :winning_numbers)
                    ON DUPLICATE KEY UPDATE winning_numbers = VALUES(winning_numbers)";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':lottery_name' => $result['lottery_name'],
                ':issue_number' => $result['issue_number'],
                ':winning_numbers' => json_encode($result['winning_numbers']),
            ]);

            // Now that the draw is saved, trigger the settlement script
            // Using include is simple and effective for this architecture.
            // Pass the context to the settlement script.
            $settlement_context = [
                'pdo' => $pdo,
                'lottery_name' => $result['lottery_name'],
                'issue_number' => $result['issue_number'],
                'winning_numbers' => $result['winning_numbers'],
            ];

            // We will create this file in the next step
            // include __DIR__ . '/settle_bets.php';

            // Optional: Log success to a file for debugging
            file_put_contents('tg_webhook.log', "Successfully processed issue {$result['issue_number']}\n", FILE_APPEND);

        } catch (Exception $e) {
            // Log any errors
            file_put_contents('tg_webhook_error.log', "Error: " . $e->getMessage() . "\n", FILE_APPEND);
        }
    }
}

// Note: The original bot commands for /listusers etc. have been removed
// as the primary purpose of this webhook is now to ingest lottery results.
// They can be added back in if needed.

// Respond to Telegram to acknowledge receipt
http_response_code(200);
echo json_encode(['status' => 'ok']);
?>
