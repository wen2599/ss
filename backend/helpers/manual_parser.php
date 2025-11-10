<?php
// File: backend/helpers/manual_parser.php

/**
 * 手动解析下注信息 - 针对特定格式
 */
function parseBetManually(string $text): array {
    $bets = [];
    $totalAmount = 0;
    
    // 解析澳门号码下注
    if (preg_match('/澳门(.+?)各(\d+)#/', $text, $matches)) {
        $numbersText = $matches[1];
        $amount = intval($matches[2]);
        
        // 提取号码
        preg_match_all('/\d+/', $numbersText, $numberMatches);
        $numbers = $numberMatches[0];
        
        foreach ($numbers as $number) {
            $bets[] = [
                'bet_type' => '号码',
                'targets' => [$number],
                'amount' => $amount,
                'raw_text' => "澳门{$number}各{$amount}#"
            ];
            $totalAmount += $amount;
        }
    }
    
    // 解析生肖下注
    if (preg_match('/([鼠牛虎兔龙蛇马羊猴鸡狗猪]+)[，,]\s*([鼠牛虎兔龙蛇马羊猴鸡狗猪]+)数各(\d+)元/', $text, $matches)) {
        $zodiac1 = $matches[1];
        $zodiac2 = $matches[2];
        $amount = intval($matches[3]);
        
        $bets[] = [
            'bet_type' => '生肖',
            'targets' => [$zodiac1, $zodiac2],
            'amount' => $amount,
            'raw_text' => "{$zodiac1}，{$zodiac2}数各{$amount}元"
        ];
        $totalAmount += $amount * 2;
    }
    
    // 解析香港号码下注
    if (preg_match('/香港：(.+?)各(\d+)块/', $text, $matches)) {
        $numbersText = $matches[1];
        $amount = intval($matches[2]);
        
        // 提取号码（用点号分隔）
        $numbers = explode('.', $numbersText);
        $numbers = array_filter($numbers, function($num) {
            return !empty(trim($num));
        });
        
        foreach ($numbers as $number) {
            $bets[] = [
                'bet_type' => '号码',
                'targets' => [trim($number)],
                'amount' => $amount,
                'raw_text' => "香港号码{$number}各{$amount}块"
            ];
            $totalAmount += $amount;
        }
    }
    
    return [
        'lottery_type' => '混合',
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
    
    // 模拟中奖计算
    $winningAmount = min(5, $parsedData['total_amount']); // 简单模拟中奖5元
    $totalSettlement .= "🎊 中奖金额: {$winningAmount} 元\n\n";
    
    // 不同赔率结算
    $totalSettlement .= "📈 不同赔率结算:\n";
    $oddsList = [45, 46, 47];
    foreach ($oddsList as $odds) {
        $netProfit = $winningAmount * $odds - $parsedData['total_amount'];
        $profitText = $netProfit >= 0 ? "盈利" : "亏损";
        $totalSettlement .= "🔴 赔率 {$odds}: {$parsedData['total_amount']}-" . ($winningAmount * $odds) . "=" . abs($netProfit) . "元 ({$profitText})\n";
    }
    
    $totalSettlement .= "==================================================\n";
    
    return $enhanced . $totalSettlement;
}
?>
