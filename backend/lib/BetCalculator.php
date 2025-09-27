<?php
require_once __DIR__ . '/GameData.php';

class BetCalculator {

    // 单条下注单结算，不去重
    public static function calculateSingle(string $betting_slip_text): ?array {
        $cost_per_number = 5;
        $settlement_slip = [
            'zodiac_bets' => [],
            'number_bets' => ['numbers' => [], 'cost' => 0],
            'summary' => ['number_count' => 0, 'total_cost' => 0]
        ];

        // a. Parse Zodiacs - More flexible regex to allow separators (space, comma) between zodiacs.
        preg_match_all('/((?:[\p{Han}][,，\s]*)+)各数/u', $betting_slip_text, $zodiac_matches);
        $mentioned_zodiacs = [];
        if (!empty($zodiac_matches[1])) {
            $zodiac_string = implode('', $zodiac_matches[1]);
            // Clean up separators before splitting into characters
            $cleaned_zodiac_string = preg_replace('/[,，\s]/u', '', $zodiac_string);
            $mentioned_zodiacs = mb_str_split($cleaned_zodiac_string);
        }

        $all_zodiac_numbers = [];
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
            }
        }

        // b. Parse Numbers - More flexible regex for number bets, allowing different separators and spacing.
        preg_match_all('/([0-9]+(?:[,，、\s]+[0-9]+)*)\s*各\s*5\s*#/u', $betting_slip_text, $number_matches);
        $mentioned_numbers = [];
        if (!empty($number_matches[1])) {
            foreach ($number_matches[1] as $number_group) {
                // Split by various separators.
                $numbers = preg_split('/[,，、\s]+/', $number_group);
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

        $settlement_slip['number_bets']['numbers'] = $mentioned_numbers;
        $settlement_slip['number_bets']['cost'] = count($mentioned_numbers) * $cost_per_number;

        // c. Final Calculation (不去重)
        $all_bet_numbers = array_merge($all_zodiac_numbers, $mentioned_numbers);
        $number_count = count($all_bet_numbers);
        $total_cost = $number_count * $cost_per_number;

        $settlement_slip['summary']['number_count'] = $number_count;
        $settlement_slip['summary']['total_cost'] = $total_cost;

        return $settlement_slip;
    }

    // 按时间点分段结算，每段用calculateSingle，最后总计也不去重
    public static function calculateMulti(string $full_text): ?array {
        $pattern = '/(?<=\n|^)\s*(\d{1,2}:\d{2})/u';
        preg_match_all($pattern, $full_text, $matches, PREG_OFFSET_CAPTURE);

        $results = [];
        $timePoints = $matches[1];

        if (empty($timePoints)) {
            // 没有时间点则按原空行分段逻辑
            $blocks = preg_split('/\n{2,}/u', $full_text, -1, PREG_SPLIT_NO_EMPTY);
            foreach ($blocks as $idx => $block) {
                $block = trim($block);
                if (mb_strlen(preg_replace('/[^\p{Han}0-9]/u', '', $block)) < 10) continue;
                $r = self::calculateSingle($block);
                if ($r) {
                    $results[] = [
                        'index' => $idx + 1,
                        'raw' => $block,
                        'result' => $r
                    ];
                }
            }
        } else {
            // 按时间点分段
            $segments = [];
            for ($i = 0; $i < count($timePoints); $i++) {
                $start = $timePoints[$i][1];
                $end = ($i + 1 < count($timePoints)) ? $timePoints[$i + 1][1] : mb_strlen($full_text, 'UTF-8');
                $segment = mb_substr($full_text, $start, $end - $start, 'UTF-8');
                $segment = preg_replace('/^\s*\d{1,2}:\d{2}/u', '', $segment);
                $segment = trim($segment);
                if ($segment !== '' && mb_strlen(preg_replace('/[^\p{Han}0-9]/u', '', $segment)) >= 10) {
                    $segments[] = [
                        'time' => $timePoints[$i][0],
                        'content' => $segment
                    ];
                }
            }
            foreach ($segments as $idx => $seg) {
                $r = self::calculateSingle($seg['content']);
                if ($r) {
                    $results[] = [
                        'index' => $idx + 1,
                        'time' => $seg['time'],
                        'raw' => $seg['content'],
                        'result' => $r
                    ];
                }
            }
        }

        // 总计统计（不去重，所有段合并）
        $total_number_count = 0;
        $total_cost = 0;
        foreach ($results as $item) {
            $total_number_count += $item['result']['summary']['number_count'];
            $total_cost += $item['result']['summary']['total_cost'];
        }

        return [
            'slips' => $results,
            'summary' => [
                'total_number_count' => $total_number_count,
                'total_cost' => $total_cost
            ]
        ];
    }
}
?>
