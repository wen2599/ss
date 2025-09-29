<?php
require_once __DIR__ . '/GameData.php';

class BetCalculator {

    public static function calculateSingle(string $betting_slip_text): ?array {
        $settlement_slip = [
            'zodiac_bets' => [],
            'number_bets' => [],
            'summary' => ['number_count' => 0, 'total_cost' => 0]
        ];
        $text = $betting_slip_text;

        // 1. Unified Zodiac "Per Number" Parsing (Handles both "数各" and "各数")
        // This regex now correctly handles multiple bet expressions on a single line by looking for a start-of-string or separator before matching.
        $pattern = '/(?:^|[,，\s])\s*([\p{Han},，\s]+?)(?:数各|各数)\s*([\p{Han}\d]+)\s*[元块]?/u';
        if (preg_match_all($pattern, $text, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $zodiac_string = preg_replace('/[,，\s]/u', '', $match[1]);
                $zodiacs = mb_str_split($zodiac_string);
                $cost_per_number = self::chineseToNumber($match[2]) ?: intval($match[2]);

                if ($cost_per_number > 0 && !empty($zodiacs)) {
                    $numbers = [];
                    foreach ($zodiacs as $z) {
                        if (isset(GameData::$zodiacMap[$z])) {
                            $numbers = array_merge($numbers, GameData::$zodiacMap[$z]);
                        }
                    }
                    $unique_numbers = array_values(array_unique($numbers));
                    if (!empty($unique_numbers)) {
                        $settlement_slip['number_bets'][] = [
                            'numbers' => $unique_numbers, 'cost_per_number' => $cost_per_number, 'cost' => count($unique_numbers) * $cost_per_number, 'source_zodiacs' => $zodiacs
                        ];
                    }
                }
            }
            $text = preg_replace($pattern, '', $text);
        }

        // 2. Number lists (e.g., "06-36各5元", "36,48各30#")
        $pattern = '/([0-9.,，、\s-]+)各\s*(\d+)\s*(?:#|[元块])/u';
        if (preg_match_all($pattern, $text, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $numbers = preg_split('/[.,，、\s-]+/', $match[1], -1, PREG_SPLIT_NO_EMPTY);
                $cost = intval($match[2]);
                if (!empty($numbers) && $cost > 0) {
                    $settlement_slip['number_bets'][] = [ 'numbers' => $numbers, 'cost_per_number' => $cost, 'cost' => count($numbers) * $cost ];
                }
            }
            $text = preg_replace($pattern, '', $text);
        }

        // 3. Multiplier numbers (e.g., "40x10元")
        $pattern = '/(\d+)\s*[xX×\*]\s*(\d+)\s*[元块]?/u';
        if (preg_match_all($pattern, $text, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $cost = intval($match[2]);
                if ($cost > 0) {
                    $settlement_slip['number_bets'][] = [ 'numbers' => [$match[1]], 'cost_per_number' => $cost, 'cost' => $cost ];
                }
            }
            $text = preg_replace($pattern, '', $text);
        }

        // Final Calculation
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
        return $settlement_slip;
    }

    public static function calculateMulti(string $full_text): ?array {
        $all_slips = [];
        $regional_blocks = preg_split('/(?=澳门|香港)/u', $full_text, -1, PREG_SPLIT_NO_EMPTY);
        if (empty($regional_blocks)) {
            $regional_blocks = [$full_text];
        }

        foreach ($regional_blocks as $block) {
            $block = trim($block);
            $current_region = null;
            $content = $block;

            if (mb_strpos($block, '澳门', 0, 'UTF-8') === 0) {
                $current_region = '澳门';
                $content = trim(mb_substr($block, mb_strlen('澳门', 'UTF-8')));
            } elseif (mb_strpos($block, '香港', 0, 'UTF-8') === 0) {
                $current_region = '香港';
                $content = trim(mb_substr($block, mb_strlen('香港', 'UTF-8')));
            }

            $time_pattern = '/(\d{1,2}:\d{2})/u';
            $parts = preg_split($time_pattern, $content, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);

            if (count($parts) <= 1) {
                $sub_blocks = preg_split('/\n{2,}/u', $content, -1, PREG_SPLIT_NO_EMPTY);
                foreach ($sub_blocks as $sub_block) {
                    if (mb_strlen(preg_replace('/[^\p{Han}0-9]/u', '', $sub_block)) < 10) continue;
                    $r = self::calculateSingle(trim($sub_block));
                    if ($r) $all_slips[] = ['raw' => trim($sub_block), 'result' => $r, 'region' => $current_region];
                }
            } else {
                $current_slip_content = !preg_match($time_pattern, $parts[0]) ? array_shift($parts) : '';
                $current_time = null;
                foreach ($parts as $part) {
                    if (preg_match($time_pattern, $part)) {
                        if (!empty(trim($current_slip_content))) {
                            $r = self::calculateSingle(trim($current_slip_content));
                            if ($r) $all_slips[] = ['time' => $current_time, 'raw' => trim($current_slip_content), 'result' => $r, 'region' => $current_region];
                        }
                        $current_time = $part;
                        $current_slip_content = '';
                    } else {
                        $current_slip_content .= $part;
                    }
                }
                if (!empty(trim($current_slip_content))) {
                    $r = self::calculateSingle(trim($current_slip_content));
                    if ($r) $all_slips[] = ['time' => $current_time, 'raw' => trim($current_slip_content), 'result' => $r, 'region' => $current_region];
                }
            }
        }

        if (empty($all_slips)) return null;

        $total_number_count = 0;
        $total_cost = 0;
        foreach ($all_slips as $key => &$item) {
            $item['index'] = $key + 1;
            $total_number_count += $item['result']['summary']['number_count'];
            $total_cost += $item['result']['summary']['total_cost'];
        }
        unset($item);

        return [
            'slips' => $all_slips,
            'summary' => [
                'total_number_count' => $total_number_count,
                'total_cost' => $total_cost
            ]
        ];
    }

    private static function chineseToNumber(string $text): int {
        $map = [ '零'=>0,'一'=>1,'二'=>2,'三'=>3,'四'=>4,'五'=>5,'六'=>6,'七'=>7,'八'=>8,'九'=>9,'两'=>2 ];
        $text = trim(str_replace('两', '二', $text));
        if (isset($map[$text])) return $map[$text];
        if ($text === '十') return 10;
        if (mb_substr($text, 0, 1) === '十') return 10 + ($map[mb_substr($text, 1, 1)] ?? 0);
        if (mb_substr($text, -1, 1) === '十') return ($map[mb_substr($text, 0, 1)] ?? 0) * 10;
        $parts = mb_split('十', $text);
        if (count($parts) === 2 && isset($map[$parts[0]]) && isset($map[$parts[1]])) {
            return $map[$parts[0]] * 10 + $map[$parts[1]];
        }
        return 0;
    }

    public static function settle(array $bill_details, array $lottery_results_map, float $winning_rate = 45.0): array {
        $settled_details = $bill_details;
        $total_winnings = 0;

        // Get the arrays of winning numbers for each region
        $hk_numbers = $lottery_results_map['香港'] ?? null;
        $nm_numbers = $lottery_results_map['新澳门'] ?? null;

        // Extract the special number (7th number) for each region
        $hk_special_number = isset($hk_numbers) && count($hk_numbers) >= 7 ? $hk_numbers[6] : null;
        $nm_special_number = isset($nm_numbers) && count($nm_numbers) >= 7 ? $nm_numbers[6] : null;

        foreach ($settled_details['slips'] as &$slip) {
            $region = $slip['region'] ?? '';
            $slip_winnings = 0;

            // Determine which special number to use based on the slip's region
            $special_number = (strpos($region, '香港') !== false || strpos($region, '港') !== false) ? $hk_special_number : $nm_special_number;

            // If there's no special number for this region, skip settlement for this slip
            if ($special_number === null) {
                continue;
            }

            if (isset($slip['result']['number_bets'])) {
                foreach ($slip['result']['number_bets'] as &$bet) {
                    $bet['winning_numbers'] = [];
                    $bet['winnings'] = 0;
                    if (isset($bet['cost_per_number'])) {
                        foreach ($bet['numbers'] as $number) {
                            // Check if the bet number matches the special number
                            if ($number == $special_number) {
                                $win_amount = $bet['cost_per_number'] * $winning_rate;
                                $bet['winnings'] += $win_amount;
                                $bet['winning_numbers'][] = $number;
                            }
                        }
                    }
                    $slip_winnings += $bet['winnings'];
                }
                unset($bet);
            }
            $slip['result']['summary']['winnings'] = $slip_winnings;
            $total_winnings += $slip_winnings;
        }
        unset($slip);

        // Calculate total winnings and the net result (win/loss)
        $settled_details['summary']['total_winnings'] = $total_winnings;
        $total_cost = $settled_details['summary']['total_cost'] ?? 0;
        $settled_details['summary']['net_result'] = $total_winnings - $total_cost;

        return $settled_details;
    }
}
?>