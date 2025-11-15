<?php
// File: backend/ai_helper.php (最终加强版 - 修复AI返回非JSON问题)

require_once __DIR__ . '/helpers/mail_parser.php';
require_once __DIR__ . '/db_operations.php';
require_once __DIR__ . '/lottery/rules.php';

function analyzeBetSlipWithAI(string $emailContent, string $lotteryType = '香港六合彩'): array {
    return analyzeSingleBetWithAI($emailContent, $lotteryType, null);
}

function analyzeSingleBetWithAI(string $betText, string $lotteryType = '香港六合彩', ?array $context = null): array {
    return analyzeWithCloudflareAI($betText, $lotteryType, $context);
}

function analyzeWithCloudflareAI(string $text, string $lotteryType = '香港六合彩', ?array $context = null): array {
    $accountId = config('CLOUDFLARE_ACCOUNT_ID');
    $apiToken = config('CLOUDFLARE_API_TOKEN');

    if (!$accountId || !$apiToken) {
        return ['success' => false, 'message' => 'Cloudflare AI credentials not configured.'];
    }

    $model = '@cf/meta/llama-3-8b-instruct';
    $url = "https://api.cloudflare.com/client/v4/accounts/{$accountId}/ai/run/{$model}";

    $prompt = "你是一个专业的六合彩下注单识别助手。请从以下文本中精确识别出下注信息，并以严格的JSON格式返回结果。\n\n";
    if ($context) {
        $prompt .= "--- 用户修正信息 ---\n";
        $prompt .= "这是我（AI）上次的解析结果: " . json_encode($context['original_parse']) . "\n";
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
    $prompt .= "返回的JSON格式必须如下：\n";
    $prompt .= "{\n    \"lottery_type\": \"{$lotteryType}\",\n    \"bets\": [\n        {\n            \"bet_type\": \"玩法（特码/平码/生肖等）\",\n            \"targets\": [\"号码或目标\"],\n            \"amount\": 金额,\n            \"raw_text\": \"原始下注文本片段\"\n        }\n    ],\n    \"total_amount\": 总下注金额 // 务必精确计算所有下注的总和\n}\n\n";
    $prompt .= "聊天记录原文：\n---\n{$text}\n---";

    $payload = [ 'messages' => [ ['role' => 'system', 'content' => '你是一个只输出严格JSON格式的助手。'], ['role' => 'user', 'content' => $prompt] ] ];
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
    
    // 记录原始AI响应，用于调试
    error_log("AI Raw Response Body: " . $responseBody);

    if ($httpCode !== 200) { return ['success' => false, 'message' => "AI API Error (HTTP {$httpCode}): " . $responseBody]; }
    
    $responseData = json_decode($responseBody, true);
    $ai_response_text = $responseData['result']['response'] ?? null;
    if (!$ai_response_text) { return ['success' => false, 'message' => 'Invalid response structure from AI.']; }

    // 【【【【【核心修复点】】】】】
    // 采用更强大的方法从AI返回的文本中提取JSON
    $json_string = null;
    $first_brace = strpos($ai_response_text, '{');
    $last_brace = strrpos($ai_response_text, '}');

    if ($first_brace !== false && $last_brace !== false && $last_brace > $first_brace) {
        $json_string = substr($ai_response_text, $first_brace, $last_brace - $first_brace + 1);
    }
    
    if (!$json_string) {
        return ['success' => false, 'message' => 'AI did not return a valid JSON object.', 'raw_response' => $ai_response_text];
    }
    
    $bet_data = json_decode($json_string, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return ['success' => false, 'message' => 'Failed to decode JSON from AI response.', 'raw_json' => $json_string];
    }
    
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
