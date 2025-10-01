<?php

namespace App\\Lib;

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
        // This single, more robust regex pattern handles:
        // - Different lottery names ("新澳门六合彩", "香港六合彩", "老澳" followed by time).
        // - Both half-width ":" and full-width "：" colons.
        // - Flexible spacing between elements.
        // - Capturing the sequence of numbers at the end.
        $pattern = '/
            # Lottery Name (Group 1)
            (新澳门六合彩|香港六合彩|老澳\d{2}\.\d{2})
            
            # "第" character followed by optional spaces
            \s*第\s*[:：]?\s*
            
            # Issue Number (Group 2) - captures one or more digits
            (\d+)
            
            # "期开奖结果" text with optional spaces
            \s*期\s*开奖结果\s*[:：]?\s*
            
            # Winning Numbers (Group 3) - captures digits and spaces
            (
                [\d\s]+
            )
        /ux'; // u: for UTF-8 matching, x: for extended mode (comments and whitespace)

        $matches = [];
        if (preg_match($pattern, $text, $matches)) {
            $lotteryName = trim($matches[1]);
            $issueNumber = trim($matches[2]);

            // Extract and clean the numbers string
            $numbersStr = trim($matches[3]);
            
            // Find all sequences of digits and collect them
            preg_match_all('/\d+/', $numbersStr, $numberMatches);
            $numbers = $numberMatches[0];

            // Basic validation: ensure we have at least one number
            if (empty($numbers)) {
                return null;
            }

            return [
                'lottery_name' => $lotteryName,
                'issue_number' => $issueNumber,
                'numbers' => $numbers,
            ];
        }

        return null;
    }
}
