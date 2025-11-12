<?php
// File: backend/ai_helper.php (优化版)

require_once __DIR__ . '/helpers/mail_parser.php';

/**
 * 分析邮件内容并提取下注信息，同时进行结算计算。
 */
function analyzeBetSlipWithAI(string $emailContent, string $lotteryType = '香港六合彩'): array {
    $cleanBody = parse_email_body($emailContent);

    if ($cleanBody === '无法解析邮件正文') {
        return ['success' => false, 'message' => 'Failed to parse email body.'];
    }

    // 获取AI分析结果
    $aiResult = analyzeWithCloudflareAI($cleanBody, $lotteryType);

    // 如果AI分析成功，进行结算计算
    if ($aiResult['success'] && isset($aiResult['data'])) {
        // 确保AI返回的数据包含彩票类型
        if (!isset($aiResult['data']['lottery_type'])) {
            $aiResult['data']['lottery_type'] = $lotteryType;
        }
        
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
            // 计算总下注金额
            $betTotal = $amount * count($targets);
            $totalBet += $betTotal;

            // 这里简化处理，实际应该根据开奖结果计算
            // 假设所有下注都是特码玩法
            if ($betType === '特码' || $betType === '号码' || $betType === '平码') {
                // 在实际应用中，这里应该与开奖结果对比
                // 现在先模拟中奖情况
                $isWin = rand(0, 10) > 7; // 30%中奖概率模拟

                if ($isWin) {
                    // 随机选择一些中奖号码
                    $winningTargets = array_slice($targets, 0, rand(1, min(3, count($targets))));
                    foreach ($winningTargets as $target) {
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
 * 使用 Cloudflare AI 进行分析 - 针对你的下注格式优化，支持彩票类型
 */
function analyzeWithCloudflareAI(string $text, string $lotteryType = '香港六合彩'): array {
    $accountId = config('CLOUDFLARE_ACCOUNT_ID');
    $apiToken = config('CLOUDFLARE_API_TOKEN');

    if (!$accountId || !$apiToken) {
        return ['success' => false, 'message' => 'Cloudflare AI credentials not configured.'];
    }

    $model = '@cf/meta/llama-3-8b-instruct';
    $url = "https://api.cloudflare.com/client/v4/accounts/{$accountId}/ai/run/{$model}";

    // 针对你的下注格式优化的提示词，包含彩票类型信息
    $prompt = "你是一个专业的六合彩下注单识别助手。请从以下微信聊天记录中精确识别出下注信息。\n\n特别注意以下下注格式：\n1. \"澳门36,48各30#，12,24各10#\" → 表示澳门六合彩，号码36和48各下注30元，号码12和24各下注10元\n2. \"鼠，鸡数各二十，兔，马数各五元\" → 表示生肖鼠和鸡各下注20元，生肖兔和马各下注5元\n3. \"香港：10.22.34.46.04.16.28.40.02.14.26.38.13.25.37.01.23.35.15.27各5块\" → 表示香港六合彩，这些号码各下注5元\n4. \"澳门、40×10元、39、30、各5元、香港、40×10元、02、04、09、45、各5元\" → 表示混合下注，包含澳门和香港\n\n当前彩票类型: {$lotteryType}\n\n请以严格的JSON格式返回识别结果：\n\n{\n    \"lottery_type\": \"{$lotteryType}\",\n    \"issue_number\": \"期号（如果提到）\",\n    \"bets\": [\n        {\n            \"bet_type\": \"下注玩法（特码/平码/生肖/色波/连肖/六肖）\",\n            \"targets\": [\"下注号码或目标\"],\n            \"amount\": 下注金额,\n            \"raw_text\": \"原始下注文本\",\n            \"lottery_type\": \"彩票类型（澳门六合彩/香港六合彩）\"\n        }\n    ],\n    \"total_amount\": 总下注金额\n}\n\n重要规则：\n1. 保持下注单的完整性，不要将一条下注拆分成多个\n2. 如果下注单中明确提到了彩票类型（如\"澳门\"、\"香港\"），使用提到的类型\n3. 如果没有明确提到，使用提供的默认类型：{$lotteryType}\n4. 对于\"各X元\"的格式，表示每个目标下注X元\n5. 对于\"X×Y元\"的格式，表示号码X下注Y元\n\n聊天记录原文：\n---\n{$text}\n---";

    $payload = [
        'messages' => [
            ['role' => 'system', 'content' => '你是一个只输出严格JSON格式的助手，必须按照指定的JSON格式返回数据。请准确识别六合彩下注信息，保持下注单的完整性。'],
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

    // 确保彩票类型正确设置
    if (!isset($bet_data['lottery_type'])) {
        $bet_data['lottery_type'] = $lotteryType;
    }

    // 确保每个下注都有彩票类型
    if (isset($bet_data['bets']) && is_array($bet_data['bets'])) {
        foreach ($bet_data['bets'] as &$bet) {
            if (!isset($bet['lottery_type'])) {
                $bet['lottery_type'] = $bet_data['lottery_type'];
            }
        }
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

/**
 * 专门用于单条下注解析的AI函数
 */
function analyzeSingleBetWithAI(string $betText, string $lotteryType = '香港六合彩'): array {
    return analyzeBetSlipWithAI($betText, $lotteryType);
}
