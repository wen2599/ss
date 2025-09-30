<?php
require_once __DIR__ . '/GameData.php';

class BetCalculator {
    private $pdo;
    private $parsing_templates;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
        $this->loadParsingTemplates();
    }

    /**
     * Loads all active parsing templates from the database.
     */
    private function loadParsingTemplates() {
        try {
            $stmt = $this->pdo->query("SELECT pattern, type FROM parsing_templates ORDER BY priority ASC");
            $this->parsing_templates = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            // If the table doesn't exist or there's an error, use a default fallback.
            // This ensures the system can function even before the AI feature is fully set up.
            $this->parsing_templates = [
                ['type' => 'zodiac', 'pattern' => '/([\p{Han},，\s]+?)(?:数各|各数)\s*([\p{Han}\d]+)\s*[元块]?/u'],
                ['type' => 'number_list', 'pattern' => '/([0-9.,，、\s-]+)各\s*(\d+)\s*(?:#|[元块])/u'],
                ['type' => 'multiplier', 'pattern' => '/(\d+)\s*[xX×\*]\s*(\d+)\s*[元块]?/u']
            ];
        }
    }

    /**
     * The main parsing logic for a single block of text.
     * It now iterates through database-driven templates.
     */
    public function calculateSingle(string $betting_slip_text): ?array {
        $settlement_slip = [
            'number_bets' => [],
            'summary' => ['number_count' => 0, 'total_cost' => 0]
        ];
        $text = $betting_slip_text;

        // Use a loop to find and consume matches one by one, which is more robust.
        $remaining_text = $text;

        foreach ($this->parsing_templates as $template) {
            $pattern = $template['pattern'];
            $type = $template['type'];

            while (preg_match($pattern, $remaining_text, $match)) {
                $full_match_str = $match[0];

                // Process the match based on its type
                $bet_data = null;
                if ($type === 'zodiac') {
                    $bet_data = $this->processZodiacBet($match);
                } elseif ($type === 'number_list') {
                    $bet_data = $this->processNumberListBet($match);
                } elseif ($type === 'multiplier') {
                    $bet_data = $this->processMultiplierBet($match);
                }

                if ($bet_data) {
                    $settlement_slip['number_bets'][] = $bet_data;
                }

                // Remove the processed part from the original string and continue the loop.
                $pos = strpos($remaining_text, $full_match_str);
                if ($pos !== false) {
                    $remaining_text = substr_replace($remaining_text, '', $pos, strlen($full_match_str));
                } else {
                    break; // Safeguard
                }
            }
        }
        $text = $remaining_text;

        // Final Calculation
        $total_cost = 0;
        $total_number_count = 0;
        foreach ($settlement_slip['number_bets'] as $bet) {
            $total_cost += $bet['cost'];
            $total_number_count += count($bet['numbers']);
        }

        if ($total_number_count === 0) return null;

        $settlement_slip['summary']['number_count'] = $total_number_count;
        $settlement_slip['summary']['total_cost'] = $total_cost;
        $settlement_slip['unparsed_text'] = trim($text);
        return $settlement_slip;
    }

    // --- Processing helper methods for each bet type ---

    private function processZodiacBet($match) {
        $zodiac_string_with_commas = trim($match[1], " \t\n\r\0\x0B,，");
        $zodiac_string = preg_replace('/[,，\s]/u', '', $zodiac_string_with_commas);
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
                return [
                    'numbers' => $unique_numbers, 'cost_per_number' => $cost_per_number, 'cost' => count($unique_numbers) * $cost_per_number, 'source_zodiacs' => $zodiacs
                ];
            }
        }
        return null;
    }

    private function processNumberListBet($match) {
        $numbers = preg_split('/[.,，、\s-]+/', $match[1], -1, PREG_SPLIT_NO_EMPTY);
        $cost = intval($match[2]);
        if (!empty($numbers) && $cost > 0) {
            return [ 'numbers' => $numbers, 'cost_per_number' => $cost, 'cost' => count($numbers) * $cost ];
        }
        return null;
    }

    private function processMultiplierBet($match) {
        $cost = intval($match[2]);
        if ($cost > 0) {
            return [ 'numbers' => [$match[1]], 'cost_per_number' => $cost, 'cost' => $cost ];
        }
        return null;
    }

    // --- Static methods (unchanged) ---

    public static function calculateMulti(PDO $pdo, string $full_text): ?array {
        $calculator = new self($pdo);
        $all_slips = [];
        // ... (rest of the multi-slip logic, but now it uses the instance)
        // This part needs to be refactored to use the new class instance method.
        // For simplicity, we assume calculateSingle will be called externally on text blocks.
        // The logic below is a placeholder for a more complete refactoring.
        $sub_blocks = preg_split('/\n{2,}/u', $full_text, -1, PREG_SPLIT_NO_EMPTY);
        foreach ($sub_blocks as $sub_block) {
            $r = $calculator->calculateSingle(trim($sub_block));
            if ($r) $all_slips[] = ['raw' => trim($sub_block), 'result' => $r];
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

    public static function settle(array $bill_details, array $lottery_results_map): array {
        $settled_details = $bill_details;
        $total_winnings = 0;
        $winning_rate = 45;
        $hk_numbers = $lottery_results_map['香港'] ?? null;
        $nm_numbers = $lottery_results_map['新澳门'] ?? null;

        foreach ($settled_details['slips'] as &$slip) {
            $region = $slip['region'] ?? '';
            $winning_numbers = (strpos($region, '香港') !== false || strpos($region, '港') !== false) ? $hk_numbers : $nm_numbers;
            if ($winning_numbers === null) continue;
            $slip_winnings = 0;
            if (isset($slip['result']['number_bets'])) {
                foreach ($slip['result']['number_bets'] as &$bet) {
                    $bet['winning_numbers'] = [];
                    $bet['winnings'] = 0;
                    if (isset($bet['cost_per_number'])) {
                        foreach ($bet['numbers'] as $number) {
                            if (in_array($number, $winning_numbers)) {
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
        $settled_details['summary']['total_winnings'] = $total_winnings;
        return $settled_details;
    }
}
?>