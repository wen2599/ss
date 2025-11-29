<?php
// File: backend/ai_helper.php

require_once __DIR__ . '/helpers/mail_parser.php';
require_once __DIR__ . '/db_operations.php';
require_once __DIR__ . '/lottery/rules.php';

function analyzeBetSlipWithAI(string $emailContent, string $lotteryType = '香港六合彩'): array {
    return analyzeSingleBetWithAI($emailContent, $lotteryType, null);
}

function analyzeSingleBetWithAI(string $betText, string $lotteryType = '香港六合彩', ?array $context = null): array {
    return analyzeWithCloudflareAI($betText, $lotteryType, $context);
}

function extract_json_from_ai_response(string $text): ?string {
    $text = preg_replace('/[[:^print:]]/', '', $text);
    $text = trim($text);
    
    // 尝试提取 ```json ... ```
    if (preg_match('/```(?:json)?\s*([\s\S]*?)\s*```/', $text, $matches)) {
        if (json_decode($matches[1]) !== null) return $matches[1];
    }
    
    // 尝试提取最外层 {}
    $first = strpos($text, '{');
    $last = strrpos($text, '}');
    if ($first !== false && $last !== false && $last > $first) {
        $candidate = substr($text, $first, $last - $first + 1);
        $candidate = preg_replace('/,\s*([}\]])/', '$1', $candidate); // 修复尾随逗号
        if (json_decode($candidate) !== null) return $candidate;
    }
    
    if (json_decode($text) !== null) return $text;
    
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

    $prompt = "你是一个JSON生成器。请分析下注文本，提取信息并输出JSON。\n\n";
    if ($context) {
        $prompt .= "--- 修正模式 ---\n";
        $prompt .= "上次解析: " . json_encode($context['original_parse'], JSON_UNESCAPED_UNICODE) . "\n";
        $prompt .= "用户指出总金额应为: " . $context['corrected_total_amount'] . "元\n";
        if (!empty($context['reason'])) $prompt .= "修正理由: '" . $context['reason'] . "'\n";
        $prompt .= "请根据正确的总金额重新解析每一项下注。\n-------------------\n\n";
    }
    
    $prompt .= "当前彩票类型: {$lotteryType}\n";
    $prompt .= "输出必须是压缩的JSON格式：\n";
    $prompt .= '{"lottery_type":"类型","bets":[{"bet_type":"玩法","targets":["号码"],"amount":10,"raw_text":"原文"}],"total_amount":总额}';
    $prompt .= "\n\n待解析文本:\n{$text}";

    $payload = [
        'messages' => [
            ['role' => 'system', 'content' => 'Output valid JSON only. Minify JSON. No markdown.'],
            ['role' => 'user', 'content' => $prompt]
        ],
        // 【关键修复】增加 max_tokens 防止 JSON 被截断
        'max_tokens' => 4000, 
        'temperature' => 0.1
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [ 'Authorization: Bearer ' . $apiToken, 'Content-Type: application/json' ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    
    $responseBody = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        return ['success' => false, 'message' => "AI API Error ($httpCode): " . substr($responseBody, 0, 100)];
    }

    $responseData = json_decode($responseBody, true);
    $ai_response_text = $responseData['result']['response'] ?? null;
    
    if (!$ai_response_text) return ['success' => false, 'message' => 'AI返回为空'];

    $json_string = extract_json_from_ai_response($ai_response_text);
    if (!$json_string) {
        // 记录原始响应以便调试
        error_log("AI JSON Parse Failed. Raw: " . substr($ai_response_text, 0, 200) . "...");
        return ['success' => false, 'message' => '无法解析AI返回的JSON (可能被截断)'];
    }

    $bet_data = json_decode($json_string, true);
    
    // 数据清洗
    if (isset($bet_data['bets']) && is_array($bet_data['bets'])) {
        foreach ($bet_data['bets'] as &$bet) {
            if (isset($bet['bet_type'])) {
                $bet['bet_type'] = preg_replace('/^(香港|澳门|新澳门|老澳门)六合彩$/', '', $bet['bet_type']);
                $bet['bet_type'] = trim($bet['bet_type']) ?: '特码';
            }
        }
    }
    
    // 补全 total_amount
    if (!isset($bet_data['total_amount']) && isset($bet_data['bets'])) {
        $total = 0;
        foreach ($bet_data['bets'] as $b) {
            $amt = floatval($b['amount'] ?? 0);
            $cnt = is_array($b['targets'] ?? []) ? count($b['targets']) : 1;
            $total += in_array($b['bet_type'], ['特码', '平码', '号码']) ? $amt * $cnt : $amt;
        }
        $bet_data['total_amount'] = $total;
    }

    return ['success' => true, 'data' => $bet_data];
}
function reanalyzeEmailWithAI(int $emailId): array { return ['success' => false, 'message' => 'Not implemented in this snippet']; }
function trainAIWithCorrection($learning_data) { return true; }
?>