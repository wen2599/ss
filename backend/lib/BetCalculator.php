<?php
require_once __DIR__ . '/GameData.php';

/**
 * Class BetCalculator
 *
 * Handles parsing of betting slip text and calculates the total cost.
 */
class BetCalculator {

    /**
     * Parses a betting slip text and calculates the cost.
     *
     * @param string $betting_slip_text The raw text of the betting slip.
     * @return array|null An associative array with calculation results, or null if parsing fails.
     */
    public static function calculate(string $betting_slip_text): ?array {
        $cost_per_number = 5;
        $calculation_breakdown = [];

        // a. Parse Zodiacs
        preg_match_all('/([\p{Han}]+)各数/u', $betting_slip_text, $zodiac_matches);
        $mentioned_zodiacs = [];
        if (!empty($zodiac_matches[1])) {
            $zodiac_string = implode('', $zodiac_matches[1]);
            $mentioned_zodiacs = mb_str_split($zodiac_string);
        }

        foreach ($mentioned_zodiacs as $zodiac) {
            if (isset(GameData::$zodiacMap[$zodiac])) {
                $number_count = count(GameData::$zodiacMap[$zodiac]);
                $calculation_breakdown[] = [
                    'item' => "生肖[{$zodiac}]",
                    'number_count' => $number_count,
                    'cost' => $number_count * $cost_per_number
                ];
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

        // If no zodiacs and no numbers were found, parsing has failed.
        if (empty($mentioned_zodiacs) && empty($mentioned_numbers)) {
            return null;
        }

        $unique_numbers = array_unique($mentioned_numbers);
        $explicit_number_count = count($unique_numbers);
        $calculation_breakdown[] = [
            'item' => '单独号码',
            'number_count' => $explicit_number_count,
            'cost' => $explicit_number_count * $cost_per_number
        ];

        // c. Final Calculation (De-duplicated)
        $all_bet_numbers = $unique_numbers;
        foreach ($mentioned_zodiacs as $zodiac) {
            if (isset(GameData::$zodiacMap[$zodiac])) {
                $all_bet_numbers = array_merge($all_bet_numbers, GameData::$zodiacMap[$zodiac]);
            }
        }
        $final_unique_numbers = array_unique($all_bet_numbers);
        $total_number_count = count($final_unique_numbers);
        $total_cost = $total_number_count * $cost_per_number;

        return [
            'total_cost' => $total_cost,
            'total_unique_numbers' => $total_number_count,
            'breakdown' => $calculation_breakdown
        ];
    }
}
?>
