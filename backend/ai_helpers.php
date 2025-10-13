<?php

/**
 * 通用的 API 调用辅助函数，处理 cURL 请求和基础错误。
 *
 * @param string $url API 的 URL 端点。
 * @param array $payload 要发送的请求体数据。
 * @param array $headers HTTP 请求头。
 * @param int $timeout 超时时间（秒）。
 * @return array 包含 http_code, response_body, curl_error 的数组。
 */
function _call_api_curl($url, $payload, $headers, $timeout = 90) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);

    $responseBody = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    return [
        'http_code' => $httpCode,
        'response_body' => $responseBody,
        'curl_error' => $curlError
    ];
}

/**
 * 调用 Google Gemini API。
 *
 * @param string $prompt 要发送给 Gemini 的文本提示。
 * @return string Gemini 的文本响应或错误信息。
 */
function call_gemini_api($prompt) {
    $apiKey = getenv('GEMINI_API_KEY');
    if (empty($apiKey) || $apiKey === 'your_gemini_api_key_here') {
        return '❌ **错误**: Gemini API 密钥未配置。请检查环境变量 GEMINI_API_KEY。';
    }

    $apiUrl = "https://generativelanguage.googleapis.com/v1/models/gemini-pro:generateContent?key={$apiKey}";

    $payload = [
        'contents' => [
            ['parts' => [['text' => $prompt]]]
        ],
        'safetySettings' => [
            ['category' => 'HARM_CATEGORY_HARASSMENT', 'threshold' => 'BLOCK_NONE'],
            ['category' => 'HARM_CATEGORY_HATE_SPEECH', 'threshold' => 'BLOCK_NONE'],
            ['category' => 'HARM_CATEGORY_SEXUALLY_EXPLICIT', 'threshold' => 'BLOCK_NONE'],
            ['category' => 'HARM_CATEGORY_DANGEROUS_CONTENT', 'threshold' => 'BLOCK_NONE'],
        ],
    ];

    $headers = ['Content-Type: application/json'];

    // 使用通用函数发起请求
    $result = _call_api_curl($apiUrl, $payload, $headers);

    // 针对 Gemini API 的错误处理和响应解析
    if ($result['http_code'] !== 200) {
        $responseData = json_decode($result['response_body'], true);
        $errorMessage = $responseData['error']['message'] ?? '未知错误';

        if (strpos($errorMessage, 'Insufficient Balance') !== false || $result['http_code'] === 402) {
            return "❌ **API 请求失败**: 账户余额不足。请检查您的 Gemini 账户并充值。";
        }
        return "❌ **API 请求失败**:\n状态码: {$result['http_code']}\n错误: {$errorMessage}\nCURL 错误: {$result['curl_error']}";
    }

    $responseData = json_decode($result['response_body'], true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return '❌ **错误**: 解析 Gemini API 的 JSON 响应失败。';
    }

    $textResponse = $responseData['candidates'][0]['content']['parts'][0]['text'] ?? null;
    if (!$textResponse) {
        return '❌ **错误**: 未在 Gemini API 输出中找到有效的文本响应。可能由于内容安全策略被拦截。';
    }

    return $textResponse;
}

/**
 * 调用 DeepSeek API。
 *
 * @param string $prompt 要发送给 DeepSeek 的文本提示。
 * @return string DeepSeek 的文本响应或错误信息。
 */
function call_deepseek_api($prompt) {
    $apiKey = getenv('DEEPSEEK_API_KEY');
    if (empty($apiKey) || $apiKey === 'your_deepseek_api_key_here') {
        return '❌ **错误**: DeepSeek API 密钥未配置。请检查环境变量 DEEPSEEK_API_KEY。';
    }

    $apiUrl = "https://api.deepseek.com/chat/completions";

    $payload = [
        'model' => 'deepseek-chat',
        'messages' => [
            ['role' => 'user', 'content' => $prompt]
        ]
    ];

    $headers = [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $apiKey
    ];

    // 使用通用函数发起请求
    $result = _call_api_curl($apiUrl, $payload, $headers);

    // 针对 DeepSeek API 的错误处理和响应解析
    if ($result['http_code'] !== 200) {
        $responseData = json_decode($result['response_body'], true);
        $errorMessage = $responseData['error']['message'] ?? '未知错误';

        if (strpos($errorMessage, 'Insufficient Balance') !== false || $result['http_code'] === 402) {
            return "❌ **API 请求失败**: 账户余额不足。请检查您的 DeepSeek 账户并充值。";
        }
        return "❌ **API 请求失败**:\n状态码: {$result['http_code']}\n错误: {$errorMessage}\nCURL 错误: {$result['curl_error']}";
    }

    $responseData = json_decode($result['response_body'], true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return '❌ **错误**: 解析 DeepSeek API 的 JSON 响应失败。';
    }

    $textResponse = $responseData['choices'][0]['message']['content'] ?? null;
    if (!$textResponse) {
        return '❌ **错误**: 未在 DeepSeek API 输出中找到有效的文本响应。';
    }

    return $textResponse;
}

/**
 * 调用 Cloudflare Workers AI REST API。
 *
 * @param string $prompt 要发送给模型的文本提示。
 * @return string 模型的文本响应或错误信息。
 */
function call_cloudflare_ai_api($prompt) {
    // 从环境变量获取信息，这是最佳实践
    $accountId = getenv('CLOUDFLARE_ACCOUNT_ID');
    $apiToken = getenv('CLOUDFLARE_API_TOKEN');

    // 检查凭证是否已配置
    if (empty($accountId) || empty($apiToken)) {
        return '❌ **错误**: Cloudflare 账户ID或API令牌未配置。请检查环境变量。';
    }

    // 您可以在这里更换其他模型，例如 @cf/mistral/mistral-7b-instruct-v0.1
    $model = '@cf/meta/llama-3-8b-instruct';
    $apiUrl = "https://api.cloudflare.com/client/v4/accounts/{$accountId}/ai/run/{$model}";

    $payload = [
        'messages' => [
            ['role' => 'system', 'content' => '你是一个乐于助人的中文AI助手。'],
            ['role' => 'user', 'content' => $prompt]
        ]
    ];

    $headers = [
        'Authorization: Bearer ' . $apiToken,
        'Content-Type: application/json'
    ];

    // 使用通用函数发起请求
    $result = _call_api_curl($apiUrl, $payload, $headers);

    if ($result['http_code'] !== 200) {
        return "❌ **API 请求失败**: \n状态码: {$result['http_code']}\n响应: {$result['response_body']}\nCURL错误: {$result['curl_error']}";
    }

    $responseData = json_decode($result['response_body'], true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return '❌ **错误**: 解析 Cloudflare AI 的 JSON 响应失败。';
    }

    // 从响应中提取AI生成的文本
    $textResponse = $responseData['result']['response'] ?? null;
    if (!$textResponse) {
        // 如果找不到响应，打印整个响应体以便调试
        return '❌ **错误**: 未在 Cloudflare AI 输出中找到有效的文本响应。完整响应：' . $result['response_body'];
    }

    return $textResponse;
}
?>