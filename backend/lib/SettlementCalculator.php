<?php
require_once __DIR__ . '/GameData.php';

class SettlementCalculator {

    // NOTE: Odds are hardcoded for now. These should ideally be configurable.
    private const NORMAL_ODDS = 40;
    private const SPECIAL_ODDS = 45;

    /**
     * Settles a raw betting slip against a given lottery result.
     *
     * @param string $raw_slip_text The raw text of the betting slip.
     * @param array|null $lottery_result The lottery result, e.g., ['numbers' => [...], 'special' => '...'].
     * @return string The formatted settlement result text.
     */
    public static function settle(string $raw_slip_text, ?array $lottery_result): string {
        $parsed_bets = self::parse_slip($raw_slip_text);

        if (empty($parsed_bets['bets'])) {
            return "无法解析下注单或无有效投注。";
        }

        if ($lottery_result === null) {
            return "无法找到对应的开奖结果，无法结算。";
        }

        $winning_numbers = $lottery_result['numbers'];
        $special_number = $lottery_result['special'];

        $total_payout = 0;
        $total_cost = $parsed_bets['total_cost'];
        $result_details = [];

        foreach ($parsed_bets['bets'] as $bet) {
            if (in_array($bet['number'], $winning_numbers)) {
                $payout = $bet['cost'] * self::NORMAL_ODDS;
                $total_payout += $payout;
                $result_details[] = "号码 {$bet['number']} 中普通码，赢 {$payout}元 (成本 {$bet['cost']}元)";
            } elseif ($bet['number'] == $special_number) {
                $payout = $bet['cost'] * self::SPECIAL_ODDS;
                $total_payout += $payout;
                $result_details[] = "号码 {$bet['number']} 中特码，赢 {$payout}元 (成本 {$bet['cost']}元)";
            }
        }

        $net_win_loss = $total_payout - $total_cost;

        $output = "--- 结算详情 ---\n";
        if (empty($result_details)) {
            $output .= "所有号码均未中奖。\n";
        } else {
            $output .= implode("\n", $result_details) . "\n";
        }
        $output .= "-----------------\n";
        $output .= "总投注: {$total_cost}元\n";
        $output .= "总派彩: {$total_payout}元\n";
        $output .= "总输赢: " . ($net_win_loss >= 0 ? "赢 " : "输 ") . abs($net_win_loss) . "元";

        return $output;
    }

    /**
     * Parses a raw slip text into a list of individual number bets.
     *
     * @param string $text The raw text of the betting slip.
     * @return array An array containing all bets and the total cost.
     */
    private static function parse_slip(string $text): array {
        $all_bets = [];

        // Pattern 1: Zodiacs (e.g., 蛇猪鸡各数5#)
        preg_match_all('/([\p{Han}]+)各数(\d+)/u', $text, $zodiac_matches, PREG_SET_ORDER);
        foreach ($zodiac_matches as $match) {
            $zodiacs_str = $match[1];
            $cost_per_number = (int)$match[2];
            $zodiacs = mb_str_split($zodiacs_str);
            foreach ($zodiacs as $zodiac) {
                if (isset(GameData::$zodiacMap[$zodiac])) {
                    foreach (GameData::$zodiacMap[$zodiac] as $number) {
                        $all_bets[] = ['number' => $number, 'cost' => $cost_per_number];
                    }
                }
            }
        }

        // Pattern 2: Numbers (e.g., 17,29,35各10 or 12.24.36各5块 or 01,13,25各5#)
        preg_match_all('/([0-9,.\s]+)(?:各|各数)(\d+)(?:#|块|元)?/u', $text, $number_matches, PREG_SET_ORDER);
        foreach ($number_matches as $match) {
            // Clean up the string to only contain numbers and valid separators.
            $numbers_str = preg_replace('/[^\d,.]/', '', $match[1]);
            $numbers = preg_split('/[,.]/', $numbers_str, -1, PREG_SPLIT_NO_EMPTY);
            $cost_per_number = (int)$match[2];
            foreach ($numbers as $number) {
                // Pad with leading zero to ensure consistent format (e.g., '4' -> '04')
                $num_val = str_pad(trim($number), 2, '0', STR_PAD_LEFT);
                $all_bets[] = ['number' => $num_val, 'cost' => $cost_per_number];
            }
        }

        $total_cost = array_sum(array_column($all_bets, 'cost'));

        return ['bets' => $all_bets, 'total_cost' => $total_cost];
    }
}
?>