<?php
// backend/api/parser.php

/**
 * Parses a string containing lottery bet information based on rules defined in the database.
 *
 * This refactored version iteratively processes the string to avoid issues with
 * greedy regex and ordering of bet types in the input.
 *
 * @param string $inputText The raw text input containing bets.
 * @param PDO $pdo A connected PDO instance.
 * @return array An array of parsed bet objects.
 */
function parseBets(string $inputText, $pdo): array
{
    // This is the centralized function for parsing bet strings.
    // 1. Fetch rules from the database
    $stmt = $pdo->query("SELECT rule_key, rule_value FROM lottery_rules");
    $rules_raw = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $rules = [];
    foreach ($rules_raw as $row) {
        $rules[$row['rule_key']] = json_decode($row['rule_value'], true);
    }
    $zodiac_mappings = $rules['zodiac_mappings'] ?? [];
    $color_mappings = $rules['color_mappings'] ?? [];

    // 2. Iteratively process the input string
    $bets = [];
    $remainingText = trim($inputText);

    // Define the patterns for different bet types. Order can matter if patterns are ambiguous.
    $patterns = [
        'special' => '/^特(\d+)[\sx*](\d+)/u',
        // Updated to be more specific for Zodiacs (e.g., "鸡狗猴") vs. Colors ("红波")
        'zodiac' => '/^((?:[\p{Han}](?!波))+?)各数(\d+)/u',
        'color' => '/^([\p{Han}]+波)各(\d+)/u',
    ];

    while (strlen($remainingText) > 0) {
        $matchFound = false;

        foreach ($patterns as $type => $pattern) {
            if (preg_match($pattern, $remainingText, $matches)) {
                $matchFound = true;
                $fullMatch = $matches[0];

                switch ($type) {
                    case 'special':
                        $bets[] = [
                            'type' => 'special',
                            'number' => $matches[1],
                            'amount' => (int)$matches[2],
                            'display_name' => '特码'
                        ];
                        break;

                    case 'zodiac':
                        $items_str = $matches[1];
                        $amount = (int)$matches[2];
                        // Split the multi-character Zodiac string into individual characters
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
                        break;

                    case 'color':
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
                        break;
                }

                // Remove the matched part and any following separators from the string
                $remainingText = trim(substr($remainingText, strlen($fullMatch)));
                $remainingText = ltrim($remainingText, " \t\n\r\0\x0B,;");

                // Break the inner loop and start again from the beginning of the patterns
                break;
            }
        }

        // If no pattern matched, it means there's unparseable text.
        // To prevent an infinite loop, we stop processing.
        if (!$matchFound) {
            // Optional: Log the unparseable text for debugging
            // error_log("Unparseable text remaining in parser: " . $remainingText);
            break;
        }
    }

    return $bets;
}
