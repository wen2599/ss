<?php

namespace App\Lib;

class LotteryParser {

    /**
     * Parses a text message to find lottery results.
     * The regex is designed to be flexible and handle variations in spacing and punctuation.
     *
     * @param string $text The text of the message.
     * @return array|null An array with the parsed data, or null if no match.
     */
    public static function parse(string $text): ?array
    {
        // Updated pattern to be more precise about capturing the winning numbers group.
        // It now expects numbers to be separated by spaces or common delimiters like '+' or ':'.
        // The numbers themselves are expected to be 1 to 2 digits.
        $pattern = '/
            # Lottery Name (Group 1)
            (新澳门六合彩|香港六合彩|老澳\d{2}\.\d{2})
            
            # "第" character followed by optional spaces and colon
            \s*第\s*[:：]?\s*
            
            # Issue Number (Group 2) - captures one or more digits
            (\d+)
            
            # "期开奖结果" text with optional spaces and colon
            \s*期\s*开奖结果\s*[:：]?\s*
            
            # Winning Numbers (Group 3) - captures sequences of 1 or 2 digits separated by non-digit/non-space characters
            # We use a non-greedy match for the numbers themselves [\d\s]+ to capture them effectively.
            (
                (?:\d{1,2}\s*[+:\s]*){6,7}\d{1,2} # Expecting around 6 to 7 numbers, 1 or 2 digits each
            )
        /ux'; // u: for UTF-8 matching, x: for extended mode (comments and whitespace)

        $matches = [];
        if (preg_match($pattern, $text, $matches)) {
            $lotteryName = trim($matches[1]);
            $issueNumber = trim($matches[2]);

            // Extract and clean the numbers string
            $numbersStr = trim($matches[3]);
            
            // Find all sequences of 1 or 2 digits and collect them
            preg_match_all('/\d{1,2}/', $numbersStr, $numberMatches);
            $numbers = array_map('intval', $numberMatches[0]);

            // Validate number count: expect 7 numbers for Six Marks Lottery (6 main + 1 special)
            if (count($numbers) !== 7) {
                // Log an error if the number count is unexpected, but still return parsed data if available.
                error_log("LotteryParser: Expected 7 numbers, but found " . count($numbers) . " in text: " . $text);
                // Depending on strictness, you might return null here or proceed with what was found.
                // For now, we proceed to allow some flexibility, but it's flagged.
            }
            
            // Assign main and special numbers (assuming the last one is special)
            $mainNumbers = array_slice($numbers, 0, 6);
            $specialNumber = $numbers[6] ?? null; // Handle cases where not enough numbers were found

            return [
                'lottery_name' => $lotteryName,
                'issue_number' => $issueNumber,
                'main_numbers' => $mainNumbers,
                'special_number' => $specialNumber,
                'raw_numbers' => $numbers, // Keep raw numbers for debugging/flexibility
            ];
        }

        return null;
    }
}
