<?php

class LotteryParser {

    /**
     * Parses a text message to find lottery results.
     *
     * @param string $text The text of the message.
     * @return array|null An array with the parsed data, or null if no match.
     */
    public static function parse($text) {
        // Use the hardcoded, robust regex for parsing.
        $pattern = '/(新澳门六合彩|老澳\d{2}\.\d{2}|香港六合彩)\s*第:?\s*(\d+)\s*期(.*)/u';

        if (preg_match($pattern, $text, $matches)) {
            $lotteryName = trim($matches[1]);
            $issueNumber = trim($matches[2]);
            $remainingText = trim($matches[3]); // This contains the numbers part

            // Robustly extract all numbers from the remaining text.
            preg_match_all('/\d+/', $remainingText, $numberMatches);
            $numbers = !empty($numberMatches[0]) ? $numberMatches[0] : [];

            // Basic validation: A valid result must contain exactly 7 numbers.
            if (count($numbers) === 7) {
                // Ensure numbers are zero-padded (e.g., 1 -> 01)
                $formattedNumbers = array_map(fn($num) => str_pad($num, 2, '0', STR_PAD_LEFT), $numbers);

                return [
                    'lottery_name' => $lotteryName,
                    'issue_number' => $issueNumber,
                    'numbers' => $formattedNumbers,
                ];
            } else {
                // Log failure if a potential lottery message was detected but didn't have 7 numbers.
                error_log("LotteryParser: Expected 7 numbers, but found " . count($numbers) . " in text: \"$text\"");
            }
        }

        return null;
    }
}