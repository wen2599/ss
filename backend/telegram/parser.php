<?php // backend/telegram/parser.php
function parse_lottery_data($text) {
    $pattern = '/(新澳门六合彩|香港六合彩|老澳.*?)第:(\d+)期开奖结果:\s*([\d\s]+)\s*([\p{L}\s]+)\s*([^\n\r]+)/u';
    if (preg_match($pattern, $text, $matches)) {
        return [
            'lottery_type' => (strpos($matches[1], '老澳') !== false) ? '老澳门六合彩' : trim($matches[1]),
            'issue_number' => $matches[2],
            'winning_numbers' => preg_split('/\s+/', trim($matches[3])),
            'zodiac_signs' => preg_split('/\s+/', trim($matches[4])),
            'colors' => preg_split('/\s+/', trim($matches[5])),
            'drawing_date' => date('Y-m-d')
        ];
    }
    return null;
}