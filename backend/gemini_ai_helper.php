<?php

require_once __DIR__ . '/api_curl_helper.php';

/**
 * 调用 Google Gemini API。
 *
 * @param string $prompt 要发送给 Gemini 的文本提示。
 * @return string Gemini 的文本响应或错误信息。
 */
function call_gemini_api($prompt) {
    $apiKey = $_ENV['GEMINI_API_KEY'] ?? null;
    if (empty($apiKey) || $apiKey === 'your_gemini_api_key_here') {
        error_log("Gemini AI not configured: API key is missing.");
        return '❌ **错误**: Gemini API 密钥未配置。请联系管理员。';
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
?>