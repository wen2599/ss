<?php
// File: backend/ai_helper.php

// 使用相对路径引入文件
require_once __DIR__ . '/helpers/mail_parser.php';
require_once __DIR__ . '/db_operations.php';
require_once __DIR__ . '/lottery/rules.php';

function analyzeBetSlipWithAI(string $emailContent, string $lotteryType = '香港六合彩'): array {
    return analyzeSingleBetWithAI($emailContent, $lotteryType, null);
}

function analyzeSingleBetWithAI(string $betText, string $lotteryType = '香港六合彩', ?array $context = null): array {
    return analyzeWithCloudflareAI($betText, $lotteryType, $context);
}

/**
 * 终极防御性解析函数：从AI返回的文本中安全地提取JSON。
 */
function extract_json_from_ai_response(string $text): ?string {
    // 0. 首先清理文本，移除可能的控制字符
    $text = preg_replace('/[[:^print:]]/', '', $text);
    $text = trim($text);

    // 1. 尝试直接解码
    $decoded = json_decode($text, true);
    if (json_last_error() === JSON_ERROR_NONE) {
        return $text;
    }

    // 2. 尝试从 ```json ... ``` 代码块中提取
    if (preg_match('/```json\s*([\s\S]*?)\s*```/', $text, $matches)) {
        $candidate = trim($matches[1]);
        $decoded = json_decode($candidate, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return $candidate;
        }
    }

    // 3. 尝试从 ``` ... ``` 代码块中提取
    if (preg_match('/```\s*([\s\S]*?)\s*```/', $text, $matches)) {
        $candidate = trim($matches[1]);
        $decoded = json_decode($candidate, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return $candidate;
        }
    }

    // 4. 尝试贪婪匹配第一个 { 和最后一个 }
    $first_brace = strpos($text, '{');
    $last_brace = strrpos($text, '}');
    if ($first_brace !== false && $last_brace !== false && $last_brace > $first_brace) {
        $candidate = substr($text, $first_brace, $last_brace - $first_brace + 1);
        $decoded = json_decode($candidate, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return $candidate;
        }
    }

    // 5. 尝试修复常见的JSON格式问题 (修复单引号等)
    $fix_attempts = [
        function($str) { return str_replace("'", '"', $str); },
        function($str) { return preg_replace('/,\s*([}\]])/', '$1', $str); }
    ];

    foreach ($fix_attempts as $fix) {
        $fixed_text = $fix($text);
        $decoded = json_decode($fixed_text, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return $fixed_text;
        }
    }

    // 记录失败的原始文本以便调试
    error_log("JSON extraction failed. Raw text length: " . strlen($text) . " content: " . substr($text, 0, 200) . "...");
    return null;
}

function analyzeWithCloudflareAI(string $text, string $lotteryType = '香港六合彩', ?array $context = null): array {
    $accountId = config('CLOUDFLARE_ACCOUNT_ID');
    $apiToken = config('CLOUDFLARE_API_TOKEN');

    if (!$accountId || !$apiToken) {
        return ['success' => false, 'message' => 'Cloudflare AI credentials not configured.'];
    }

    $model = '@cf/meta/llama-3-8b-instruct';
    $url = "https://api.cloudflare.com/client/v4/accounts/{$accountId}/ai/run/{$model}";

    // 构建提示词
    $prompt = "你是一个专业的六合彩下注单识别助手。请从以下文本中精确识别出下注信息，并以严格的JSON格式返回结果。\n\n";
    if ($context) {
        $prompt .= "--- 用户修正信息 ---\n";
        $prompt .= "这是我（AI）上次的解析结果: " . json_encode($context['original_parse'], JSON_UNESCAPED_UNICODE) . "\n";
        $prompt .= "用户指出正确的总金额应该是: " . $context['corrected_total_amount'] . "元\n";
        if (!empty($context['reason'])) {
            $prompt .= "用户给出的理由是: '" . $context['reason'] . "'\n";
        }
        $prompt .= "请根据这些线索，重新思考并生成一份全新的、更准确的解析。特别注意金额的计算方式，确保最终的 'total_amount' 字段等于用户提供的正确总金额。\n";
        $prompt .= "-------------------\n\n";
    }
    $prompt .= "请遵循以下格式和规则：\n";
    $prompt .= "1. \"澳门36,48各30#\" → 表示号码36和48各下注30元\n";
    $prompt .= "2. \"香港：10.22.34各5块\" → 表示号码10,22,34各下注5元\n";
    $prompt .= "3. \"40x10元\" → 表示号码40下注10元\n";
    $prompt .= "当前默认彩票类型: {$lotteryType}\n\n";
    $prompt .= "返回的JSON格式必须如下（不要包含换行符和多余空格，保持紧凑）：\n";
    $prompt .= "{\"lottery_type\":\"彩票类型\",\"bets\":[{\"bet_type\":\"玩法\",\"targets\":[\"号码\"],\"amount\":金额,\"raw_text\":\"原文\"}],\"total_amount\":总额}\n\n";
    $prompt .= "重要：请确保bet_type字段只包含玩法类型（如'特码'、'平码'、'生肖'等），不要包含彩票类型。彩票类型应该在lottery_type字段中。\n\n";
    $prompt .= "聊天记录原文：\n---\n{$text}\n---";

    // 【修改点】增加 max_tokens 参数，防止长回复被截断
    $payload = [
        'messages' => [
            ['role' => 'system', 'content' => '你是一个只输出严格JSON格式的助手。不要添加任何解释性文字。输出的JSON应尽量紧凑。'],
            ['role' => 'user', 'content' => $prompt]
        ],
        'max_tokens' => 4096 // 允许更长的输出，解决截断问题
    ];
    
    $headers = [ 'Authorization: Bearer ' . $apiToken, 'Content-Type: application/json' ];
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    $responseBody = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    // 记录日志
    $log_file = __DIR__ . '/ai_debug.log';
    $log_content = "====== AI Request ======\n";
    $log_content .= "Time: " . date('Y-m-d H:i:s') . "\n";
    $log_content .= "HTTP Code: " . $httpCode . "\n";
    // $log_content .= "Prompt: " . $prompt . "\n"; // 可选：记录 prompt
    $log_content .= "Response Length: " . strlen($responseBody) . "\n";
    $log_content .= "Raw Response Sample: " . substr($responseBody, 0, 500) . "...\n\n";
    file_put_contents($log_file, $log_content, FILE_APPEND);

    if ($httpCode !== 200) {
        return ['success' => false, 'message' => "AI API Error (HTTP {$httpCode}): " . $responseBody];
    }

    $responseData = json_decode($responseBody, true);
    $ai_response_text = $responseData['result']['response'] ?? null;
    
    if (!$ai_response_text) {
        return ['success' => false, 'message' => 'AI返回了无效的结构。'];
    }

    // 使用解析函数
    $json_string = extract_json_from_ai_response($ai_response_text);

    if (!$json_string) {
        // 如果解析失败，检查是否是因为截断
        $is_truncated = (substr(trim($ai_response_text), -1) !== '}' && substr(trim($ai_response_text), -1) !== ']');
        $error_msg = $is_truncated 
            ? 'AI响应被截断，请尝试减少单次解析的内容量。' 
            : 'AI没有返回有效的JSON内容。';
            
        return ['success' => false, 'message' => $error_msg . ' 原始返回: ' . substr($ai_response_text, 0, 200) . '...'];
    }

    $bet_data = json_decode($json_string, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return ['success' => false, 'message' => 'JSON解析失败: ' . json_last_error_msg()];
    }

    // 修复AI返回的数据
    if (isset($bet_data['bets']) && is_array($bet_data['bets'])) {
        foreach ($bet_data['bets'] as &$bet) {
            if (isset($bet['bet_type'])) {
                $bet['bet_type'] = preg_replace('/^(香港|澳门|新澳门|老澳门)六合彩$/', '', $bet['bet_type']);
                $bet['bet_type'] = trim($bet['bet_type']);
                if (empty($bet['bet_type'])) {
                    $bet['bet_type'] = '特码';
                }
            }
        }
    }

    // 计算总金额
    if (isset($bet_data['bets']) && is_array($bet_data['bets'])) {
        $totalAmount = 0;
        foreach ($bet_data['bets'] as $bet) {
            $amount = floatval($bet['amount'] ?? 0);
            $targetCount = is_array($bet['targets']) ? count($bet['targets']) : 1;
            $bet_type = $bet['bet_type'] ?? '';
            if (in_array($bet_type, ['特码', '号码', '平码'])) {
                $totalAmount += $amount * $targetCount;
            } else {
                $totalAmount += $amount;
            }
        }
        if (!isset($bet_data['total_amount'])) {
            $bet_data['total_amount'] = $totalAmount;
        }
    }

    return ['success' => true, 'data' => $bet_data];
}

function reanalyzeEmailWithAI(int $emailId): array {
    try {
        $pdo = get_db_connection();
        $stmt = $pdo->prepare("SELECT content FROM raw_emails WHERE id = ?");
        $stmt->execute([$emailId]);
        $emailContent = $stmt->fetchColumn();
        if (!$emailContent) return ['success' => false, 'message' => 'Email not found'];
        $aiResult = analyzeBetSlipWithAI($emailContent);
        if ($aiResult['success']) {
            $stmtDelete = $pdo->prepare("DELETE FROM parsed_bets WHERE email_id = ?");
            $stmtDelete->execute([$emailId]);
            $model_used = $aiResult['model'] ?? 'unknown_model';
            $bet_data_json = json_encode($aiResult['data']);
            $stmtInsert = $pdo->prepare("INSERT INTO parsed_bets (email_id, bet_data_json, ai_model_used) VALUES (?, ?, ?)");
            $stmtInsert->execute([$emailId, $bet_data_json, $model_used]);
            $stmtUpdate = $pdo->prepare("UPDATE raw_emails SET status = 'processed' WHERE id = ?");
            $stmtUpdate->execute([$emailId]);
            return ['success' => true, 'message' => '重新解析成功', 'batch_id' => $pdo->lastInsertId()];
        } else {
            $stmtUpdate = $pdo->prepare("UPDATE raw_emails SET status = 'failed' WHERE id = ?");
            $stmtUpdate->execute([$emailId]);
            return ['success' => false, 'message' => 'AI解析失败: ' . ($aiResult['message'] ?? '未知错误')];
        }
    } catch (Exception $e) {
        return ['success' => false, 'message' => '重新解析过程中出错: ' . $e->getMessage()];
    }
}

function trainAIWithCorrection($learning_data) {
    $log_message = "AI Learning Triggered:\n" . json_encode($learning_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";
    error_log($log_message, 3, __DIR__ . '/ai_learning.log');
    return true;
}
?>