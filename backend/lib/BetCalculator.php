<?php
require_once __DIR__ . '/GameData.php';

class BetCalculator {

    public static function calculate(string $betting_slip_text): ?array {
        $cost_per_number = 5;
        $settlement_slip = [
            'zodiac_bets' => [],
            'number_bets' => ['numbers' => [], 'cost' => 0],
            'summary' => ['total_numbers_count' => 0, 'total_cost' => 0]
        ];

        // a. Parse Zodiacs
        preg_match_all('/([\p{Han}]+)各数/u', $betting_slip_text, $zodiac_matches);
        $mentioned_zodiacs = [];
        if (!empty($zodiac_matches[1])) {
            $zodiac_string = implode('', $zodiac_matches[1]);
            $mentioned_zodiacs = mb_str_split($zodiac_string);
        }

        $all_zodiac_numbers = [];
        $total_cost_from_zodiacs = 0;
        foreach ($mentioned_zodiacs as $zodiac) {
            if (isset(GameData::$zodiacMap[$zodiac])) {
                $numbers = GameData::$zodiacMap[$zodiac];
                $cost = count($numbers) * $cost_per_number;
                $settlement_slip['zodiac_bets'][] = [
                    'zodiac' => $zodiac,
                    'numbers' => $numbers,
                    'cost' => $cost
                ];
                $all_zodiac_numbers = array_merge($all_zodiac_numbers, $numbers);
                $total_cost_from_zodiacs += $cost;
            }
        }

        // b. Parse Numbers
        preg_match_all('/([0-9,]+)各5#/u', $betting_slip_text, $number_matches);
        $mentioned_numbers = [];
        if (!empty($number_matches[1])) {
            foreach ($number_matches[1] as $number_group) {
                $numbers = explode(',', trim($number_group, ','));
                foreach ($numbers as $num) {
                    if (!empty($num)) {
                        $mentioned_numbers[] = trim($num);
                    }
                }
            }
        }

        if (empty($mentioned_zodiacs) && empty($mentioned_numbers)) {
            return null;
        }

        $settlement_slip['number_bets']['numbers'] = $mentioned_numbers;
        $cost_from_numbers = count($mentioned_numbers) * $cost_per_number;
        $settlement_slip['number_bets']['cost'] = $cost_from_numbers;

        // c. Final Calculation (No De-duplication)
        $total_numbers_count = count($all_zodiac_numbers) + count($mentioned_numbers);
        $total_cost = $total_cost_from_zodiacs + $cost_from_numbers;

        $settlement_slip['summary']['total_numbers_count'] = $total_numbers_count;
        $settlement_slip['summary']['total_cost'] = $total_cost;

        return $settlement_slip;
    }

    public static function calculateMulti(string $full_text): ?array {
        $blocks = preg_split('/\n{2,}/u', $full_text, -1, PREG_SPLIT_NO_EMPTY);
        $results = [];
        foreach ($blocks as $idx => $block) {
            $block = trim($block);
            if (mb_strlen(preg_replace('/[^\p{Han}0-9]/u', '', $block)) < 10) continue;
            $r = self::calculate($block);
            if ($r) {
                $results[] = [
                    'index' => $idx + 1,
                    'raw' => $block,
                    'result' => $r
                ];
            }
        }
        return !empty($results) ? $results : null;
    }
}
?>
