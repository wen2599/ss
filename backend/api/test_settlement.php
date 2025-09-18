<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/tg_webhook_unified.php';

echo "--- Settlement Logic Test Script ---\n\n";

$pdo = getDbConnection();

// Test Data
$test_issue_number = '2025999';
$test_lottery_name = 'TestLotto';
$test_winning_numbers = [1, 2, 3, 4, 5, 6];
$test_special_number = 7;

// Sample Bets
$bet1_parsed = [
    ['type' => 'special', 'number' => 7, 'amount' => 10, 'display_name' => '特码'], // WIN
    ['type' => 'special', 'number' => 8, 'amount' => 10, 'display_name' => '特码']  // LOSE
];
$bet2_parsed = [
    ['type' => 'zodiac', 'name' => '鸡', 'numbers' => [1, 13, 25, 37, 49], 'amount' => 20, 'display_name' => '生肖'], // WIN (number 1)
    ['type' => 'color', 'name' => '蓝波', 'numbers' => [3, 4, 9, 10], 'amount' => 30, 'display_name' => '波色'] // WIN (numbers 3, 4)
];
$bet3_parsed = [
    ['type' => 'zodiac', 'name' => '狗', 'numbers' => [10, 22, 34, 46], 'amount' => 50, 'display_name' => '生肖'] // LOSE
];

// Odds
$test_odds = ['special' => 47, 'default' => 45];
$test_zodiacs = ['鸡' => [1, 13, 25, 37, 49], '狗' => [10, 22, 34, 46]];
$test_colors = ['蓝波' => [3, 4, 9, 10, 15, 16, 21, 22, 27, 28, 32, 33, 38, 39, 44, 45, 50]];


function cleanup($pdo, $issue) {
    echo "Cleaning up test data...\n";
    $pdo->prepare("DELETE FROM lottery_draws WHERE issue_number = ?")->execute([$issue]);
    $pdo->prepare("DELETE FROM bets WHERE issue_number = ?")->execute([$issue]);
    $pdo->prepare("DELETE FROM lottery_rules WHERE rule_key LIKE 'test_%'")->execute();
    echo "Cleanup complete.\n";
}

try {
    // 1. Setup
    echo "1. Setting up test data...\n";
    $pdo->beginTransaction();

    // Insert rules
    $stmt = $pdo->prepare("INSERT INTO lottery_rules (rule_key, rule_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE rule_value = VALUES(rule_value)");
    $stmt->execute(['odds', json_encode($test_odds)]);
    $stmt->execute(['zodiac_mappings', json_encode($test_zodiacs)]);
    $stmt->execute(['color_mappings', json_encode($test_colors)]);

    // Insert draw result
    $stmt = $pdo->prepare("INSERT INTO lottery_draws (issue_number, lottery_name, numbers, special_number) VALUES (?, ?, ?, ?)");
    $stmt->execute([$test_issue_number, $test_lottery_name, json_encode($test_winning_numbers), $test_special_number]);

    // Insert bets
    $stmt = $pdo->prepare("INSERT INTO bets (issue_number, original_content, parsed_data, status) VALUES (?, ?, ?, 'pending')");
    $stmt->execute([$test_issue_number, 'test content 1', json_encode($bet1_parsed)]);
    $bet1_id = $pdo->lastInsertId();
    $stmt->execute([$test_issue_number, 'test content 2', json_encode($bet2_parsed)]);
    $bet2_id = $pdo->lastInsertId();
    $stmt->execute([$test_issue_number, 'test content 3', json_encode($bet3_parsed)]);
    $bet3_id = $pdo->lastInsertId();

    $pdo->commit();
    echo "Setup complete. Inserted 3 bets with IDs: $bet1_id, $bet2_id, $bet3_id\n\n";

    // 2. Execute
    echo "2. Executing settleBetsForIssue function...\n";
    $result_message = settleBetsForIssue($pdo, $test_issue_number);
    echo "Function returned: \"$result_message\"\n\n";

    // 3. Assert
    echo "3. Verifying results...\n";
    $stmt = $pdo->prepare("SELECT * FROM bets WHERE id IN (?, ?, ?)");
    $stmt->execute([$bet1_id, $bet2_id, $bet3_id]);
    $settled_bets = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

    $all_settled = true;
    $payouts_correct = true;

    // Bet 1: Payout should be 10 * 47 = 470
    $payout1 = json_decode($settled_bets[$bet1_id]['settlement_data'], true)['total_payout'];
    if ($settled_bets[$bet1_id]['status'] !== 'settled' || $payout1 != 470) {
        $payouts_correct = false;
        echo "FAIL: Bet 1 incorrect. Status: {$settled_bets[$bet1_id]['status']}, Payout: {$payout1} (expected 470)\n";
    } else {
        echo "PASS: Bet 1 correct.\n";
    }

    // Bet 2: Payout should be (20 * 45) + (30 * 45) = 900 + 1350 = 2250
    $payout2 = json_decode($settled_bets[$bet2_id]['settlement_data'], true)['total_payout'];
    if ($settled_bets[$bet2_id]['status'] !== 'settled' || $payout2 != 2250) {
        $payouts_correct = false;
        echo "FAIL: Bet 2 incorrect. Status: {$settled_bets[$bet2_id]['status']}, Payout: {$payout2} (expected 2250)\n";
    } else {
        echo "PASS: Bet 2 correct.\n";
    }

    // Bet 3: Payout should be 0
    $payout3 = json_decode($settled_bets[$bet3_id]['settlement_data'], true)['total_payout'];
    if ($settled_bets[$bet3_id]['status'] !== 'settled' || $payout3 != 0) {
        $payouts_correct = false;
        echo "FAIL: Bet 3 incorrect. Status: {$settled_bets[$bet3_id]['status']}, Payout: {$payout3} (expected 0)\n";
    } else {
        echo "PASS: Bet 3 correct.\n";
    }

    echo "\n--- TEST SUMMARY ---\n";
    if ($payouts_correct) {
        echo "✅ SUCCESS: All bets were settled with the correct payout amounts.\n";
    } else {
        echo "❌ FAILURE: One or more bets had incorrect settlement data.\n";
    }

} catch (Exception $e) {
    echo "AN UNEXPECTED ERROR OCCURRED: " . $e->getMessage() . "\n";
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
} finally {
    // 4. Teardown
    cleanup($pdo, $test_issue_number);
}

?>
