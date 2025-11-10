<?php
// File: ai_helper.php (修复版 - 改进AI提示词)

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
                if ($betType === '特码' || $betType === '号码' || $betType === '平码') {
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
 * 使用 Cloudflare AI 进行分析 - 改进提示词
 */
function analyzeWithCloudflareAI(string $text): array {
    $accountId = config('CLOUDFLARE_ACCOUNT_ID');
    $apiToken = config('CLOUDFLARE_API_TOKEN');
    
    if (!$accountId || !$apiToken) {
        return ['success' => false, 'message' => 'Cloudflare AI credentials not configured.'];
    }
    
    $model = '@cf/meta/llama-3-8b-instruct';
    $url = "https://api.cloudflare.com/client/v4/accounts/{$accountId}/ai/run/{$model}";
    
    // 改进的提示词 - 更具体地针对六合彩下注格式
    $prompt = "你是一个专业的六合彩下注单识别助手。请从以下微信聊天记录中精确识别出下注信息。

特别注意以下常见下注格式：
1. 号码下注：\"36,48各30#\" 表示号码36和48各下注30元
2. 生肖下注：\"鼠，鸡数各二十\" 表示鼠和鸡各下注20元  
3. 多号码下注：\"10.22.34.46.04.16...各5块\" 表示这些号码各下注5元
4. 澳门/香港区分：注意区分澳门六合彩和香港六合彩

请以严格的JSON格式返回识别结果：

{
    \"lottery_type\": \"彩票类型（澳门六合彩/香港六合彩）\",
    \"issue_number\": \"期号（如果提到）\",
    \"bets\": [
        {
            \"bet_type\": \"下注玩法（特码/平码/生肖/色波）\",
            \"targets\": [\"下注号码或目标\"],
            \"amount\": 下注金额,
            \"raw_text\": \"原始下注文本\"
        }
    ]
}

聊天记录原文：
---
{$text}
---";

    $payload = [
        'messages' => [
            ['role' => 'system', 'content' => '你是一个只输出严格JSON格式的助手，必须按照指定的JSON格式返回数据。'],
            ['role' => 'user', 'content' => $prompt]
        ],
        'max_tokens' => 2000
    ];
    
    $headers = [
        'Authorization: Bearer ' . $apiToken,
        'Content-Type: ' . 'application/json',
    ];
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    
    $responseBody = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    // 记录AI调用日志
    error_log("AI API Call - HTTP Code: {$httpCode}, Response: {$responseBody}");
    
    if ($httpCode !== 200) {
        return [
            'success' => false, 
            'message' => "Cloudflare AI API Error (HTTP {$httpCode}): " . $responseBody,
            'curl_error' => $curlError
        ];
    }
    
    $responseData = json_decode($responseBody, true);
    $ai_response_text = $responseData['result']['response'] ?? null;
    
    if (!$ai_response_text) {
        return [
            'success' => false, 
            'message' => 'Invalid response structure from Cloudflare AI.',
            'raw_response' => $responseBody
        ];
    }
    
    // 记录AI原始响应
    error_log("AI Raw Response: " . $ai_response_text);
    
    // 提取JSON - 更宽松的匹配
    preg_match('/\{(?:[^{}]|(?R))*\}/', $ai_response_text, $matches);
    
    if (empty($matches)) {
        // 尝试更宽松的匹配
        preg_match('/\{[\s\S]*\}/', $ai_response_text, $matches);
    }
    
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
            'message' => 'Failed to decode JSON from AI response: ' . json_last_error_msg(), 
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

/**
 * 手动重新解析邮件的函数
 */
function reanalyzeEmailWithAI(int $emailId): array {
    try {
        require_once __DIR__ . '/config.php';
        require_once __DIR__ . '/db_operations.php';
        
        $pdo = get_db_connection();
        
        // 获取邮件内容
        $stmt = $pdo->prepare("SELECT content FROM raw_emails WHERE id = ?");
        $stmt->execute([$emailId]);
        $emailContent = $stmt->fetchColumn();
        
        if (!$emailContent) {
            return ['success' => false, 'message' => 'Email not found'];
        }
        
        // 调用AI分析
        $aiResult = analyzeBetSlipWithAI($emailContent);
        
        if ($aiResult['success']) {
            // 删除旧的解析记录
            $stmtDelete = $pdo->prepare("DELETE FROM parsed_bets WHERE email_id = ?");
            $stmtDelete->execute([$emailId]);
            
            // 插入新的解析记录
            $model_used = $aiResult['model'] ?? 'unknown_model';
            $bet_data_json = json_encode($aiResult['data']);
            
            $stmtInsert = $pdo->prepare("INSERT INTO parsed_bets (email_id, bet_data_json, ai_model_used) VALUES (?, ?, ?)");
            $stmtInsert->execute([$emailId, $bet_data_json, $model_used]);
            
            // 更新邮件状态
            $stmtUpdate = $pdo->prepare("UPDATE raw_emails SET status = 'processed' WHERE id = ?");
            $stmtUpdate->execute([$emailId]);
            
            return [
                'success' => true, 
                'message' => '重新解析成功',
                'batch_id' => $pdo->lastInsertId()
            ];
        } else {
            // 更新邮件状态为失败
            $stmtUpdate = $pdo->prepare("UPDATE raw_emails SET status = 'failed' WHERE id = ?");
            $stmtUpdate->execute([$emailId]);
            
            return [
                'success' => false, 
                'message' => 'AI解析失败: ' . ($aiResult['message'] ?? '未知错误')
            ];
        }
        
    } catch (Exception $e) {
        return [
            'success' => false, 
            'message' => '重新解析过程中出错: ' . $e->getMessage()
        ];
    }
}
?>