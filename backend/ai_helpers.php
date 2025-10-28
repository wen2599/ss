<?php

declare(strict_types=1);

// backend/ai_helpers.php

function _call_api_curl(string $url, array $payload, array $headers): array
{
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60); // 60-second timeout for AI responses

    $response_body = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);

    return [
        'response_body' => $response_body,
        'http_code' => $http_code,
        'curl_error' => $curl_error,
    ];
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

    $apiUrl = "https://generativelanguage.googleapis.com/v1/models/gemini-2.5-flash:generateContent?key={$apiKey}";

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
 * [MODIFIED] 使用AI将六合彩投注邮件整理成结构化结算单。
 * @param string $email_body 邮件原文。
 * @param string|null $correction_feedback 用户提供的修正指令。
 * @return array|null 结构化的结算单数组，失败时返回null。
 */
function organize_email_with_ai(string $email_body, ?string $correction_feedback = null): ?array
{
    $system_prompt = <<<PROMPT
你是一个专业的六合彩下注单据分析师。你的任务是将用户提供的邮件文本解析成一个结构化的JSON对象，代表一份完整的结算单。严格按照以下规则执行：

1.  **JSON结构**: 返回的JSON必须包含以下顶级键：`draw_period` (string, 期号), `customer_name` (string, 客户名), `bets` (array, 投注列表), `total_amount` (number, 总金额)。
2.  **投注列表 (`bets`)**: `bets` 是一个对象数组，每条投注一个对象。每个对象必须包含：
    - `type` (string): 玩法，例如 "特码", "平码", "三中二", "连肖" 等。
    - `content` (string): 投注内容，例如 "49", "猪,鸡", "01,05,22"。
    - `amount` (number): 投注金额，必须是数字。
    - `odds` (number|null): 赔率。如果邮件中未提供，则为 `null`。
    - `status` (string): 状态，固定为 "待结算"。
    - `winnings` (number|null): 输赢金额，固定为 `null`。
3.  **数据提取**: 从邮件中仔细提取期号、客户名和每一条下注的详细信息。客户名通常在邮件开头或结尾。
4.  **计算总额**: `total_amount` 必须是所有 `bets` 中 `amount` 的总和。
5.  **修正指令**: 如果用户提供了修正指令，请优先根据指令修正你的分析结果。
6.  **输出格式**: 你的回答必须是纯粹的、格式良好的JSON，不能包含任何额外的解释、注释或包裹在 ```json ... ``` 代码块中。

**示例1 - 邮件内容:**
`客户：张三, 240期。特码 49=100。平一肖 猪=200。`
**示例1 - JSON输出:**
`{"draw_period":"240","customer_name":"张三","bets":[{"type":"特码","content":"49","amount":100,"odds":null,"status":"待结算","winnings":null},{"type":"平一肖","content":"猪","amount":200,"odds":null,"status":"待结算","winnings":null}],"total_amount":300}`

**示例2 - 邮件内容:**
`李四 240期。三中二 01,07,11=100。合肖 狗,马,羊=50x3。`
**示例2 - JSON输出:**
`{"draw_period":"240","customer_name":"李四","bets":[{"type":"三中二","content":"01,07,11","amount":100,"odds":null,"status":"待结算","winnings":null},{"type":"合肖","content":"狗,马,羊","amount":150,"odds":null,"status":"待结算","winnings":null}],"total_amount":250}`
PROMPT;

    $user_prompt = "请为以下邮件内容提取信息：\n\n---\n{$email_body}\n---\n";

    // 如果有修正指令，则附加到用户提示中
    if ($correction_feedback) {
        $user_prompt .= "\n\n请注意，用户提供了以下修正指令，请务必采纳：\n{$correction_feedback}";
    }

    // 尝试Cloudflare AI
    $cf_response = call_cloudflare_ai_api($system_prompt . "\n\n" . $user_prompt);
    if (is_string($cf_response) && strpos($cf_response, '❌') !== 0) {
        $organized_data = json_decode($cf_response, true);
        if ($organized_data && is_array($organized_data) && isset($organized_data['bets'])) {
            error_log("Successfully organized email with Cloudflare AI.");
            return $organized_data;
        }
    }

    // 如果Cloudflare失败，回退到Gemini
    error_log("Cloudflare AI failed or returned invalid data. Falling back to Gemini AI.");
    $gemini_response = call_gemini_api($system_prompt . "\n\n" . $user_prompt);
    if (is_string($gemini_response) && strpos($gemini_response, '❌') !== 0) {
        $organized_data = json_decode($gemini_response, true);
        if ($organized_data && is_array($organized_data) && isset($organized_data['bets'])) {
            error_log("Successfully organized email with Gemini AI.");
            return $organized_data;
        }
    }

    error_log("Both AI services failed to produce valid lottery settlement data.");
    return null;
}

function chat_with_ai(string $user_prompt, string $ai_service = 'cloudflare'): ?string
{
    if ($ai_service === 'cloudflare') {
        return call_cloudflare_ai_api($user_prompt);
    }
    
    if ($ai_service === 'gemini') {
        return call_gemini_api($user_prompt);
    }

    return null;
}
