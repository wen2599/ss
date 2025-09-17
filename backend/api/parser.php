<?php
// backend/api/parser.php

/**
 * Parses a string containing lottery bet information based on rules defined in the database.
 *
 * @param string $inputText The raw text input containing bets.
 * @param PDO $pdo A connected PDO instance.
 * @return array An array of parsed bet objects.
 */
function parseBets(string $inputText, PDO $pdo): array
{
    // 1. Fetch rules from the database
    // This is essential for mapping Zodiacs and Colors to numbers.
    // The Admin UI (to be built) will populate these rules.
    $stmt = $pdo->query("SELECT rule_key, rule_value FROM lottery_rules");
    $rules_raw = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $rules = [];
    foreach ($rules_raw as $row) {
        $rules[$row['rule_key']] = json_decode($row['rule_value'], true);
    }
    $zodiac_mappings = $rules['zodiac_mappings'] ?? [];
    $color_mappings = $rules['color_mappings'] ?? [];

    // 2. Process the input string
    $bets = [];
    // Split bets by spaces, commas, or semicolons for flexibility
    $bet_chunks = preg_split('/[\s,;]+/', $inputText, -1, PREG_SPLIT_NO_EMPTY);

    foreach ($bet_chunks as $chunk) {
        // Pattern for Special Number bets, e.g., "特15x100" or "特15 100"
        if (preg_match('/^特(\d+)[\sx*](\d+)$/u', $chunk, $matches)) {
            $bets[] = [
                'type' => 'special',
                'number' => $matches[1],
                'amount' => (int)$matches[2],
                'display_name' => '特码'
            ];
            continue;
        }

        // Pattern for "each number" bets, e.g., "鸡狗猴各数50"
        if (preg_match('/^([\p{Han}]+)各数(\d+)$/u', $chunk, $matches)) {
            $items_str = $matches[1];
            $amount = (int)$matches[2];
            $items = preg_split('//u', $items_str, -1, PREG_SPLIT_NO_EMPTY);

            foreach ($items as $item) {
                if (isset($zodiac_mappings[$item])) {
                    $bets[] = [
                        'type' => 'zodiac',
                        'name' => $item,
                        'numbers' => $zodiac_mappings[$item],
                        'amount' => $amount,
                        'display_name' => '生肖'
                    ];
                }
            }
            continue;
        }

        // Pattern for "each wave/color" bets, e.g., "红波各10"
        if (preg_match('/^([\p{Han}]+波)各(\d+)$/u', $chunk, $matches)) {
            $item = $matches[1];
            $amount = (int)$matches[2];
            if (isset($color_mappings[$item])) {
                $bets[] = [
                    'type' => 'color',
                    'name' => $item,
                    'numbers' => $color_mappings[$item],
                    'amount' => $amount,
                    'display_name' => '波色'
                ];
            }
            continue;
        }
    }

    return $bets;
}
