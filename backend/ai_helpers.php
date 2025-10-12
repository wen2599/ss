<?php

/**
 * Calls the Google Gemini API with a given prompt.
 *
 * @param string $prompt The text prompt to send to Gemini.
 * @return string The text response from Gemini or an error message.
 */
function call_gemini_api($prompt) {
    $apiKey = getenv('GEMINI_API_KEY');
    if (empty($apiKey) || $apiKey === 'your_gemini_api_key_here') {
        return '❌ **错误**: Gemini API 密钥未配置。请通过键盘更新密钥。 ';
    }

    // Using a recent, capable model. The v1beta endpoint is often used for the latest features.
    $apiUrl = "https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash-latest:generateContent?key={$apiKey}";

    $payload = [
        'contents' => [
            [
                'parts' => [
                    ['text' => $prompt]
                ]
            ]
        ]
    ];

    $ch = curl_init($apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 90); // Increased timeout for potentially long AI responses.

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);

    if ($http_code !== 200) {
        return "❌ **API 请求失败**:\n状态码: {$http_code}\n响应: {$response}\nCURL 错误: {$curl_error}";
    }

    $responseData = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return '❌ **错误**: 解析 Gemini API 的 JSON 响应失败。';
    }

    // Extract the text content from the complex response structure.
    $text_response = $responseData['candidates'][0]['content']['parts'][0]['text'] ?? null;

    if (!$text_response) {
        return '❌ **错误**: 未在 Gemini API 输出中找到有效的文本响应。可能由于内容安全策略被拦截。';
    }

    return $text_response;
}