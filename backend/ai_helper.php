<?php
// File: ai_helper.php (增强版 - 包含结算计算)

require_once __DIR__ . '/helpers/mail_parser.php';

/**
 * 分析邮件内容并提取下注信息，同时进行结算计算。
 */
function analyzeBetSlipWithAI(string $emailContent): array {
    $cleanBody = parse_email_body($emailContent);

    if ($cleanBody === '无法解析邮件正文') {
        return ['success' => false, 'message' => 'Failed to parse email body.'];
    }

    // 获取AI分析结果
    $aiResult = analyzeWithCloudflareAI($cleanBody);
    
    // 如果AI分析成功，进行结算计算
    if ($aiResult['success'] && isset($aiResult['data'])) {
        $settlementResult = calculateSettlement($aiResult['data']);
        $aiResult['settlement'] = $settlementResult;
    }
    
    return $aiResult;
}

/**
 * 计算结算结果
 */
function calculateSettlement(array $betData): array {
    $settlement = [
        'total_bet_amount' => 0,
        'winning_details' => [],
        'net_profits' => [],
        'summary' => ''
    ];
    
    if (!isset($betData['bets']) || !is_array($betData['bets'])) {
        return $settlement;
    }
    
    $totalBet = 0;
    $winningBets = [];
    
    foreach ($betData['bets'] as $bet) {
        $amount = floatval($bet['amount'] ?? 0);
        $betType = $bet['bet_type'] ?? '';
        $targets = $bet['targets'] ?? [];
        
        if ($amount > 0 && is_array($targets)) {
            foreach ($targets as $target) {
                $totalBet += $amount;
                
                // 这里简化处理，实际应该根据开奖结果计算
                // 假设所有下注都是特码玩法
                if ($betType === '特码' || $betType === '号码') {
                    // 在实际应用中，这里应该与开奖结果对比
                    // 现在先模拟中奖情况
                    $isWin = rand(0, 10) > 7; // 30%中奖概率模拟
                    
                    if ($isWin) {
                        $winningBets[] = [
                            'number' => $target,
                            'amount' => $amount,
                            'odds' => 45 // 默认赔率
                        ];
                    }
                }
            }
        }
    }
    
    $settlement['total_bet_amount'] = $totalBet;
    $settlement['winning_details'] = $winningBets;
    
    // 计算不同赔率下的净收益
    $oddsList = [45, 46, 47];
    foreach ($oddsList as $odds) {
        $totalWin = 0;
        foreach ($winningBets as $win) {
            $totalWin += $win['amount'] * $odds;
        }
        $netProfit = $totalWin - $totalBet;
        $settlement['net_profits'][$odds] = [
            'total_win' => $totalWin,
            'net_profit' => $netProfit,
            'is_profit' => $netProfit >= 0
        ];
    }
    
    $winCount = count($winningBets);
    $settlement['summary'] = "总下注 {$totalBet} 元，中奖 {$winCount} 注";
    
    return $settlement;
}

/**
 * 将结算结果嵌入邮件内容
 */
function embedSettlementInEmail(string $emailContent, array $settlementData): string {
    $embeddedContent = $emailContent;
    
    // 在邮件末尾添加结算信息
    $settlementHtml = "\n\n--- 结算结果 ---\n";
    
    $settlementHtml .= "📊 " . $settlementData['summary'] . "\n";
    $settlementHtml .= "💰 总投注金额: " . $settlementData['total_bet_amount'] . " 元\n";
    
    if (!empty($settlementData['winning_details'])) {
        $settlementHtml .= "🎯 中奖详情:\n";
        foreach ($settlementData['winning_details'] as $win) {
            $settlementHtml .= "   - 号码 {$win['number']}: {$win['amount']} 元 (赔率 {$win['odds']})\n";
        }
    }
    
    $settlementHtml .= "\n📈 不同赔率结算:\n";
    foreach ($settlementData['net_profits'] as $odds => $result) {
        $color = $result['is_profit'] ? '🟢' : '🔴';
        $profitText = $result['is_profit'] ? "盈利" : "亏损";
        $settlementHtml .= "{$color} 赔率 {$odds}: {$profitText} " . abs($result['net_profit']) . " 元\n";
    }
    
    $embeddedContent .= $settlementHtml;
    
    return $embeddedContent;
}

/**
 * 使用 Cloudflare AI 进行分析
 */
function analyzeWithCloudflareAI(string $text): array {
    $accountId = config('CLOUDFLARE_ACCOUNT_ID');
    $apiToken = config('CLOUDFLARE_API_TOKEN');
    
    if (!$accountId || !$apiToken) {
        return ['success' => false, 'message' => 'Cloudflare AI credentials not configured.'];
    }
    
    $model = '@cf/meta/llama-3-8b-instruct';
    $url = "https://api.cloudflare.com/client/v4/accounts/{$accountId}/ai/run/{$model}";
    
    $prompt = "你是一个专业的六合彩下注单识别助手。请从以下邮件原文中识别出下注信息，并以严格的JSON格式返回。
要求：
1. 识别彩票类型（香港六合彩、澳门六合彩等）
2. 识别下注玩法（特码、平码、生肖、色波等）
3. 识别下注号码和金额
4. 识别期号（如果有）

返回格式：
{
    \"lottery_type\": \"彩票类型\",
    \"issue_number\": \"期号\",
    \"bets\": [
        {
            \"bet_type\": \"下注玩法\",
            \"targets\": [\"下注号码或目标\"],
            \"amount\": 下注金额,
            \"raw_text\": \"原始下注文本\"
        }
    ]
}

邮件原文如下：
---
{$text}
---";

    $payload = [
        'messages' => [
            ['role' => 'system', 'content' => '你是一个只输出严格JSON格式的助手。'],
            ['role' => 'user', 'content' => $prompt]
        ]
    ];
    
    $headers = [
        'Authorization: Bearer ' . $apiToken,
        'Content-Type: application/json'
    ];
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    
    $responseBody = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200) {
        return [
            'success' => false, 
            'message' => "Cloudflare AI API Error (HTTP {$httpCode}): " . $responseBody
        ];
    }
    
    $responseData = json_decode($responseBody, true);
    $ai_response_text = $responseData['result']['response'] ?? null;
    
    if (!$ai_response_text) {
        return ['success' => false, 'message' => 'Invalid response structure from Cloudflare AI.'];
    }
    
    // 提取JSON
    preg_match('/\{[\s\S]*\}/', $ai_response_text, $matches);
    
    if (empty($matches)) {
        return [
            'success' => false, 
            'message' => 'AI did not return a valid JSON object.', 
            'raw_response' => $ai_response_text
        ];
    }
    
    $bet_data = json_decode($matches[0], true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        return [
            'success' => false, 
            'message' => 'Failed to decode JSON from AI response.', 
            'raw_json' => $matches[0]
        ];
    }
    
    return [
        'success' => true, 
        'data' => $bet_data, 
        'model' => $model,
        'raw_ai_response' => $ai_response_text
    ];
}
?>