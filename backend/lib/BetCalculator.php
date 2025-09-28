<?php
require_once __DIR__ . '/GameData.php';

class BetCalculator {

    // 单条下注单结算，不去重
    public static function calculateSingle(string $betting_slip_text): ?array {
        $settlement_slip = [
            'zodiac_bets' => [], // Array of groups, e.g., [['zodiac' => '鼠', 'numbers' => [...], 'cost' => 100]]
            'number_bets' => [], // Array of groups, e.g., [['numbers' => ['1','2'], 'cost_per_number' => 5, 'cost' => 10]]
            'summary' => ['number_count' => 0, 'total_cost' => 0]
        ];

        // --- Zodiac Bets Parsing ---
        // Format: "鼠,鸡数各二十[元|块]"
        $zodiac_pattern = '/((?:[\p{Han}][,，\s]*)+)数各\s*([\p{Han}\d]+)\s*[元块]?/u';
        preg_match_all($zodiac_pattern, $betting_slip_text, $zodiac_matches, PREG_SET_ORDER);

        foreach ($zodiac_matches as $match) {
            $zodiac_string = $match[1];
            $cost_text = $match[2];

            $cost_per_zodiac = self::chineseToNumber($cost_text);
            if ($cost_per_zodiac === 0 && is_numeric($cost_text)) {
                $cost_per_zodiac = intval($cost_text);
            }

            if ($cost_per_zodiac > 0) {
                $cleaned_zodiac_string = preg_replace('/[,，\s]/u', '', $zodiac_string);
                $mentioned_zodiacs = mb_str_split($cleaned_zodiac_string);

                foreach ($mentioned_zodiacs as $zodiac) {
                    if (isset(GameData::$zodiacMap[$zodiac])) {
                        $numbers = GameData::$zodiacMap[$zodiac];
                        $settlement_slip['zodiac_bets'][] = [
                            'zodiac' => $zodiac,
                            'numbers' => $numbers,
                            'cost' => $cost_per_zodiac // Cost for the whole zodiac
                        ];
                    }
                }
                // Remove the matched part from the text to avoid re-parsing
                $betting_slip_text = str_replace($match[0], '', $betting_slip_text);
            }
        }

        // --- Number Bets Parsing ---

        // Pattern 1: Numbers with cost per number, ending with '#' (e.g., "36,48各30#")
        $pattern1 = '/((?:[0-9]+[,，、\s]*)+)各\s*(\d+)\s*#/u';
        preg_match_all($pattern1, $betting_slip_text, $matches1, PREG_SET_ORDER);
        foreach ($matches1 as $match) {
            $numbers_str = $match[1];
            $cost_per_number = intval($match[2]);
            $numbers = preg_split('/[,，、\s]+/', $numbers_str);
            $valid_numbers = array_values(array_filter(array_map('trim', $numbers), 'strlen'));

            if (!empty($valid_numbers) && $cost_per_number > 0) {
                $settlement_slip['number_bets'][] = [
                    'numbers' => $valid_numbers,
                    'cost_per_number' => $cost_per_number,
                    'cost' => count($valid_numbers) * $cost_per_number
                ];
                $betting_slip_text = str_replace($match[0], '', $betting_slip_text);
            }
        }

        // Pattern 2: Numbers with multiplier (e.g., "40x10元", "40*10")
        $pattern2 = '/(\d+)\s*[xX×\*]\s*(\d+)\s*[元块]?/u';
        preg_match_all($pattern2, $betting_slip_text, $matches2, PREG_SET_ORDER);
        foreach ($matches2 as $match) {
            $number = $match[1];
            $cost = intval($match[2]);
            if ($cost > 0) {
                $settlement_slip['number_bets'][] = [
                    'numbers' => [$number],
                    'cost_per_number' => $cost,
                    'cost' => $cost
                ];
                $betting_slip_text = str_replace($match[0], '', $betting_slip_text);
            }
        }

        // Pattern 3: Numbers with cost per number, ending with unit (e.g., "04.16.28...各5块", "39、30、各5元")
        $pattern3 = '/((?:[0-9]+[.,，、\s]*)+)各\s*(\d+)\s*[元块]/u';
        preg_match_all($pattern3, $betting_slip_text, $matches3, PREG_SET_ORDER);
        foreach ($matches3 as $match) {
            $numbers_str = $match[1];
            $cost_per_number = intval($match[2]);
            $numbers = preg_split('/[.,，、\s]+/', $numbers_str);
            $valid_numbers = array_values(array_filter(array_map('trim', $numbers), 'strlen'));

            if (!empty($valid_numbers) && $cost_per_number > 0) {
                $settlement_slip['number_bets'][] = [
                    'numbers' => $valid_numbers,
                    'cost_per_number' => $cost_per_number,
                    'cost' => count($valid_numbers) * $cost_per_number
                ];
                $betting_slip_text = str_replace($match[0], '', $betting_slip_text);
            }
        }

        // c. Final Calculation
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

        // If no valid bets were parsed, return null.
        if ($total_number_count === 0) {
            return null;
        }

        $settlement_slip['summary']['number_count'] = $total_number_count;
        $settlement_slip['summary']['total_cost'] = $total_cost;

        return $settlement_slip;
    }

    // 按地区、时间点/空行分段结算
    public static function calculateMulti(string $full_text): ?array {
        $all_slips = [];

        // 1. 按地区（澳门/香港）分割文本
        $regional_blocks = preg_split('/(?=澳门|香港)/u', $full_text, -1, PREG_SPLIT_NO_EMPTY);
        if (empty($regional_blocks)) {
            // 如果没有地区标记，则将全部内容视为一个默认块
            $regional_blocks = [$full_text];
        }

        foreach ($regional_blocks as $block) {
            $block = trim($block);
            $current_region = null;

            // 识别并移除地区标记
            if (mb_strpos($block, '澳门', 0, 'UTF-8') === 0) {
                $current_region = '澳门';
                $content = trim(mb_substr($block, mb_strlen('澳门', 'UTF-8')));
            } elseif (mb_strpos($block, '香港', 0, 'UTF-8') === 0) {
                $current_region = '香港';
                $content = trim(mb_substr($block, mb_strlen('香港', 'UTF-8')));
            } else {
                $content = $block; // 没有地区标记的块
            }

            // 2. 在地区内部，按时间或空行分割
            $time_pattern = '/(?<=\n|^)\s*(\d{1,2}:\d{2})/u';
            preg_match_all($time_pattern, $content, $matches, PREG_OFFSET_CAPTURE);
            $timePoints = $matches[1];

            $slips_in_block = [];

            if (empty($timePoints)) {
                // 按空行分段
                $sub_blocks = preg_split('/\n{2,}/u', $content, -1, PREG_SPLIT_NO_EMPTY);
                foreach ($sub_blocks as $sub_block) {
                    $sub_block = trim($sub_block);
                    if (mb_strlen(preg_replace('/[^\p{Han}0-9]/u', '', $sub_block)) < 10) continue;

                    $r = self::calculateSingle($sub_block);
                    if ($r) {
                        $slips_in_block[] = [
                            'raw' => $sub_block,
                            'result' => $r,
                            'region' => $current_region
                        ];
                    }
                }
            } else {
                // 按时间点分段
                $segments = [];
                for ($i = 0; $i < count($timePoints); $i++) {
                    $start = $timePoints[$i][1];
                    $end = ($i + 1 < count($timePoints)) ? $timePoints[$i + 1][1] : mb_strlen($content, 'UTF-8');
                    $segment_text = mb_substr($content, $start, $end - $start, 'UTF-8');
                    $segment_text = preg_replace('/^\s*\d{1,2}:\d{2}/u', '', $segment_text);
                    $segment_text = trim($segment_text);

                    if ($segment_text !== '' && mb_strlen(preg_replace('/[^\p{Han}0-9]/u', '', $segment_text)) >= 10) {
                        $segments[] = [
                            'time' => $timePoints[$i][0],
                            'content' => $segment_text
                        ];
                    }
                }

                foreach ($segments as $seg) {
                    $r = self::calculateSingle($seg['content']);
                    if ($r) {
                        $slips_in_block[] = [
                            'time' => $seg['time'],
                            'raw' => $seg['content'],
                            'result' => $r,
                            'region' => $current_region
                        ];
                    }
                }
            }
            $all_slips = array_merge($all_slips, $slips_in_block);
        }

        // 3. 重新编号并计算总计
        $total_number_count = 0;
        $total_cost = 0;
        foreach ($all_slips as $key => &$item) {
            $item['index'] = $key + 1; // Assign a global, sequential index
            $total_number_count += $item['result']['summary']['number_count'];
            $total_cost += $item['result']['summary']['total_cost'];
        }
        unset($item); // Unset reference

        return [
            'slips' => $all_slips,
            'summary' => [
                'total_number_count' => $total_number_count,
                'total_cost' => $total_cost
            ]
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
