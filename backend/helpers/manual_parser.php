<?php
// File: backend/helpers/manual_parser.php (优化聚合显示)

/**
 * 手动解析下注信息 - 聚合相同金额的下注
 */
function parseBetManually(string $text): array {
    $bets = [];
    $totalAmount = 0;

    // 清理文本
    $text = preg_replace('/\s+/', ' ', $text);
    $text = trim($text);

    // 1. 解析混合下注格式: "澳门、40×10元、39、30、各5元、香港、40×10元、02、04、09、45、各5元"
    if (preg_match('/澳门[，,].*香港[，,]/u', $text)) {
        // 解析澳门部分
        if (preg_match('/澳门[，,]\s*([^香港]+)香港/u', $text, $matches)) {
            $macau_part = $matches[1];
            parseMixedBetPart($macau_part, '澳门六合彩', $bets, $totalAmount);
        }

        // 解析香港部分
        if (preg_match('/香港[，,]\s*(.+)$/u', $text, $matches)) {
            $hk_part = $matches[1];
            parseMixedBetPart($hk_part, '香港六合彩', $bets, $totalAmount);
        }
    }
    // 2. 解析纯澳门下注 - 优化点号分隔格式
    else if (preg_match('/澳门[，,:]\s*([\d.]+)各(\d+)(块|元)/u', $text, $matches)) {
        $numbers_text = $matches[1];
        $amount = intval($matches[2]);

        $numbers = explode('.', $numbers_text);
        $numbers = array_filter($numbers, function($num) {
            return !empty(trim($num));
        });

        if (!empty($numbers)) {
            $bets[] = [
                'bet_type' => '特码',
                'targets' => $numbers,
                'amount' => $amount,
                'total_bet' => $amount * count($numbers),
                'raw_text' => "澳门号码" . implode('.', $numbers) . "各{$amount}块",
                'lottery_type' => '澳门六合彩'
            ];
            $totalAmount += $amount * count($numbers);
        }
    }
    // 3. 解析纯香港下注 - 优化点号分隔格式
    else if (preg_match('/香港[，,:]\s*([\d.]+)各(\d+)(块|元)/u', $text, $matches)) {
        $numbers_text = $matches[1];
        $amount = intval($matches[2]);

        $numbers = explode('.', $numbers_text);
        $numbers = array_filter($numbers, function($num) {
            return !empty(trim($num));
        });

        if (!empty($numbers)) {
            $bets[] = [
                'bet_type' => '特码',
                'targets' => $numbers,
                'amount' => $amount,
                'total_bet' => $amount * count($numbers),
                'raw_text' => "香港号码" . implode('.', $numbers) . "各{$amount}块",
                'lottery_type' => '香港六合彩'
            ];
            $totalAmount += $amount * count($numbers);
        }
    }
    // 4. 解析六肖下注
    else if (preg_match('/([鼠牛虎兔龙蛇马羊猴鸡狗猪]{2,})肖\s*(\d+)\s*闷/u', $text, $matches)) {
        $zodiacs = preg_split('//u', $matches[1], -1, PREG_SPLIT_NO_EMPTY);
        $amount = intval($matches[2]);

        if (count($zodiacs) >= 2) {
            $bets[] = [
                'bet_type' => '六肖',
                'targets' => $zodiacs,
                'amount' => $amount,
                'total_bet' => $amount, // 六肖只算一次下注
                'raw_text' => implode('', $zodiacs) . "肖{$amount}闷",
                'lottery_type' => '混合'
            ];
            $totalAmount += $amount;
        }
    }
    // 5. 解析其他格式...

    return [
        'lottery_type' => count($bets) > 0 ? '混合' : '未知',
        'issue_number' => '',
        'bets' => $bets,
        'total_amount' => $totalAmount
    ];
}
