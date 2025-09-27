<?php
require_once __DIR__ . '/GameData.php';

class BetCalculator {

    public static function calculate(string $betting_slip_text): ?array {
        $cost_per_number = 5;
        $settlement_slip = [
            'zodiac_bets' => [],
            'number_bets' => ['numbers' => [], 'cost' => 0],
            'summary' => ['total_unique_numbers' => 0, 'total_cost' => 0]
        ];

        // a. Parse Zodiacs
        preg_match_all('/([\p{Han}]+)各数/u', $betting_slip_text, $zodiac_matches);
        $mentioned_zodiacs = [];
        if (!empty($zodiac_matches[1])) {
            $zodiac_string = implode('', $zodiac_matches[1]);
            $mentioned_zodiacs = mb_str_split($zodiac_string);
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
        $settlement_slip['number_bets']['numbers'] = array_values($unique_numbers);
        $settlement_slip['number_bets']['cost'] = count($unique_numbers) * $cost_per_number;

        // c. Final Calculation (De-duplicated)
        $all_bet_numbers = array_merge($all_zodiac_numbers, $unique_numbers);
        $final_unique_numbers = array_unique($all_bet_numbers);
        $total_number_count = count($final_unique_numbers);
        $total_cost = $total_number_count * $cost_per_number;

        $settlement_slip['summary']['total_unique_numbers'] = $total_number_count;
        $settlement_slip['summary']['total_cost'] = $total_cost;

        return $settlement_slip;
    }

    // 新增按时间点分段结算
    public static function calculateMulti(string $full_text): ?array {
        // 时间点正则：适配 21:30、22:15、08:00 等
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

        // 按时间点分段
        $segments = [];
        for ($i = 0; $i < count($timePoints); $i++) {
            $start = $timePoints[$i][1];
            $end = ($i + 1 < count($timePoints)) ? $timePoints[$i + 1][1] : mb_strlen($full_text, 'UTF-8');
            $segment = mb_substr($full_text, $start, $end - $start, 'UTF-8');
            // 去掉头部的时间点本身
            $segment = preg_replace('/^\s*\d{1,2}:\d{2}/u', '', $segment);
            $segment = trim($segment);
            if ($segment !== '' && mb_strlen(preg_replace('/[^\p{Han}0-9]/u', '', $segment)) >= 10) {
                $segments[] = [
                    'time' => $timePoints[$i][0],
                    'content' => $segment
                ];
            }
        }

        // 针对最后一个时间点到末尾
        if (count($segments) === 0) return null;

        foreach ($segments as $idx => $seg) {
            $r = self::calculate($seg['content']);
            if ($r) {
                $results[] = [
                    'index' => $idx + 1,
                    'time' => $seg['time'],
                    'raw' => $seg['content'],
                    'result' => $r
                ];
            }
        }

        return !empty($results) ? $results : null;
    }
}
?>
