<?php
// File: ai_helper.php

/**
 * 分析邮件内容并提取下注信息。
 * @param string $emailContent 邮件原文
 * @return array 包含 'success' 和 'data'/'message' 的结果数组
 */
function analyzeBetSlipWithAI(string $emailContent): array {
    // 策略：我们可以默认使用 Cloudflare AI，未来可以增加切换到 Gemini 的逻辑
    return analyzeWithCloudflareAI($emailContent);
}

/**
 * 使用 Cloudflare AI 进行分析。
 */
function analyzeWithCloudflareAI(string $text): array {
    $accountId = config('CLOUDFLARE_ACCOUNT_ID');
    $apiToken = config('CLOUDFLARE_API_TOKEN');

    if (!$accountId || !$apiToken) {
        return ['success' => false, 'message' => 'Cloudflare AI credentials not configured.'];
    }

    // 选择一个适合的模型，Llama-3 是个不错的选择
    $model = '@cf/meta/llama-3-8b-instruct';
    $url = "https://api.cloudflare.com/client/v4/accounts/{$accountId}/ai/run/{$model}";

    // 精心设计的 Prompt
    $prompt = "你是一个专业的六合彩下注单识别助手。请从以下邮件原文中提取所有的下注信息。返回一个严格的JSON对象，该对象包含一个名为 'bets' 的数组。数组中的每个对象都应包含 'number' (号码，字符串类型), 'amount' (金额，数字类型), 和 'type' (玩法类型，字符串，例如 '特码', '平码', '二中二' 等)。如果邮件内容无法识别或不包含下注信息，请返回一个空的 'bets' 数组。不要在JSON之外添加任何解释或注释。邮件原文如下：\n\n---\n{$text}\n---";

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
    curl_setopt($ch, CURLOPT_TIMEOUT, 60); // AI 请求可能较慢，增加超时

    $responseBody = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        return ['success' => false, 'message' => "Cloudflare AI API Error (HTTP {$httpCode}): " . $responseBody];
    }
    
    $responseData = json_decode($responseBody, true);
    $ai_response_text = $responseData['result']['response'] ?? null;

    if (!$ai_response_text) {
        return ['success' => false, 'message' => 'Invalid response structure from Cloudflare AI.'];
    }

    // 尝试从AI返回的文本中提取JSON
    // AI 可能返回 ```json\n{...}\n``` 格式
    preg_match('/\{[\s\S]*\}/', $ai_response_text, $matches);
    if (empty($matches)) {
        return ['success' => false, 'message' => 'AI did not return a valid JSON object.', 'raw_response' => $ai_response_text];
    }

    $bet_data = json_decode($matches[0], true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return ['success' => false, 'message' => 'Failed to decode JSON from AI response.', 'raw_json' => $matches[0]];
    }

    return ['success' => true, 'data' => $bet_data, 'model' => $model];
}