<?php
// backend/check_lottery_data.php
// This script checks for winning lottery tickets and notifies users.
// It should be run periodically via a cron job, e.g., after the lottery draw.

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/telegram_helpers.php';
require_once __DIR__ . '/db_operations.php';

// Load Telegram Admin ID and Lottery Channel ID from environment variables
if (!defined('TELEGRAM_ADMIN_ID') && isset($_ENV['TELEGRAM_ADMIN_ID'])) {
    define('TELEGRAM_ADMIN_ID', $_ENV['TELEGRAM_ADMIN_ID']);
}
if (!defined('LOTTERY_CHANNEL_ID') && isset($_ENV['LOTTERY_CHANNEL_ID'])) {
    define('LOTTERY_CHANNEL_ID', $_ENV['LOTTERY_CHANNEL_ID']);
}

// --- Main Logic ---
function checkLotteryTickets(PDO $pdo)
{
    echo "Starting lottery check...\n";

    // 1. Get the latest lottery draw result. We'll fetch the most recent one.
    $latestResult = fetchOne($pdo, "SELECT draw_date, winning_numbers FROM lottery_results ORDER BY draw_date DESC LIMIT 1");

    if (!$latestResult) {
        echo "No lottery results found in the database. Exiting.\n";
        if (defined('TELEGRAM_ADMIN_ID')) {
            sendTelegramMessage(TELEGRAM_ADMIN_ID, "[CRON ERROR] No lottery results found in database for checking.");
        }
        return;
    }

    $drawDate = $latestResult['draw_date'];
    $winningNumbers = explode(',', $latestResult['winning_numbers']);
    echo "Latest winning numbers (Date: {$drawDate}): " . implode(', ', $winningNumbers) . "\n";

    // 2. Get all lottery bills that have been processed by AI but not yet checked for this draw date.
    // We'll use a new status 'lottery_checked' to mark bills that have been processed by this script.
    $lotteryBills = fetchAll($pdo,
        "SELECT b.id, b.user_id, b.lottery_numbers, u.telegram_chat_id, b.subject \n         FROM bills b\n         JOIN users u ON b.user_id = u.id\n         WHERE b.is_lottery = 1 AND b.status = 'processed'", 
        []
    );

    if (empty($lotteryBills)) {
        echo "No new lottery tickets to check. Exiting.\n";
        return;
    }

    echo "Found " . count($lotteryBills) . " lottery tickets to check.\n";

    // 3. Compare each ticket with the winning numbers.
    foreach ($lotteryBills as $bill) {
        // Ensure the bill has lottery numbers and the user has a linked Telegram chat ID
        if (empty($bill['lottery_numbers']) || empty($bill['telegram_chat_id'])) {
            // Mark as completed if no numbers or chat ID, to avoid re-processing invalid entries.
            update($pdo, 'bills', ['status' => 'completed'], 'id = :id', [':id' => $bill['id']]);
            continue;
        }

        $userNumbers = explode(',', $bill['lottery_numbers']);
        $matches = array_intersect($userNumbers, $winningNumbers);

        if (count($matches) > 0) {
            echo "Found a winner! User ID: {$bill['user_id']} (Bill ID: {$bill['id']})\n";
            // 4. Notify the user via Telegram.
            $winnerText = "🎉 <b>恭喜！您的彩票中奖了！</b> 🎉\n\n"
                        . "<b>开奖日期:</b> {$drawDate}\n"
                        . "<b>中奖号码:</b> " . implode(', ', $winningNumbers) . "\n"
                        . "<b>您的号码:</b> " . implode(', ', $userNumbers) . "\n"
                        . "<b>匹配号码:</b> " . implode(', ', $matches) . "\n\n"
                        . "您的账单主题: " . htmlspecialchars($bill['subject']);
            
            sendTelegramMessage($bill['telegram_chat_id'], $winnerText);

            // Optionally, also send to a public lottery channel if configured
            if (defined('LOTTERY_CHANNEL_ID') && !empty(LOTTERY_CHANNEL_ID)) {
                sendTelegramMessage(LOTTERY_CHANNEL_ID, "🥳 有人中奖了！请查看私聊通知！");
            }
            
            // Update the bill status to 'completed' to avoid re-checking.
            update($pdo, 'bills', ['status' => 'completed'], 'id = :id', [\':id\' => $bill['id']]);
        } else {
             // If not a winner, mark as 'completed' as well to avoid re-checking.
             update($pdo, 'bills', ['status' => 'completed'], 'id = :id', [\':id\' => $bill['id']]);
        }
    }
    echo "Lottery check finished.\n\";
}

// --- Execution ---
// Allow running from the command line.
if (php_sapi_name() === \'cli\') {
    checkLotteryTickets($pdo);
} else {
    // If accessed via browser, protect it with ADMIN_SECRET.
    $adminSecret = $_GET[\'secret\'] ?? \'\';\n    if (!isset($_ENV[\'ADMIN_SECRET\']) || $adminSecret === $_ENV[\'ADMIN_SECRET\']) {
        header(\'Content-Type: text/plain\');
        checkLotteryTickets($pdo);
    } else {
        http_response_code(403);\n        die(\'Forbidden\');\n    }\n}\n