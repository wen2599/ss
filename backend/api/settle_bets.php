<?php
// backend/api/settle_bets.php
require_once 'config.php';
require_once 'db_connect.php';

// This script should be run as a cron job after each draw.

$conn = db_connect();
if (!$conn) {
    error_log("Settle bets failed: Database connection failed.");
    exit;
}

try {
    // Get the latest draws for all lottery types
    $sql = "
        SELECT d.period, d.winning_numbers, d.lottery_type
        FROM draws d
        INNER JOIN (
            SELECT lottery_type, MAX(draw_time) AS max_draw_time
            FROM draws
            GROUP BY lottery_type
        ) AS latest_draws ON d.lottery_type = latest_draws.lottery_type AND d.draw_time = latest_draws.max_draw_time
    ";
    $result = $conn->query($sql);
    $latest_draws = [];
    while($row = $result->fetch_assoc()) {
        $latest_draws[$row['lottery_type']] = $row;
    }

    // Get all unsettled bets
    $sql = "SELECT id, user_id, numbers, lottery_type, period FROM bets WHERE settled = 0";
    $unsettled_bets = $conn->query($sql);

    while($bet = $unsettled_bets->fetch_assoc()) {
        $lottery_type = $bet['lottery_type'];
        if (isset($latest_draws[$lottery_type])) {
            $draw = $latest_draws[$lottery_type];
            if ($bet['period'] === $draw['period']) {
                $winning_numbers = explode(',', $draw['winning_numbers']);
                $bet_numbers = explode(',', $bet['numbers']);
                $matched_numbers = array_intersect($bet_numbers, $winning_numbers);
                // TODO: Implement the actual winning rules.
                // The current logic is a simple placeholder that gives 10 points for each matched number.
                // A real lottery would have more complex rules with different payouts for different numbers of matches.
                $winnings = count($matched_numbers) * PAYOUT_MULTIPLIER; // Simple placeholder logic

                if ($winnings > 0) {
                    // Update user's points
                    $stmt = $conn->prepare("UPDATE users SET points = points + ? WHERE id = ?");
                    $stmt->bind_param("di", $winnings, $bet['user_id']);
                    $stmt->execute();
                }

                // Mark the bet as settled
                $stmt = $conn->prepare("UPDATE bets SET settled = 1, winnings = ? WHERE id = ?");
                $stmt->bind_param("di", $winnings, $bet['id']);
                $stmt->execute();
            }
        }
    }

    echo "Bets settled successfully.";

} catch (Exception $e) {
    error_log("Settle bets failed: " . $e->getMessage());
}

$conn->close();
?>
