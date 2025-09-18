<?php
// backend/api/settle_bets.php

/**
 * Settles all pending bets for a given lottery issue number.
 *
 * It fetches the winning numbers, calculates payouts based on predefined odds,
 * updates the bet status, and stores settlement details. It's designed to be
 * called from the Telegram webhook.
 *
 * @param PDO $pdo A connected PDO instance.
 * @param string|int $issue_number The issue number to settle.
 * @return string A summary message of the settlement result, suitable for Telegram.
 */
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

        // Get odds from the database
        $stmt = $pdo->query("SELECT rule_value FROM lottery_rules WHERE rule_key = 'odds'");
        $odds_data = json_decode($stmt->fetchColumn(), true);
        $odds_special = $odds_data['special'] ?? 47;
        $odds_default = $odds_data['default'] ?? 45; // A default for zodiac/color if not specified

        // Get all pending bets for this issue
        // Note: The original webhook had status = 'pending', but the user-facing API uses 'unsettled'.
        // Let's check for both to be safe during this transition.
        $stmt = $pdo->prepare("SELECT * FROM bets WHERE issue_number = ? AND (status = 'pending' OR status = 'unsettled')");
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
            // The user API stores parsed data in 'bet_data', the webhook stores it in 'parsed_data'
            $parsed_bets_json = $bet_row['parsed_data'] ?? $bet_row['bet_data'] ?? '[]';
            $parsed_bets = json_decode($parsed_bets_json, true);
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
        // Use the centralized logger if available
        if (function_exists('log_error')) {
            log_error("Error during settlement for issue {$issue_number}: " . $e->getMessage());
        }
        return "结算时发生严重错误。请检查日志。";
    }
}
