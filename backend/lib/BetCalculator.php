<?php
require_once __DIR__ . '/GameData.php';

class BetCalculator {
    private $pdo;
    private $userId;
    private $parsingTemplates;

    public function __construct(PDO $pdo, ?int $userId = null) {
        $this->pdo = $pdo;
        $this->userId = $userId;
        $this->loadParsingTemplates();
    }

    /**
     * Loads parsing templates from the database, prioritizing user-specific templates.
     * If no user-specific templates are found, it falls back to global templates.
     * If no templates are found in the database at all, it uses a hardcoded default set.
     */
    private function loadParsingTemplates() {
        try {
            // First, try to load user-specific templates.
            if ($this->userId !== null) {
                $sql = "SELECT pattern, type FROM parsing_templates WHERE user_id = :user_id ORDER BY priority ASC";
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute([':user_id' => $this->userId]);
                $userTemplates = $stmt->fetchAll(PDO::FETCH_ASSOC);

                if (!empty($userTemplates)) {
                    $this->parsingTemplates = $userTemplates;
                    return; // User templates found and loaded, so we are done.
                }
            }

            // If no user-specific templates were found (or if userId is null), load global templates.
            $sql = "SELECT pattern, type FROM parsing_templates WHERE user_id IS NULL ORDER BY priority ASC";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute();
            $this->parsingTemplates = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // If still no templates are found, throw an exception to trigger the fallback.
            if (empty($this->parsingTemplates)) {
                throw new Exception("No user-specific or global templates found in DB, using fallback.");
            }
        } catch (Exception $e) {
            // Fallback to hardcoded templates if the DB query fails or no templates exist.
            error_log("Template loading info: " . $e->getMessage());
            $this->parsingTemplates = [
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

        $remaining_text = $text;

        foreach ($this->parsingTemplates as $template) {
            $pattern = $template['pattern'];
            $type = $template['type'];

            // Use a loop to find and consume all matches for the current pattern.
            while (preg_match($pattern, $remaining_text, $match)) {
                $full_match_str = $match[0];

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

                // Remove the processed part from the string to avoid re-matching.
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
                    'numbers' => $unique_numbers, 'cost_per_number' => $cost_per_number, 'cost' => count($unique_numbers) * $cost_per_number, 'source_zodiacs' => $zodiacs, 'type' => 'zodiac'
                ];
            }
        }
        return null;
    }

    private function processNumberListBet($match) {
        $numbers = preg_split('/[.,，、\s-]+/', $match[1], -1, PREG_SPLIT_NO_EMPTY);
        $cost = intval($match[2]);
        if (!empty($numbers) && $cost > 0) {
            return [ 'numbers' => $numbers, 'cost_per_number' => $cost, 'cost' => count($numbers) * $cost, 'type' => 'number_list' ];
        }
        return null;
    }

    private function processMultiplierBet($match) {
        $cost = intval($match[2]);
        if ($cost > 0) {
            return [ 'numbers' => [$match[1]], 'cost_per_number' => $cost, 'cost' => $cost, 'type' => 'multiplier' ];
        }
        return null;
    }

    // --- Instance and Static methods ---

    public function calculateMulti(string $full_text): ?array {
        $all_slips = [];
        $full_text = trim(str_replace("\r\n", "\n", $full_text));
        if (empty($full_text)) {
            return null;
        }

        // This pattern splits the text by lines that act as headers (region or timestamp)
        // and captures these headers as part of the output array.
        $pattern = '/(^(?:香港|新澳门|澳门|港|(?:\d+\.|\d+、)).*)/mu';
        $parts = preg_split($pattern, $full_text, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);

        $current_region = '新澳门'; // Default region

        $content_block = '';
        if (count($parts) > 0 && !preg_match($pattern, $parts[0])) {
            // The text does not start with a region marker, so the first part is content.
            $content_block = array_shift($parts);
        }

        // Process the initial content block (if any) with the default region
        if (!empty(trim($content_block))) {
            $sub_slips_text = preg_split('/\n{2,}/u', trim($content_block), -1, PREG_SPLIT_NO_EMPTY);
            foreach ($sub_slips_text as $sub_slip_text) {
                $r = $this->calculateSingle(trim($sub_slip_text));
                if ($r && !empty($r['number_bets'])) {
                    $all_slips[] = ['raw' => trim($sub_slip_text), 'result' => $r, 'region' => $current_region];
                }
            }
        }

        // Process the remaining parts, which should be in [header, content, header, content...] sequence.
        for ($i = 0; $i < count($parts); $i += 2) {
            $header = trim($parts[$i]);
            $content_block = isset($parts[$i + 1]) ? trim($parts[$i + 1]) : '';

            // Determine region from the header
            if (strpos($header, '香港') !== false || strpos($header, '港') !== false) {
                $current_region = '香港';
            } elseif (strpos($header, '新澳门') !== false || strpos($header, '澳门') !== false) {
                $current_region = '新澳门';
            }
            // If it's just a numbered header, the region from the previous block is inherited.

            if (!empty(trim($content_block))) {
                $sub_slips_text = preg_split('/\n{2,}/u', $content_block, -1, PREG_SPLIT_NO_EMPTY);
                foreach ($sub_slips_text as $sub_slip_text) {
                    $r = $this->calculateSingle(trim($sub_slip_text));
                    if ($r && !empty($r['number_bets'])) {
                        $all_slips[] = ['raw' => trim($sub_slip_text), 'result' => $r, 'region' => $current_region];
                    }
                }
            }
        }

        if (empty($all_slips)) return null;

        // Final summary calculation
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

    public static function settle(array $bill_details, array $lottery_results_map, float $user_winning_rate): array {
        $settled_details = $bill_details;
        $total_winnings = 0;

        $hk_numbers = $lottery_results_map['香港'] ?? null;
        $nm_numbers = $lottery_results_map['新澳门'] ?? null;

        foreach ($settled_details['slips'] as &$slip) {
            $region = $slip['region'] ?? '';
            $winning_numbers = (strpos($region, '香港') !== false || strpos($region, '港') !== false) ? $hk_numbers : $nm_numbers;
            if ($winning_numbers === null) continue;

            $slip_winnings = 0;
            if (isset($slip['result']['number_bets'])) {
                foreach ($slip['result']['number_bets'] as &$bet) {
                    $bet['winnings'] = 0;
                    $bet['winning_numbers'] = []; // Initialize array to store winning numbers for this bet
                    if (isset($bet['cost_per_number'])) {
                        foreach ($bet['numbers'] as $number) {
                            if (in_array($number, $winning_numbers)) {
                                $bet['winnings'] += $bet['cost_per_number'] * $user_winning_rate;
                                $bet['winning_numbers'][] = $number; // Add the winning number to the list
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