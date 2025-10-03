<?php

namespace App\Lib;

/**
 * Class LotteryParser
 *
 * A static utility class for parsing lottery result announcements from raw text.
 */
class LotteryParser {

    /**
     * Parses a text message to find and extract lottery result details.
     *
     * The regex is designed to be flexible and handle variations in spacing and punctuation.
     * It looks for a known lottery name, an issue number, and a sequence of winning numbers.
     *
     * @param string $text The text of the message to parse.
     * @return array|null An associative array with the parsed data on success, or null if no match is found.
     *                    The returned array has the following structure:
     *                    [
     *                        'lottery_name' => string,
     *                        'issue_number' => string,
     *                        'main_numbers' => array,
     *                        'special_number' => int|null,
     *                        'raw_numbers' => array
     *                    ]
     */
    public static function parse(string $text): ?array
    {
        global $log; // Use the global logger from init.php

        // This pattern identifies the lottery name, issue number, and the block of winning numbers.
        $pattern = '/
            # Lottery Name (Group 1)
            (新澳门六合彩|香港六合彩|老澳\d{2}\.\d{2})
            
            # "第" character followed by optional spaces and colon
            \s*第\s*[:：]?\s*
            
            # Issue Number (Group 2)
            (\d+)
            
            # "期开奖结果" text with optional spaces and colon
            \s*期\s*开奖结果\s*[:：]?\s*
            
            # Winning Numbers (Group 3) - Captures sequences of 1-2 digits, separated by various symbols.
            (
                (?:\d{1,2}\s*[,+:\s]*){6,7}\d{1,2}
            )
        /ux'; // u: for UTF-8 matching, x: for extended mode (comments and whitespace)

        if (preg_match($pattern, $text, $matches)) {
            $lotteryName = trim($matches[1]);
            $issueNumber = trim($matches[2]);
            $numbersStr = trim($matches[3]);
            
            // Extract all 1 or 2-digit numbers from the captured number string.
            preg_match_all('/\d{1,2}/', $numbersStr, $numberMatches);
            $numbers = array_map('intval', $numberMatches[0]);

            // Validate the number count. A standard result should have 7 numbers.
            // If not, log a warning but proceed leniently to handle potential format variations.
            if (count($numbers) !== 7) {
                $log->warning("LotteryParser found an unexpected number of lottery balls.", [
                    'expected' => 7,
                    'found' => count($numbers),
                    'text' => $text
                ]);
            }
            
            // Assign main and special numbers, assuming the last one is the special number.
            $mainNumbers = array_slice($numbers, 0, 6);
            $specialNumber = $numbers[6] ?? null; // Null-coalescing handles cases with fewer than 7 numbers.

            return [
                'lottery_name' => $lotteryName,
                'issue_number' => $issueNumber,
                'main_numbers' => $mainNumbers,
                'special_number' => $specialNumber,
                'raw_numbers' => $numbers, // Keep all parsed numbers for debugging or other uses.
            ];
        }

        // Return null if the text does not match the lottery result pattern.
        return null;
    }
}
?>