<?php
require_once __DIR__ . '/GameData.php';

class BetCalculator {

    // 单条下注单结算，不去重
    public static function calculateSingle(string $betting_slip_text): ?array {
        $original_length = mb_strlen($betting_slip_text, 'UTF-8');
        if ($original_length === 0) return null;

        $settlement_slip = [
            'zodiac_bets' => [],
            'number_bets' => [],
            'summary' => ['number_count' => 0, 'total_cost' => 0]
        ];
        $text = $betting_slip_text;

        // --- Parsing Logic ---
        // Each block of logic will now parse its pattern and then replace the matches from the text.

        // Pattern 1: Zodiacs per number (e.g., "鼠马各数5元")
        $zodiac_by_number_pattern = '/((?:[\p{Han}]+[,，\s]*)+?)各数\s*([\p{Han}\d]+)\s*[元块]?/u';
        if (preg_match_all($zodiac_by_number_pattern, $text, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $zodiac_string = $match[1];
                $cost_text = $match[2];
                $cost_per_number = self::chineseToNumber($cost_text) ?: (is_numeric($cost_text) ? intval($cost_text) : 0);
                if ($cost_per_number > 0) {
                    $cleaned_zodiac_string = preg_replace('/[,，\s]/u', '', $zodiac_string);
                    $mentioned_zodiacs = mb_str_split($cleaned_zodiac_string);
                    $all_numbers = [];
                    foreach ($mentioned_zodiacs as $zodiac) {
                        if (isset(GameData::$zodiacMap[$zodiac])) {
                            $all_numbers = array_merge($all_numbers, GameData::$zodiacMap[$zodiac]);
                        }
                    }
                    $unique_numbers = array_values(array_unique($all_numbers));
                    if (!empty($unique_numbers)) {
                        $settlement_slip['number_bets'][] = [ 'numbers' => $unique_numbers, 'cost_per_number' => $cost_per_number, 'cost' => count($unique_numbers) * $cost_per_number, 'source_zodiacs' => $mentioned_zodiacs ];
                    }
                }
            }
            $text = preg_replace($zodiac_by_number_pattern, '', $text);
        }

        // Pattern 2: Zodiac sets (e.g., "鼠数各20元")
        $zodiac_set_pattern = '/((?:[\p{Han}]+[,，\s]*)+?)数各\s*([\p{Han}\d]+)\s*[元块]?/u';
        if (preg_match_all($zodiac_set_pattern, $text, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $cost_text = $match[2];
                $cost_per_zodiac = self::chineseToNumber($cost_text) ?: (is_numeric($cost_text) ? intval($cost_text) : 0);
                if ($cost_per_zodiac > 0) {
                    $cleaned_zodiac_string = preg_replace('/[,，\s]/u', '', $match[1]);
                    $mentioned_zodiacs = mb_str_split($cleaned_zodiac_string);
                    foreach ($mentioned_zodiacs as $zodiac) {
                        if (isset(GameData::$zodiacMap[$zodiac])) {
                            $settlement_slip['zodiac_bets'][] = [ 'zodiac' => $zodiac, 'numbers' => GameData::$zodiacMap[$zodiac], 'cost' => $cost_per_zodiac ];
                        }
                    }
                }
            }
            $text = preg_replace($zodiac_set_pattern, '', $text);
        }

        // Pattern 3: Number lists (e.g., "06-36各5元", "36,48各30#")
        $number_list_pattern = '/((?:[0-9]+[.,，、\s-]*)+)各\s*(\d+)\s*(?:#|[元块])/u';
        if (preg_match_all($number_list_pattern, $text, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $numbers = preg_split('/[.,，、\s-]+/', $match[1]);
                $valid_numbers = array_values(array_filter(array_map('trim', $numbers), 'strlen'));
                $cost_per_number = intval($match[2]);
                if (!empty($valid_numbers) && $cost_per_number > 0) {
                    $settlement_slip['number_bets'][] = [ 'numbers' => $valid_numbers, 'cost_per_number' => $cost_per_number, 'cost' => count($valid_numbers) * $cost_per_number ];
                }
            }
            $text = preg_replace($number_list_pattern, '', $text);
        }

        // Pattern 4: Multiplier numbers (e.g., "40x10元")
        $multiplier_pattern = '/(\d+)\s*[xX×\*]\s*(\d+)\s*[元块]?/u';
        if (preg_match_all($multiplier_pattern, $text, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                if (intval($match[2]) > 0) {
                    $settlement_slip['number_bets'][] = [ 'numbers' => [$match[1]], 'cost_per_number' => intval($match[2]), 'cost' => intval($match[2]) ];
                }
            }
            $text = preg_replace($multiplier_pattern, '', $text);
        }

        // --- Final Calculation ---
        $total_cost = 0;
        $total_number_count = 0;
        foreach ($settlement_slip['zodiac_bets'] as $bet) {
            $total_cost += $bet['cost'];
            $total_number_count += count($bet['numbers']);
        }
        foreach ($settlement_slip['number_bets'] as $bet) {
            $total_cost += $bet['cost'];
            $total_number_count += count($bet['numbers']);
        }

        if ($total_number_count === 0) return null;

        $settlement_slip['summary']['number_count'] = $total_number_count;
        $settlement_slip['summary']['total_cost'] = $total_cost;

        // --- Confidence Score Calculation ---
        $unparsed_text = trim(preg_replace('/\s+/', ' ', $text));
        $unparsed_length = mb_strlen($unparsed_text, 'UTF-8');
        $confidence = 1.0 - ($unparsed_length / $original_length);

        return [
            'settlement' => $settlement_slip,
            'unparsed_text' => $unparsed_text,
            'confidence' => round($confidence, 4)
        ];
    }

    // 按地区、时间点/空行分段结算
    public static function calculateMulti(string $full_text): ?array {
        $all_slips = [];

        // 1. 按地区（澳门/香港）分割文本
        $regional_blocks = preg_split('/(?=澳门|香港)/u', $full_text, -1, PREG_SPLIT_NO_EMPTY);
        if (empty($regional_blocks)) {
            $regional_blocks = [$full_text];
        }

        foreach ($regional_blocks as $block) {
            $block = trim($block);
            $current_region = null;

            if (mb_strpos($block, '澳门', 0, 'UTF-8') === 0) {
                $current_region = '澳门';
                $content = trim(mb_substr($block, mb_strlen('澳门', 'UTF-8')));
            } elseif (mb_strpos($block, '香港', 0, 'UTF-8') === 0) {
                $current_region = '香港';
                $content = trim(mb_substr($block, mb_strlen('香港', 'UTF-8')));
            } else {
                $content = $block;
            }

            // 2. 在地区内部，按时间或空行分割
            $time_pattern = '/(\d{1,2}:\d{2})/u';
            $parts = preg_split($time_pattern, $content, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);

            $slips_in_block = [];

            if (count($parts) <= 1) {
                $sub_blocks = preg_split('/\n{2,}/u', $content, -1, PREG_SPLIT_NO_EMPTY);
                foreach ($sub_blocks as $sub_block) {
                    if (mb_strlen(preg_replace('/[^\p{Han}0-9]/u', '', $sub_block)) < 10) continue;
                    $r = self::calculateSingle(trim($sub_block));
                    if ($r) $slips_in_block[] = ['raw' => trim($sub_block), 'result' => $r, 'region' => $current_region];
                }
            } else {
                $current_slip_content = !preg_match($time_pattern, $parts[0]) ? array_shift($parts) : '';
                $current_time = null;
                foreach ($parts as $part) {
                    if (preg_match($time_pattern, $part)) {
                        if (!empty(trim($current_slip_content))) {
                            $r = self::calculateSingle(trim($current_slip_content));
                            if ($r) $slips_in_block[] = ['time' => $current_time, 'raw' => trim($current_slip_content), 'result' => $r, 'region' => $current_region];
                        }
                        $current_time = $part;
                        $current_slip_content = '';
                    } else {
                        $current_slip_content .= $part;
                    }
                }
                if (!empty(trim($current_slip_content))) {
                    $r = self::calculateSingle(trim($current_slip_content));
                    if ($r) $slips_in_block[] = ['time' => $current_time, 'raw' => trim($current_slip_content), 'result' => $r, 'region' => $current_region];
                }
            }
            $all_slips = array_merge($all_slips, $slips_in_block);
        }

        // --- Aggregation and Final Calculation ---
        if (empty($all_slips)) return null;

        $total_number_count = 0;
        $total_cost = 0;
        $total_confidence = 0;
        $all_unparsed_text = [];

        foreach ($all_slips as $key => &$slip) {
            $slip['index'] = $key + 1;
            $result = $slip['result'];
            $total_number_count += $result['settlement']['summary']['number_count'];
            $total_cost += $result['settlement']['summary']['total_cost'];
            $total_confidence += $result['confidence'];
            if (!empty($result['unparsed_text'])) {
                $all_unparsed_text[] = $result['unparsed_text'];
            }
            // Restructure the slip to nest the settlement details correctly
            $slip['settlement_details'] = $result['settlement'];
            unset($slip['result']); // Clean up old structure
        }
        unset($slip);

        $average_confidence = !empty($all_slips) ? $total_confidence / count($all_slips) : 0;

        return [
            'slips' => $all_slips,
            'summary' => [
                'total_number_count' => $total_number_count,
                'total_cost' => $total_cost
            ],
            'confidence' => round($average_confidence, 4),
            'unparsed_text' => implode("\n", $all_unparsed_text)
        ];
    }

    /**
     * Converts simple Chinese numeric strings to integers.
     * Handles cases like "五", "十", "二十", "二十五".
     * @param string $text The Chinese numeral text.
     * @return int The integer value.
     */
    private static function chineseToNumber(string $text): int {
        $map = [
            '零' => 0, '一' => 1, '二' => 2, '三' => 3, '四' => 4, '五' => 5,
            '六' => 6, '七' => 7, '八' => 8, '九' => 9, '两' => 2,
        ];
        $text = trim(str_replace('两', '二', $text));

        if (isset($map[$text])) {
            return $map[$text];
        }

        if ($text === '十') {
            return 10;
        }

        // "十五" -> 15
        if (mb_substr($text, 0, 1) === '十') {
            $unit_char = mb_substr($text, 1, 1);
            return 10 + ($map[$unit_char] ?? 0);
        }

        // "二十" -> 20
        if (mb_substr($text, -1, 1) === '十') {
            $ten_char = mb_substr($text, 0, 1);
            if (isset($map[$ten_char])) {
                return $map[$ten_char] * 10;
            }
        }

        // "二十五" -> 25
        $parts = mb_split('十', $text);
        if (count($parts) === 2 && isset($map[$parts[0]]) && isset($map[$parts[1]])) {
            return $map[$parts[0]] * 10 + $map[$parts[1]];
        }

        return 0;
    }
}
?>