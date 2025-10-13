<?php

/**
 * 调用 Cloudflare Workers AI REST API。
 *
 * @param string $prompt 要发送给模型的文本提示。
 * @return string 模型的文本响应或错误信息。
 */
function call_cloudflare_ai_api($prompt) {
    // 从环境变量获取信息
    $accountId = $_ENV['CLOUDFLARE_ACCOUNT_ID'] ?? null;
    $apiToken = $_ENV['CLOUDFLARE_API_TOKEN'] ?? null;

    // 检查凭证是否已配置
    if (empty($accountId) || empty($apiToken)) {
        return '❌ **错误**: Cloudflare 账户ID或API令牌未配置。请检查环境变量。';
    }

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

    $result = _call_api_curl($apiUrl, $payload, $headers);

    if ($result['http_code'] !== 200) {
        return "❌ **API 请求失败**: \n状态码: {$result['http_code']}\n响应: {$result['response_body']}\nCURL错误: {$result['curl_error']}";
    }

    $responseData = json_decode($result['response_body'], true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return '❌ **错误**: 解析 Cloudflare AI 的 JSON 响应失败。';
    }

    $textResponse = $responseData['result']['response'] ?? null;
    if (!$textResponse) {
        return '❌ **错误**: 未在 Cloudflare AI 输出中找到有效的文本响应。完整响应：' . $result['response_body'];
    }

    return $textResponse;
}
?>