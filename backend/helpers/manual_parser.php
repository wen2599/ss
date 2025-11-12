<?php
// File: backend/helpers/manual_parser.php (优化版)

/**
 * 手动解析下注信息 - 扩展版支持更多玩法
 */
function parseBetManually(string $text): array {
    $bets = [];
    $totalAmount = 0;
    
    // 清理文本
    $text = preg_replace('/\s+/', ' ', $text);
    $text = trim($text);

    // 1. 解析澳门下注格式: "澳门、40×10元、39、30、各5元"
    if (preg_match('/澳门[，,]\s*([^香港]+)(?:香港|$)/u', $text, $matches)) {
        $macau_part = $matches[1];
        
        // 解析 "40×10元" 这种格式
        if (preg_match_all('/(\d+)[×x](\d+)元/u', $macau_part, $number_matches, PREG_SET_ORDER)) {
            foreach ($number_matches as $match) {
                $number = $match[1];
                $amount = intval($match[2]);
                
                $bets[] = [
                    'bet_type' => '特码',
                    'targets' => [$number],
                    'amount' => $amount,
                    'raw_text' => "澳门{$number}×{$amount}元",
                    'lottery_type' => '澳门六合彩'
                ];
                $totalAmount += $amount;
            }
        }
        
        // 解析 "39、30、各5元" 这种格式
        if (preg_match_all('/(\d+)(?:[，、]|$)/u', $macau_part, $number_matches)) {
            $numbers = $number_matches[1];
            if (preg_match('/各\s*(\d+)元/u', $macau_part, $amount_match)) {
                $amount = intval($amount_match[1]);
                foreach ($numbers as $number) {
                    if (strlen($number) >= 2) { // 确保是号码不是金额
                        $bets[] = [
                            'bet_type' => '特码',
                            'targets' => [$number],
                            'amount' => $amount,
                            'raw_text' => "澳门{$number}各{$amount}元",
                            'lottery_type' => '澳门六合彩'
                        ];
                        $totalAmount += $amount;
                    }
                }
            }
        }
    }

    // 2. 解析香港下注格式: "香港、40×10元、02、04、09、45、各5元"
    if (preg_match('/香港[，,]\s*([^澳门]+)(?:澳门|$)/u', $text, $matches)) {
        $hk_part = $matches[1];
        
        // 解析 "40×10元" 格式
        if (preg_match_all('/(\d+)[×x](\d+)元/u', $hk_part, $number_matches, PREG_SET_ORDER)) {
            foreach ($number_matches as $match) {
                $number = $match[1];
                $amount = intval($match[2]);
                
                $bets[] = [
                    'bet_type' => '特码',
                    'targets' => [$number],
                    'amount' => $amount,
                    'raw_text' => "香港{$number}×{$amount}元",
                    'lottery_type' => '香港六合彩'
                ];
                $totalAmount += $amount;
            }
        }
        
        // 解析号码列表格式
        if (preg_match_all('/(\d{2})(?:[，、]|$)/u', $hk_part, $number_matches)) {
            $numbers = $number_matches[1];
            if (preg_match('/各\s*(\d+)元/u', $hk_part, $amount_match)) {
                $amount = intval($amount_match[1]);
                foreach ($numbers as $number) {
                    $bets[] = [
                        'bet_type' => '特码',
                        'targets' => [$number],
                        'amount' => $amount,
                        'raw_text' => "香港{$number}各{$amount}元",
                        'lottery_type' => '香港六合彩'
                    ];
                    $totalAmount += $amount;
                }
            }
        }
    }

    // 3. 解析香港点号分隔格式: "香港：10.22.34.46.04.16.28.40.02.14.26.38.13.25.37.01.23.35.15.27各5块"
    if (preg_match('/香港[：:]\s*([\d.]+)各(\d+)(块|元)/u', $text, $matches)) {
        $numbers_text = $matches[1];
        $amount = intval($matches[2]);
        
        $numbers = explode('.', $numbers_text);
        $numbers = array_filter($numbers, function($num) {
            return !empty(trim($num));
        });
        
        foreach ($numbers as $number) {
            $number = trim($number);
            if (!empty($number)) {
                $bets[] = [
                    'bet_type' => '特码',
                    'targets' => [$number],
                    'amount' => $amount,
                    'raw_text' => "香港号码{$number}各{$amount}块",
                    'lottery_type' => '香港六合彩'
                ];
                $totalAmount += $amount;
            }
        }
    }

    // 4. 解析澳门点号分隔格式: "澳门:04.16.28.40.02.14.26.38.13.01.25.37.49.06.18.30.42.23.35.03各5块"
    if (preg_match('/澳门[：:]\s*([\d.]+)各(\d+)(块|元)/u', $text, $matches)) {
        $numbers_text = $matches[1];
        $amount = intval($matches[2]);
        
        $numbers = explode('.', $numbers_text);
        $numbers = array_filter($numbers, function($num) {
            return !empty(trim($num));
        });
        
        foreach ($numbers as $number) {
            $number = trim($number);
            if (!empty($number)) {
                $bets[] = [
                    'bet_type' => '特码',
                    'targets' => [$number],
                    'amount' => $amount,
                    'raw_text' => "澳门号码{$number}各{$amount}块",
                    'lottery_type' => '澳门六合彩'
                ];
                $totalAmount += $amount;
            }
        }
    }

    // 5. 解析六肖下注: "兔猴蛇狗龙虎六肖300闷"
    if (preg_match('/([鼠牛虎兔龙蛇马羊猴鸡狗猪]{2,})肖\s*(\d+)\s*闷/u', $text, $matches)) {
        $zodiacs = preg_split('//u', $matches[1], -1, PREG_SPLIT_NO_EMPTY);
        $amount = intval($matches[2]);
        
        if (count($zodiacs) >= 2) {
            $bets[] = [
                'bet_type' => '六肖',
                'targets' => $zodiacs,
                'amount' => $amount,
                'raw_text' => implode('', $zodiacs) . "肖{$amount}闷",
                'lottery_type' => '混合'
            ];
            $totalAmount += $amount;
        }
    }

    return [
        'lottery_type' => count($bets) > 0 ? '混合' : '未知',
        'issue_number' => '',
        'bets' => $bets,
        'total_amount' => $totalAmount
    ];
}

/**
 * 增强的邮件内容解析
 */
function enhanceEmailContent(string $content, array $parsedData): string {
    $enhanced = $content;

    // 在每条下注后面添加结算信息
    foreach ($parsedData['bets'] as $index => $bet) {
        $searchText = $bet['raw_text'];
        $settlementInfo = "\n💰 结算: {$bet['amount']}元 × " . count($bet['targets']) . "个 = " . ($bet['amount'] * count($bet['targets'])) . "元";

        $position = strpos($enhanced, $searchText);
        if ($position !== false) {
            $insertPosition = $position + strlen($searchText);
            $enhanced = substr($enhanced, 0, $insertPosition) . $settlementInfo . substr($enhanced, $insertPosition);
        }
    }

    // 添加总计
    $totalSettlement = "\n\n==================================================\n";
    $totalSettlement .= "🎯 结算汇总\n";
    $totalSettlement .= "==================================================\n";
    $totalSettlement .= "💰 总投注金额: {$parsedData['total_amount']} 元\n";
    $totalSettlement .= "==================================================\n";

    return $enhanced . $totalSettlement;
}
?>
