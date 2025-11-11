<?php
// File: backend/helpers/manual_parser.php

/**
 * 手动解析下注信息 - 扩展版支持更多玩法
 */
function parseBetManually(string $text): array {
    $bets = [];
    $totalAmount = 0;

    // 解析澳门号码下注 - 第一条
    if (preg_match('/澳门(.+?)各(\d+)#/', $text, $matches)) {
        $numbersText = $matches[1];
        $amount = intval($matches[2]);

        // 提取号码
        preg_match_all('/\d+/', $numbersText, $numberMatches);
        $numbers = $numberMatches[0];

        foreach ($numbers as $number) {
            $bets[] = [
                'bet_type' => '特码',
                'targets' => [trim($number)],
                'amount' => $amount,
                'raw_text' => "澳门{$number}各{$amount}#",
                'lottery_type' => '澳门六合彩'
            ];
            $totalAmount += $amount;
        }
    }

    // 解析生肖下注 - 第二条
    if (preg_match('/([鼠牛虎兔龙蛇马羊猴鸡狗猪])[，,，\s]*([鼠牛虎兔龙蛇马羊猴鸡狗猪])数各(\d+)元/', $text, $matches)) {
        $zodiac1 = trim($matches[1]);
        $zodiac2 = trim($matches[2]);
        $amount = intval($matches[3]);

        $bets[] = [
            'bet_type' => '连肖',
            'targets' => [$zodiac1, $zodiac2],
            'amount' => $amount,
            'raw_text' => "{$zodiac1}，{$zodiac2}数各{$amount}元",
            'lottery_type' => '澳门六合彩'
        ];
        $totalAmount += $amount * 2;
    }

    // 解析香港号码下注 - 第三条
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
                'bet_type' => '特码',
                'targets' => [trim($number)],
                'amount' => $amount,
                'raw_text' => "香港号码{$number}各{$amount}块",
                'lottery_type' => '香港六合彩'
            ];
            $totalAmount += $amount;
        }
    }

    // 解析平特下注
    if (preg_match('/平特(.+?)各(\d+)元/', $text, $matches)) {
        $targetsText = $matches[1];
        $amount = intval($matches[2]);

        // 提取号码或生肖
        preg_match_all('/\d+|[鼠牛虎兔龙蛇马羊猴鸡狗猪]/u', $targetsText, $targetMatches);
        $targets = $targetMatches[0];

        foreach ($targets as $target) {
            $betType = is_numeric($target) ? '平特' : '平特肖';
            $bets[] = [
                'bet_type' => $betType,
                'targets' => [trim($target)],
                'amount' => $amount,
                'raw_text' => "平特{$target}各{$amount}元",
                'lottery_type' => '混合'
            ];
            $totalAmount += $amount;
        }
    }

    // 解析串码下注
    if (preg_match('/串码(.+?)各(\d+)元/', $text, $matches)) {
        $numbersText = $matches[1];
        $amount = intval($matches[2]);

        preg_match_all('/\d+/', $numbersText, $numberMatches);
        $numbers = $numberMatches[0];

        if (count($numbers) >= 2) {
            $bets[] = [
                'bet_type' => '串码',
                'targets' => $numbers,
                'amount' => $amount,
                'raw_text' => "串码" . implode(',', $numbers) . "各{$amount}元",
                'lottery_type' => '混合'
            ];
            $totalAmount += $amount * count($numbers);
        }
    }

    // 解析大小单双
    if (preg_match('/([大小单双])各(\d+)元/', $text, $matches)) {
        $type = trim($matches[1]);
        $amount = intval($matches[2]);

        $bets[] = [
            'bet_type' => '大小单双',
            'targets' => [$type],
            'amount' => $amount,
            'raw_text' => "{$type}各{$amount}元",
            'lottery_type' => '混合'
        ];
        $totalAmount += $amount;
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
    $totalSettlement .= "==================================================\n";

    return $enhanced . $totalSettlement;
}
?>