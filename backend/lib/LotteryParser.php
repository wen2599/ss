<?php

class LotteryParser {

    /**
     * Parses a text message to find lottery results.
     *
     * @param string $text The text of the message.
     * @return array|null An array with the parsed data, or null if no match.
     */
    public static function parse($text) {
        // Regex for "新澳门六合彩" and "老澳21.30" formats
        $pattern1 = '/(新澳门六合彩|老澳\d{2}\.\d{2})第:(\d+)期开奖结果:\s*([\d\s]+)/u';
        // Regex for "香港六合彩" format
        $pattern2 = '/(香港六合彩)第:(\d+)期开奖结果:\s*([\d\s]+)/u';

        $matches = [];
        if (preg_match($pattern1, $text, $matches) || preg_match($pattern2, $text, $matches)) {
            $lotteryName = trim($matches[1]);
            $issueNumber = trim($matches[2]);

            // Extract and clean the numbers
            $numbersStr = trim($matches[3]);
            $numbers = preg_split('/\s+/', $numbersStr);

            // The subsequent lines for zodiacs and colors are not parsed here,
            // as they can be derived from the numbers using GameData.
            // This parser focuses on the core result data.

            return [
                'lottery_name' => $lotteryName,
                'issue_number' => $issueNumber,
                'numbers' => $numbers,
            ];
        }

        return null;
    }
}
