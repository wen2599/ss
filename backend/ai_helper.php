<?php
// File: backend/ai_helper.php (优化版)

require_once __DIR__ . '/helpers/mail_parser.php';
require_once __DIR__ . '/db_operations.php';
require_once __DIR__ . '/lottery/rules.php';

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
        $aiResult['data']['settlement'] = $settlementResult;
    }

    return $aiResult;
}

/**
 * 计算结算结果
 */
function calculateSettlement(array $betData): array {
    // 引入数据库操作和开奖规则
    require_once __DIR__ . '/db_operations.php';
    require_once __DIR__ . '/lottery/rules.php';

    $lottery_type = $betData['lottery_type'] ?? '香港六合彩';
    $issue_number = $betData['issue_number'] ?? null;

    if (!$issue_number) {
        return ['status' => 'error', 'message' => '下注数据中未找到期号，无法结算。'];
    }

    try {
        $pdo = get_db_connection();
        $stmt = $pdo->prepare("SELECT * FROM lottery_results WHERE lottery_type = ? AND issue_number = ?");
        $stmt->execute([$lottery_type, $issue_number]);
        $lottery_result = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$lottery_result) {
            return ['status' => 'error', 'message' => "未找到彩票类型 '{$lottery_type}' 第 '{$issue_number}' 期的开奖结果。"];
        }

        $winning_numbers = json_decode($lottery_result['winning_numbers'], true);
        $special_number = array_pop($winning_numbers);
        $normal_numbers = $winning_numbers;

        $total_bet_amount = 0;
        $total_win_amount = 0;
        $winning_details = [];

        foreach ($betData['bets'] as $bet) {
            $amount = floatval($bet['amount'] ?? 0);
            $bet_type = $bet['bet_type'] ?? '';
            $targets = $bet['targets'] ?? [];
            
            $bet_total = 0;
            if (in_array($bet_type, ['特码', '号码', '平码'])) {
                $bet_total = $amount * count($targets);
            } else {
                $bet_total = $amount; // 组合玩法只算一次
            }
            $total_bet_amount += $bet_total;

            switch ($bet_type) {
                case '特码':
                case '号码':
                    foreach ($targets as $target) {
                        if ($target == $special_number) {
                            $odds = 45; // 假设赔率
                            $win_amount = $amount * $odds;
                            $total_win_amount += $win_amount;
                            $winning_details[] = ['bet_type' => $bet_type, 'target' => $target, 'amount' => $amount, 'odds' => $odds, 'win_amount' => $win_amount];
                        }
                    }
                    break;
                
                case '平码':
                    foreach ($targets as $target) {
                        if (in_array($target, $normal_numbers)) {
                            $odds = 7; // 假设赔率
                            $win_amount = $amount * $odds;
                            $total_win_amount += $win_amount;
                            $winning_details[] = ['bet_type' => $bet_type, 'target' => $target, 'amount' => $amount, 'odds' => $odds, 'win_amount' => $win_amount];
                        }
                    }
                    break;

                case '生肖':
                    $special_zodiac = get_zodiac_by_number($special_number);
                    foreach ($targets as $target_zodiac) {
                        if ($target_zodiac == $special_zodiac) {
                            $odds = 11; // 假设赔率
                            $win_amount = $amount * $odds;
                            $total_win_amount += $win_amount;
                            $winning_details[] = ['bet_type' => $bet_type, 'target' => $target_zodiac, 'amount' => $amount, 'odds' => $odds, 'win_amount' => $win_amount];
                        }
                    }
                    break;

                case '色波':
                    $special_color = get_color_by_number($special_number);
                    foreach ($targets as $target_color) {
                        if (strpos($special_color, $target_color) !== false) {
                            $odds = 2.8; // 假设赔率
                            $win_amount = $amount * $odds;
                            $total_win_amount += $win_amount;
                            $winning_details[] = ['bet_type' => $bet_type, 'target' => $target_color, 'amount' => $amount, 'odds' => $odds, 'win_amount' => $win_amount];
                        }
                    }
                    break;
            }
        }

        $net_profit = $total_win_amount - $total_bet_amount;

        return [
            'status' => 'success',
            'total_bet_amount' => $total_bet_amount,
            'total_win_amount' => $total_win_amount,
            'net_profit' => $net_profit,
            'winning_details' => $winning_details,
            'summary' => "总下注: {$total_bet_amount}元, 总中奖: {$total_win_amount}元, 净利润: {$net_profit}元.",
            'lottery_result' => $lottery_result
        ];

    } catch (PDOException $e) {
        return ['status' => 'error', 'message' => '数据库连接失败: ' . $e->getMessage()];
    } catch (Exception $e) {
        return ['status' => 'error', 'message' => '结算过程中发生未知错误: ' . $e->getMessage()];
    }
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

    // 强制聚合相同金额的下注项
    if (isset($bet_data['bets']) && is_array($bet_data['bets'])) {
        $aggregatedBets = [];
        
        // 按金额分组聚合
        $betGroups = [];
        foreach ($bet_data['bets'] as $bet) {
            $amount = floatval($bet['amount'] ?? 0);
            $betType = $bet['bet_type'] ?? '特码';
            
            $key = $betType . '|' . $amount;
            
            if (!isset($betGroups[$key])) {
                $betGroups[$key] = [
                    'bet_type' => $betType,
                    'amount' => $amount,
                    'targets' => [],
                    'raw_text' => '',
                    'lottery_type' => $bet['lottery_type'] ?? $bet_data['lottery_type']
                ];
            }
            
            // 合并目标
            if (isset($bet['targets']) && is_array($bet['targets'])) {
                $betGroups[$key]['targets'] = array_merge($betGroups[$key]['targets'], $bet['targets']);
            }
            
            // 合并原始文本
            if (isset($bet['raw_text'])) {
                $betGroups[$key]['raw_text'] .= ' ' . $bet['raw_text'];
            }
        }
        
        // 转换为数组并计算每个组合的总下注
        foreach ($betGroups as $key => $group) {
            $targetCount = count($group['targets']);
            if ($group['bet_type'] === '特码' || $group['bet_type'] === '号码' || $group['bet_type'] === '平码') {
                $group['total_bet'] = $group['amount'] * $targetCount;
            } else {
                $group['total_bet'] = $group['amount']; // 组合玩法只算一次
            }
            
            $aggregatedBets[] = $group;
        }
        
        $bet_data['bets'] = $aggregatedBets;
        
        // 重新计算总金额
        $totalAmount = 0;
        foreach ($aggregatedBets as $bet) {
            $totalAmount += $bet['total_bet'];
        }
        $bet_data['total_amount'] = $totalAmount;
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

// 在 ai_helper.php 中添加学习函数
function trainAIWithCorrection($learning_data) {
    $accountId = config('CLOUDFLARE_ACCOUNT_ID');
    $apiToken = config('CLOUDFLARE_API_TOKEN');
    
    if (!$accountId || !$apiToken) {
        return false;
    }
    
    $model = '@cf/meta/llama-3-8b-instruct';
    $url = "https://api.cloudflare.com/client/v4/accounts/{$accountId}/ai/run/{$model}";
    
    $prompt = "根据以下修正数据学习如何更好地解析六合彩下注单：
    
原始文本: {$learning_data['original_text']}\n原始解析金额: {$learning_data['original_parse']['bets'][0]['amount']} 元\n修正后金额: {$learning_data['corrected_parse']['bets'][0]['amount']} 元\n修正原因: {$learning_data['corrected_parse']['correction']['correction_reason']}\n\n请学习这个修正，在将来遇到类似下注单时使用修正后的金额模式。";

    $payload = [
        'messages' => [
            ['role' => 'system', 'content' => '你是一个六合彩下注单解析AI，正在学习用户的修正以提高解析准确性。'],
            ['role' => 'user', 'content' => $prompt]
        ],
        'max_tokens' => 500
    ];
    
    // 发送学习请求
    $headers = [
        'Authorization: Bearer ' . $apiToken,
        'Content-Type: ' . 'application/json',
    ];
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $response = curl_exec($ch);
    curl_close($ch);
    
    error_log("AI Learning Response: " . $response);
    return true;
}
