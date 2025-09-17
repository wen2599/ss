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
    $pdo->beginTransaction();

    // 1. Fetch the odds from the database
    $stmt = $pdo->query("SELECT rule_value FROM lottery_rules WHERE rule_key = 'odds'");
    $odds_data = json_decode($stmt->fetchColumn(), true);
    $odds_special = $odds_data['special'] ?? 47;
    $odds_default = $odds_data['default'] ?? 45;

    // 2. Fetch all unsettled bets for this issue number
    $stmt = $pdo->prepare("SELECT * FROM bets WHERE issue_number = :issue_number AND status = 'unsettled'");
    $stmt->execute([':issue_number' => $issue_number]);
    $unsettled_bets = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($unsettled_bets)) {
        // No bets to settle, exit gracefully.
        $pdo->commit();
        return;
    }

    // 3. Create a temporary table to stage settlement data
    $pdo->exec("CREATE TEMPORARY TABLE temp_settlements (
        bet_id INT PRIMARY KEY,
        settlement_json JSON NOT NULL
    )");

    // 4. Loop through bets, calculate outcomes, and insert into the temporary table
    $insert_stmt = $pdo->prepare("INSERT INTO temp_settlements (bet_id, settlement_json) VALUES (?, ?)");

    foreach ($unsettled_bets as $bet_row) {
        $bet_data = json_decode($bet_row['bet_data'], true);
        $total_payout = 0;
        $winning_details = [];

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
                    $common_numbers = array_intersect($individual_bet['numbers'], $winning_numbers);
                    if (!empty($common_numbers)) {
                        $is_win = true;
                        $payout = $bet_amount * $odds_default;
                    }
                    break;
            }

            if ($is_win) {
                $total_payout += $payout;
                $winning_details[] = ['bet' => $individual_bet, 'payout' => $payout, 'is_win' => true];
            }
        }

        $settlement_data_to_save = [
            'total_payout' => $total_payout,
            'details' => $winning_details,
            'settled_at' => date('Y-m-d H:i:s'),
            'winning_numbers' => $winning_numbers
        ];

        $insert_stmt->execute([$bet_row['id'], json_encode($settlement_data_to_save)]);
    }

    // 5. Perform a single bulk update from the temporary table
    $bulk_update_sql = "
        UPDATE bets b
        JOIN temp_settlements ts ON b.id = ts.bet_id
        SET
            b.status = 'settled',
            b.settlement_data = ts.settlement_json
    ";
    $pdo->exec($bulk_update_sql);

    // 6. Clean up the temporary table (optional, as it's dropped at session end anyway)
    $pdo->exec("DROP TEMPORARY TABLE temp_settlements");

    $pdo->commit();

    file_put_contents('settlement.log', "Successfully settled " . count($unsettled_bets) . " bets for issue {$issue_number}\n", FILE_APPEND);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    file_put_contents('settlement_error.log', "Error during settlement for issue {$issue_number}: " . $e->getMessage() . "\n", FILE_APPEND);
}
?>
