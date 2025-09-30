<?php

class LotteryParser {

    /**
     * Parses a text message to find lottery results, using dynamic templates first.
     *
     * @param string $text The text of the message.
     * @param PDO $pdo The database connection object.
     * @return array|null An array with the parsed data, or null if no match.
     */
    public static function parse($text, PDO $pdo) {
        // 1. Try to parse using custom templates from the database
        try {
            $stmt = $pdo->query("SELECT pattern FROM parsing_templates WHERE type = 'lottery_result' ORDER BY priority ASC, id ASC");
            $templates = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($templates as $template) {
                $pattern = $template['pattern'];
                if (@preg_match($pattern, $text, $matches)) {
                    // Custom templates are expected to capture 3 groups: name, issue, and numbers string.
                    if (count($matches) === 4) {
                        $lotteryName = trim($matches[1]);
                        $issueNumber = trim($matches[2]);
                        $numbersStr = trim($matches[3]);

                        // Re-use the robust number extraction logic
                        preg_match_all('/\d+/', $numbersStr, $numberMatches);
                        $numbers = !empty($numberMatches[0]) ? $numberMatches[0] : [];

                        if (count($numbers) === 7) {
                            $formattedNumbers = array_map(fn($num) => str_pad($num, 2, '0', STR_PAD_LEFT), $numbers);
                            return [
                                'lottery_name' => $lotteryName,
                                'issue_number' => $issueNumber,
                                'numbers' => $formattedNumbers,
                            ];
                        }
                    }
                }
            }
        } catch (PDOException $e) {
            error_log("LotteryParser: Database error fetching templates: " . $e->getMessage());
            // Proceed to fallback on DB error
        }

        // 2. Fallback to the hardcoded, robust regex if no custom template matched
        $fallback_pattern = '/(新澳门六合彩|老澳\d{2}\.\d{2}|香港六合彩)\s*第:?\s*(\d+)\s*期(.*)/u';

        if (preg_match($fallback_pattern, $text, $matches)) {
            $lotteryName = trim($matches[1]);
            $issueNumber = trim($matches[2]);
            $remainingText = trim($matches[3]); // This contains the numbers part

            preg_match_all('/\d+/', $remainingText, $numberMatches);
            $numbers = !empty($numberMatches[0]) ? $numberMatches[0] : [];

            if (count($numbers) === 7) {
                $formattedNumbers = array_map(fn($num) => str_pad($num, 2, '0', STR_PAD_LEFT), $numbers);
                return [
                    'lottery_name' => $lotteryName,
                    'issue_number' => $issueNumber,
                    'numbers' => $formattedNumbers,
                ];
            }
        }

        // Log failure if a potential lottery message was detected but didn't have 7 numbers.
        if (isset($numbers) && count($numbers) !== 7) {
             error_log("LotteryParser: Expected 7 numbers, but found " . count($numbers) . " in text: \"$text\"");
        }


        return null;
    }
}