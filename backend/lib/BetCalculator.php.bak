<?php

namespace App\\Lib;

use PDO;
use Exception;
use Monolog\\Logger;

class BetCalculator {
    private PDO $pdo;
    private Logger $logger;
    private ?int $userId;
    private array $parsingTemplates;

    public function __construct(PDO $pdo, Logger $logger, ?int $userId = null) {
        $this->pdo = $pdo;
        $this->logger = $logger;
        $this->userId = $userId;
        $this->loadParsingTemplates();
    }

    /**
     * Loads parsing templates from the database, prioritizing user-specific templates.
     * Falls back to global templates, and then to hardcoded defaults if necessary.
     */
    private function loadParsingTemplates(): void {
        try {
            // First, attempt to load user-specific templates if a user ID is provided.
            if ($this->userId !== null) {
                $sql = "SELECT pattern, type FROM parsing_templates WHERE user_id = :user_id ORDER BY priority ASC";
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute([':user_id' => $this->userId]);
                $userTemplates = $stmt->fetchAll(PDO::FETCH_ASSOC);

                if (!empty($userTemplates)) {
                    $this->parsingTemplates = $userTemplates;
                    $this->logger->info("Loaded " . count($userTemplates) . " user-specific parsing templates for user_id: {$this->userId}.");
                    return;
                }
            }

            // If no user templates, load global templates (where user_id IS NULL).
            $sql = "SELECT pattern, type FROM parsing_templates WHERE user_id IS NULL ORDER BY priority ASC";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute();
            $globalTemplates = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (!empty($globalTemplates)) {
                $this->parsingTemplates = $globalTemplates;
                $this->logger->info("Loaded " . count($globalTemplates) . " global parsing templates.");
                return;
            }
            
            throw new Exception("No parsing templates found in the database for this user or globally.");

        } catch (Exception $e) {
            $this->logger->warning("Could not load parsing templates from DB. Reason: " . $e->getMessage() . ". Falling back to hardcoded default templates.");
            // Fallback to hardcoded templates if DB query fails or no templates exist.
            $this->parsingTemplates = [
                ['type' => 'zodiac', 'pattern' => '/([\\p{Han},，\\s]+?)(?:数各|各数)\\s*([\\p{Han}\\d]+)\\s*[元块]?/u'],
                ['type' => 'number_list', 'pattern' => '/([0-9.,，、\\s-]+)各\\s*(\\d+)\\s*(?:#|[元块])/u'],
                ['type' => 'multiplier', 'pattern' => '/(\\d+)\\s*[xX×\\*]\\s*(\\d+)\\s*[元块]?/u']
            ];
        }
    }

    /**
     * Parses a single, continuous block of betting text.
     */
    public function calculateSingle(string $betting_slip_text): ?array {
        $settlement_slip = ['number_bets' => []];
        $remaining_text = $betting_slip_text;

        foreach ($this->parsingTemplates as $template) {
            $pattern = $template['pattern'];
            $type = $template['type'];

            while (preg_match($pattern, $remaining_text, $match)) {
                $bet_data = null;

                switch ($type) {
                    case 'zodiac':
                        $bet_data = $this->processZodiacBet($match);
                        break;
                    case 'number_list':
                        $bet_data = $this->processNumberListBet($match);
                        break;
                    case 'multiplier':
                        $bet_data = $this->processMultiplierBet($match);
                        break;
                }

                if ($bet_data) {
                    $settlement_slip['number_bets'][] = $bet_data;
                }

                // Safely consume the matched part from the text using preg_replace with a limit of 1.
                // This prevents issues if the matched string appears multiple times.
                $remaining_text = preg_replace('/' . preg_quote($match[0], '/') . '/', '', $remaining_text, 1);
            }
        }

        // Final summary calculation for the single slip
        $total_cost = 0;
        $total_number_count = 0;
        foreach ($settlement_slip['number_bets'] as $bet) {
            $total_cost += $bet['cost'];
            $total_number_count += count($bet['numbers']);
        }

        if ($total_number_count === 0) {
            return null; // Return null if no valid bets were parsed
        }

        $settlement_slip['summary'] = [
            'number_count' => $total_number_count,
            'total_cost' => $total_cost
        ];
        $settlement_slip['unparsed_text'] = trim($remaining_text);
        
        return $settlement_slip;
    }
    
    /**
     * Parses a full text which may contain multiple betting slips separated by headers or newlines.
     */
    public function calculateMulti(string $full_text): ?array {
        $all_slips = [];
        $full_text = trim(str_replace("\r\n", "\n", $full_text));
        if (empty($full_text)) return null;

        // This regex splits the text by lines that act as headers (e.g., "香港", "1.", etc.)
        // It captures the headers, allowing us to associate content with the preceding header.
        $header_pattern = '/(^(?:香港|新澳门|澳门|港|(?:\d+\.|\d+、)).*)/mu';
        $parts = preg_split($header_pattern, $full_text, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);

        $current_region = '新澳门'; // Default region
        $content_block = '';

        // Handle case where the text does not start with a header.
        if (count($parts) > 0 && !preg_match($header_pattern, $parts[0])) {
            $content_block = array_shift($parts);
            $this->processContentBlock($content_block, $current_region, $all_slips);
        }
        
        // Process the remaining parts, which should be in [header, content, header, content...] sequence.
        for ($i = 0; $i < count($parts); $i += 2) {
            $header = trim($parts[$i]);
            $content_block = isset($parts[$i + 1]) ? trim($parts[$i + 1]) : '';

            // Update region based on the header content.
            if (str_contains($header, '香港') || str_contains($header, '港')) {
                $current_region = '香港';
            } elseif (str_contains($header, '新澳门') || str_contains($header, '澳门')) {
                $current_region = '新澳门';
            }
            
            $this->processContentBlock($content_block, $current_region, $all_slips);
        }

        if (empty($all_slips)) return null;

        // Add index and calculate final summary
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

    /**
     * Helper function to process a block of content and add parsed slips to the main array.
     */
    private function processContentBlock(string $content_block, string $region, array &$all_slips): void {
        if (empty(trim($content_block))) return;
        
        // Split content block into individual slips, separated by one or more blank lines.
        $sub_slips_text = preg_split('/\n{2,}/u', trim($content_block), -1, PREG_SPLIT_NO_EMPTY);
        foreach ($sub_slips_text as $sub_slip_text) {
            $result = $this->calculateSingle(trim($sub_slip_text));
            if ($result && !empty($result['number_bets'])) {
                $all_slips[] = [
                    'raw' => trim($sub_slip_text), 
                    'result' => $result, 
                    'region' => $region
                ];
            }
        }
    }
    
    /**
     * Settles the bill based on lottery results and user-specific winning rate.
     */
    public static function settle(array $bill_details, array $lottery_results_map, float $user_winning_rate): array {
        $settled_details = $bill_details;
        $total_winnings = 0;

        $hk_numbers = $lottery_results_map['香港'] ?? null;
        $nm_numbers = $lottery_results_map['新澳门'] ?? null;

        foreach ($settled_details['slips'] as &$slip) {
            $region = $slip['region'] ?? '';
            $winning_numbers = (str_contains($region, '香港') || str_contains($region, '港')) ? $hk_numbers : $nm_numbers;
            
            $slip['result']['summary']['winnings'] = 0;
            if ($winning_numbers === null) continue;

            $slip_winnings = 0;
            if (isset($slip['result']['number_bets'])) {
                foreach ($slip['result']['number_bets'] as &$bet) {
                    $bet['winnings'] = 0;
                    $bet['winning_numbers'] = [];
                    foreach ($bet['numbers'] as $number) {
                        if (in_array(ltrim($number, '0'), $winning_numbers)) {
                            $win_amount = $bet['cost_per_number'] * $user_winning_rate;
                            $bet['winnings'] += $win_amount;
                            $bet['winning_numbers'][] = $number;
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

    // --- Processing helper methods for each bet type ---

    private function processZodiacBet(array $match): ?array {
        $zodiac_string_with_commas = trim($match[1], " \t\n\r\0\x0B,，");
        $zodiac_string = preg_replace('/[,，\\s]/u', '', $zodiac_string_with_commas);
        $zodiacs = mb_str_split($zodiac_string);
        $cost_per_number = self::chineseToNumber($match[2]);

        if ($cost_per_number <= 0 || empty($zodiacs)) return null;

        $numbers = [];
        foreach ($zodiacs as $z) {
            $numbers = array_merge($numbers, GameData::getNumbersByZodiac($z));
        }
        $unique_numbers = array_values(array_unique($numbers));

        if (empty($unique_numbers)) return null;

        return [
            'numbers' => $unique_numbers, 
            'cost_per_number' => $cost_per_number, 
            'cost' => count($unique_numbers) * $cost_per_number, 
            'source_zodiacs' => $zodiacs, 
            'type' => 'zodiac'
        ];
    }

    private function processNumberListBet(array $match): ?array {
        $numbers = preg_split('/[.,，、\\s-]+/', $match[1], -1, PREG_SPLIT_NO_EMPTY);
        $cost = (int)$match[2];
        if (empty($numbers) || $cost <= 0) return null;

        return [
            'numbers' => $numbers, 
            'cost_per_number' => $cost, 
            'cost' => count($numbers) * $cost, 
            'type' => 'number_list'
        ];
    }

    private function processMultiplierBet(array $match): ?array {
        $cost = (int)$match[2];
        if ($cost <= 0) return null;

        return [
            'numbers' => [$match[1]], 
            'cost_per_number' => $cost, 
            'cost' => $cost, 
            'type' => 'multiplier'
        ];
    }
    
    /**
     * Converts Chinese number characters (up to 99) or a numeric string to an integer.
     */
    private static function chineseToNumber(string $text): int {
        $text = trim($text);
        if (is_numeric($text)) {
            return (int)$text;
        }

        $map = ['零'=>0, '一'=>1, '二'=>2, '三'=>3, '四'=>4, '五'=>5, '六'=>6, '七'=>7, '八'=>8, '九'=>9, '两'=>2];
        $text = str_replace('两', '二', $text);
        
        $total = 0;
        if (str_contains($text, '十')) {
            $parts = explode('十', $text);
            if ($parts[0] === '') { // Case: "十一" (starts with ten)
                $total = 10;
            } else { // Case: "二十" (starts with a digit)
                $total = ($map[$parts[0]] ?? 0) * 10;
            }
            if (!empty($parts[1])) { // Case: "二十一" (has a digit after ten)
                $total += ($map[$parts[1]] ?? 0);
            }
        } else { // Case: "五" (single digit)
            $total = $map[$text] ?? 0;
        }
        
        return $total;
    }
}
