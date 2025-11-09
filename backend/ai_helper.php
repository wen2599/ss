<?php
// File: ai_helper.php

// 【新增】引入邮件解析器
require_once __DIR__ . '/helpers/mail_parser.php';

/**
 * 分析邮件内容并提取下注信息。
 * @param string $emailContent 邮件原文
 * @return array
 */
function analyzeBetSlipWithAI(string $emailContent): array {
    // 【核心修改】先解析出干净的邮件正文
    $cleanBody = parse_email_body($emailContent);
    
    // 如果解析失败，可以直接返回错误
    if ($cleanBody === '无法解析邮件正文') {
        return ['success' => false, 'message' => 'Failed to parse email body.'];
    }

    // 将清洗后的正文交给 AI
    return analyzeWithCloudflareAI($cleanBody);
}

/**
 * 使用 Cloudflare AI 进行分析。
 * @param string $text 清洗后的正文文本
 * @return array
 */
function analyzeWithCloudflareAI(string $text): array {
    // ... 此函数剩余部分代码保持不变 ...
    $accountId = config('CLOUDFLARE_ACCOUNT_ID');
    // ...
    $prompt = "你是一个专业的六合彩下注单识别助手...邮件原文如下：\n\n---\n{$text}\n---";
    // ...
    
    // --- 为了完整性，这里是剩余部分的代码 ---
    $apiToken = config('CLOUDFLARE_API_TOKEN');
    if (!$accountId || !$apiToken) return ['success' => false, 'message' => 'Cloudflare AI credentials not configured.'];
    $model = '@cf/meta/llama-3-8b-instruct';
    $url = "https://api.cloudflare.com/client/v4/accounts/{$accountId}/ai/run/{$model}";
    $payload = [
        'messages' => [
            ['role' => 'system', 'content' => '你是一个只输出严格JSON格式的助手。'],
            ['role' => 'user', 'content' => $prompt]
        ]
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
    if ($httpCode !== 200) return ['success' => false, 'message' => "Cloudflare AI API Error (HTTP {$httpCode}): " . $responseBody];
    $responseData = json_decode($responseBody, true);
    $ai_response_text = $responseData['result']['response'] ?? null;
    if (!$ai_response_text) return ['success' => false, 'message' => 'Invalid response structure from Cloudflare AI.'];
    preg_match('/\{[\s\S]*\}/', $ai_response_text, $matches);
    if (empty($matches)) return ['success' => false, 'message' => 'AI did not return a valid JSON object.', 'raw_response' => $ai_response_text];
    $bet_data = json_decode($matches[0], true);
    if (json_last_error() !== JSON_ERROR_NONE) return ['success' => false, 'message' => 'Failed to decode JSON from AI response.', 'raw_json' => $matches[0]];
    return ['success' => true, 'data' => $bet_data, 'model' => $model];
}
