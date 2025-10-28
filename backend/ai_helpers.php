<?php

declare(strict_types=1);

// backend/ai_helpers.php

/**
 * 从数据库获取Gemini API密钥。
 */
function get_gemini_api_key(): ?string
{
    global $db_connection;
    $stmt = $db_connection->prepare("SELECT setting_value FROM settings WHERE setting_key = 'gemini_api_key'");
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        return $row['setting_value'];
    }
    return null;
}

/**
 * 在数据库中设置或更新Gemini API密钥。
 */
function set_gemini_api_key(string $api_key): bool
{
    global $db_connection;
    $stmt = $db_connection->prepare("INSERT INTO settings (setting_key, setting_value) VALUES ('gemini_api_key', ?) ON DUPLICATE KEY UPDATE setting_value = ?");
    $stmt->bind_param("ss", $api_key, $api_key);
    return $stmt->execute();
}

/**
 * 调用Cloudflare Workers AI (@cf/meta/llama-2-7b-chat-int8)。
 *
 * @param array $messages 消息数组，格式为 [['role' => 'system'|'user', 'content' => '...']]
 * @return array|null 返回AI的响应数组或在失败时返回null。
 */
function call_cloudflare_ai(array $messages): ?array
{
    $account_id = getenv('CLOUDFLARE_ACCOUNT_ID');
    $api_token = getenv('CLOUDFLARE_API_TOKEN');

    if (!$account_id || !$api_token) {
        error_log("Cloudflare AI credentials are not set in .env file.");
        return null;
    }

    $url = "https://api.cloudflare.com/client/v4/accounts/{$account_id}/ai/run/@cf/meta/llama-2-7b-chat-int8";
    $data = json_encode(['messages' => $messages]);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer {$api_token}",
        "Content-Type: application/json"
    ]);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);

    if ($http_code !== 200) {
        error_log("Cloudflare AI API request failed. HTTP Code: {$http_code}. Response: {$response}. cURL Error: {$curl_error}");
        return null;
    }

    $result = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("Cloudflare AI response is not valid JSON. Response: " . $response);
        return null;
    }
    if (!isset($result['result']['response'])) {
         error_log("Cloudflare AI response is missing 'result.response'. Response: " . $response);
         return null;
    }

    // Llama2 经常在 JSON 代码块前后添加额外的文本，我们需要提取它
    preg_match('/```json\n(.*?)(\n```)?/s', $result['result']['response'], $matches);

    if (isset($matches[1])) {
        $json_data = json_decode(trim($matches[1]), true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return $json_data;
        } else {
             error_log("Failed to parse JSON from Cloudflare AI response (inside code block): " . $matches[1]);
        }
    }

    // 如果没有找到代码块，尝试直接解析
    $json_data = json_decode($result['result']['response'], true);
     if (json_last_error() === JSON_ERROR_NONE) {
        return $json_data;
    } else {
         error_log("Direct JSON parse failed for Cloudflare AI response: " . $result['result']['response']);
    }

    return null; // 如果无法解析 JSON，则返回 null
}


/**
 * 调用Google Gemini Pro API。
 *
 * @param string $prompt 用户的提示。
 * @param bool $is_json_mode 是否要求输出为JSON。
 * @return array|string|null 成功时返回数组（JSON模式）或字符串，失败时返回null。
 */
function call_gemini_ai(string $prompt, bool $is_json_mode = false)
{
    $api_key = get_gemini_api_key();
    if (!$api_key || $api_key === 'YOUR_GEMINI_API_KEY_HERE') {
        error_log("Gemini API key is not configured in the database.");
        return null;
    }

    $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-pro:generateContent?key={$api_key}";

    $data = [
        'contents' => [[
            'parts' => [[
                'text' => $prompt
            ]]
        ]]
    ];

    if ($is_json_mode) {
        $data['generationConfig'] = [
            'response_mime_type' => 'application/json',
        ];
    }

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/json"]);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);

    if ($http_code !== 200) {
        error_log("Gemini API request failed. HTTP Code: {$http_code}. Response: {$response}. cURL Error: {$curl_error}");
        return null;
    }

    $result = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("Gemini API response is not valid JSON. Response: " . $response);
        return null;
    }

    if (!isset($result['candidates'][0]['content']['parts'][0]['text'])) {
        error_log("Gemini API response format is invalid or missing content. Response: " . $response);
        return null;
    }

    $text_response = $result['candidates'][0]['content']['parts'][0]['text'];

    if ($is_json_mode) {
        $json_data = json_decode($text_response, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return $json_data;
        } else {
            error_log("Failed to parse JSON from Gemini API response: " . $text_response);
            return null;
        }
    }

    return $text_response;
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
    $cf_messages = [
        ['role' => 'system', 'content' => $system_prompt],
        ['role' => 'user', 'content' => $user_prompt]
    ];
    $organized_data = call_cloudflare_ai($cf_messages);
    if ($organized_data && is_array($organized_data) && isset($organized_data['bets'])) {
        error_log("Successfully organized email with Cloudflare AI.");
        return $organized_data;
    }

    // 如果Cloudflare失败，回退到Gemini
    error_log("Cloudflare AI failed or returned invalid data. Falling back to Gemini AI.");
    $gemini_prompt = $system_prompt . "\n\n" . $user_prompt;
    $organized_data = call_gemini_ai($gemini_prompt, true);
    if ($organized_data && is_array($organized_data) && isset($organized_data['bets'])) {
        error_log("Successfully organized email with Gemini AI.");
        return $organized_data;
    }

    error_log("Both AI services failed to produce valid lottery settlement data.");
    return null;
}

function chat_with_ai(string $user_prompt, string $ai_service = 'cloudflare'): ?string
{
    $system_prompt = '你是一个乐于助人的AI助手。请清晰、简洁地回答用户的问题。';

    if ($ai_service === 'cloudflare') {
        // Add Chinese language instruction for Cloudflare AI
        $system_prompt .= ' 请始终使用中文回答。';
        $messages = [
            ['role' => 'system', 'content' => $system_prompt],
            ['role' => 'user', 'content' => $user_prompt]
        ];
        // 需要一个能返回纯文本的Cloudflare调用
        return call_cloudflare_ai_raw_text($messages);
    }
    
    if ($ai_service === 'gemini') {
        $full_prompt = $system_prompt . "\n\n用户问题: " . $user_prompt;
        return call_gemini_ai($full_prompt, false);
    }

    return null;
}

function call_cloudflare_ai_raw_text(array $messages): ?string
{
    $account_id = getenv('CLOUDFLARE_ACCOUNT_ID');
    $api_token = getenv('CLOUDFLARE_API_TOKEN');

    if (!$account_id || !$api_token) return null;

    $url = "https://api.cloudflare.com/client/v4/accounts/{$account_id}/ai/run/@cf/meta/llama-2-7b-chat-int8";
    $data = json_encode(['messages' => $messages]);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $data,
        CURLOPT_HTTPHEADER => [
            "Authorization: Bearer {$api_token}",
            "Content-Type: application/json"
        ]
    ]);

    $response = curl_exec($ch);
    curl_close($ch);

    $result = json_decode($response, true);
    return $result['result']['response'] ?? null;
}

?>