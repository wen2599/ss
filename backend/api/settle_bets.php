<?php
// backend/api/settle_bets.php
// This script is included by the webhook after a new draw is saved.
// It handles the entire settlement process for a given issue number.

// The script expects a $settlement_context variable to be defined, containing:
// ['pdo', 'issue_number', 'winning_numbers']
if (!isset($settlement_context)) {
    // Optional: Log an error if context is not set.
    file_put_contents('settlement_error.log', "Settlement script called without context.\n", FILE_APPEND);
    return;
}

$pdo = $settlement_context['pdo'];
$issue_number = $settlement_context['issue_number'];
$winning_numbers = $settlement_context['winning_numbers']['numbers']; // Array of 7 numbers
$special_number = $settlement_context['winning_numbers']['special_number'];

try {
    // 1. Fetch the odds from the database
    $stmt = $pdo->query("SELECT rule_value FROM lottery_rules WHERE rule_key = 'odds'");
    $odds_data = json_decode($stmt->fetchColumn(), true);
    $odds_special = $odds_data['special'] ?? 47;
    $odds_default = $odds_data['default'] ?? 45;

    // 2. Fetch all unsettled bets for this issue number
    $stmt = $pdo->prepare("SELECT * FROM bets WHERE issue_number = :issue_number AND status = 'unsettled'");
    $stmt->execute([':issue_number' => $issue_number]);
    $unsettled_bets = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Prepare statement for updating bets
    $update_stmt = $pdo->prepare(
        "UPDATE bets SET status = 'settled', settlement_data = :settlement_data WHERE id = :id"
    );

    // 3. Loop through each bet submission and settle it
    foreach ($unsettled_bets as $bet_row) {
        $bet_data = json_decode($bet_row['bet_data'], true);
        $total_payout = 0;
        $winning_details = [];

        // 4. Loop through each individual bet line within the submission
        foreach ($bet_data as $individual_bet) {
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
                    // Check for any intersection between the bet's numbers and the winning numbers
                    $common_numbers = array_intersect($individual_bet['numbers'], $winning_numbers);
                    if (!empty($common_numbers)) {
                        // For simplicity in V1, we assume any match is a win for the full amount.
                        // A more complex system might pay per matched number.
                        $is_win = true;
                        $payout = $bet_amount * $odds_default;
                    }
                    break;
            }

            if ($is_win) {
                $total_payout += $payout;
                $winning_details[] = [
                    'bet' => $individual_bet,
                    'payout' => $payout,
                    'is_win' => true
                ];
            }
        }

        // 5. Update the bet record in the database
        $settlement_data_to_save = [
            'total_payout' => $total_payout,
            'details' => $winning_details,
            'settled_at' => date('Y-m-d H:i:s'),
            'winning_numbers' => $winning_numbers // Include winning numbers for reference
        ];

        $update_stmt->execute([
            ':settlement_data' => json_encode($settlement_data_to_save),
            ':id' => $bet_row['id']
        ]);
    }

    file_put_contents('settlement.log', "Successfully settled bets for issue {$issue_number}\n", FILE_APPEND);

} catch (Exception $e) {
    file_put_contents('settlement_error.log', "Error during settlement for issue {$issue_number}: " . $e->getMessage() . "\n", FILE_APPEND);
}
?>
