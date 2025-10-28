<?php

declare(strict_types=1);

// backend/ai_helpers.php
// 该文件封装了所有与AI服务（Cloudflare & Gemini）交互的函数。

/**
 * 从数据库中获取Gemini API密钥。
 * @return string|null API密钥，如果未设置则返回null。
 */
function get_gemini_api_key(): ?string
{
    global $db_connection;
    $stmt = $db_connection->prepare("SELECT setting_value FROM settings WHERE setting_key = 'gemini_api_key' LIMIT 1");
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        return $row['setting_value'];
    }
    return null;
}

/**
 * 在数据库中设置或更新Gemini API密钥。
 * @param string $api_key 要设置的API密钥。
 * @return bool 操作是否成功。
 */
function set_gemini_api_key(string $api_key): bool
{
    global $db_connection;
    // 使用ON DUPLICATE KEY UPDATE来简化插入或更新逻辑
    $stmt = $db_connection->prepare("INSERT INTO settings (setting_key, setting_value) VALUES ('gemini_api_key', ?) ON DUPLICATE KEY UPDATE setting_value = ?");
    $stmt->bind_param("ss", $api_key, $api_key);
    return $stmt->execute();
}

/**
 * [重构] 统一的AI服务调用函数，用于处理纯文本交互。
 * @param array $messages 对话消息数组，格式: [['role' => 'system'|'user', 'content' => '...']]
 * @param string $service 指定使用的AI服务 ('cloudflare' 或 'gemini')。
 * @return string|null 成功时返回AI的文本响应，失败时返回null。
 */
function unified_ai_text_chat(array $messages, string $service = 'cloudflare'): ?string
{
    if ($service === 'cloudflare') {
        $account_id = getenv('CLOUDFLARE_ACCOUNT_ID');
        $api_token = getenv('CLOUDFLARE_API_TOKEN');

        if (!$account_id || !$api_token) {
            error_log("【AI助手错误】: Cloudflare环境变量（CLOUDFLARE_ACCOUNT_ID, CLOUDFLARE_API_TOKEN）未设置。");
            return null;
        }

        $url = "https://api.cloudflare.com/client/v4/accounts/{$account_id}/ai/run/@cf/meta/llama-2-7b-chat-int8";
        $data = json_encode(['messages' => $messages]);
        $headers = [
            "Authorization: Bearer {$api_token}",
            "Content-Type: application/json"
        ];
        
        $response_data = curl_request($url, $data, $headers);
        return $response_data['result']['response'] ?? null;
    }

    if ($service === 'gemini') {
        $api_key = get_gemini_api_key();
        if (!$api_key || $api_key === 'YOUR_GEMINI_API_KEY_HERE') {
            error_log("【AI助手错误】: Gemini API密钥未在数据库中配置。");
            return null;
        }

        $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-pro:generateContent?key={$api_key}";
        // Gemini需要将system prompt和user prompt合并
        $gemini_prompt_text = "";
        foreach ($messages as $message) {
            $gemini_prompt_text .= $message['role'] . ": " . $message['content'] . "\n\n";
        }
        
        $data = json_encode(['contents' => [['parts' => [['text' => $gemini_prompt_text]]]]]);
        $headers = ["Content-Type: application/json"];
        
        $response_data = curl_request($url, $data, $headers);
        return $response_data['candidates'][0]['content']['parts'][0]['text'] ?? null;
    }

    return null;
}

/**
 * [新增] 统一的AI服务调用函数，用于处理JSON输出模式。
 * @param array $messages 对话消息数组。
 * @param string $service 指定AI服务。
 * @return array|null 成功时返回解析后的JSON数组，失败时返回null。
 */
function unified_ai_json_chat(array $messages, string $service = 'cloudflare'): ?array
{
    // Cloudflare Llama2 不支持原生JSON模式，但我们可以尝试从其文本输出中解析JSON
    if ($service === 'cloudflare') {
        $text_response = unified_ai_text_chat($messages, 'cloudflare');
        if(!$text_response) return null;

        // 经典的提取 ```json ... ``` 代码块的逻辑
        preg_match('/```json\n(.*?)\n```/s', $text_response, $matches);
        $json_string = $matches[1] ?? $text_response; // 如果没匹配到，就假设整个响应是JSON

        $json_data = json_decode(trim($json_string), true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return $json_data;
        } else {
            error_log("【AI助手错误】: Cloudflare响应中的JSON解析失败。原始文本: " . $text_response);
            return null;
        }
    }

    if ($service === 'gemini') {
        $api_key = get_gemini_api_key();
        if (!$api_key) return null;

        $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-pro:generateContent?key={$api_key}";
        $gemini_prompt_text = "";
        foreach ($messages as $message) {
            $gemini_prompt_text .= $message['role'] . ": " . $message['content'] . "\n\n";
        }

        $request_body = [
            'contents' => [['parts' => [['text' => $gemini_prompt_text]]]],
            'generationConfig' => ['response_mime_type' => 'application/json']
        ];
        
        $data = json_encode($request_body);
        $headers = ["Content-Type: application/json"];

        $response_data = curl_request($url, $data, $headers);
        $json_string = $response_data['candidates'][0]['content']['parts'][0]['text'] ?? null;

        if ($json_string) {
            $json_data = json_decode($json_string, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $json_data;
            } else {
                error_log("【AI助手错误】: Gemini(JSON模式)响应中的JSON解析失败。原始文本: " . $json_string);
                return null;
            }
        }
    }
    return null;
}

/**
 * [核心功能-优化] 使用AI将邮件文本整理成结构化的结算单。
 * @param string $email_body 邮件原文。
 * @param string|null $correction_feedback 用户提供的修正指令。
 * @return array|null 结构化的结算单数组，失败时返回null。
 */
function organize_email_with_ai(string $email_body, ?string $correction_feedback = null): ?array
{
    $system_prompt = <<<PROMPT
你是一位顶级的六合彩注单分析师。你的任务是将用户提供的邮件文本精准无误地解析成一个结构化的JSON对象。

**必须严格遵守以下规则:**

1.  **JSON结构**: 最终输出的JSON必须包含四个顶级键：`draw_period` (string, 期号), `customer_name` (string, 客户名), `bets` (array, 投注列表), `total_amount` (number, 总金额)。不得有任何多余的键。
2.  **投注列表 (`bets`)**: `bets` 数组中的每个对象都代表一条投注，且必须包含以下键：
    - `type` (string): 玩法名称，例如 "特码", "平码", "三中二", "连肖"。
    - `content` (string): 投注的具体内容，例如 "49", "猪,鸡", "01,05,22"。
    - `amount` (number): 该项投注的金额，必须是数字。
    - `odds` (number|null): 赔率。如果原文未提供，则必须为 `null`。
    - `status` (string): 状态值必须固定为 "待结算"。
    - `winnings` (number): 中奖金额，此阶段必须固定为 `0`。
3.  **数据提取**:
    - **客户名**: 通常在邮件开头或结尾，仔细识别。
    - **期号**: 明确提取期号。
    - **金额**: 注意处理 "50x3" 这样的乘法表达式，应计算为 `150`。
4.  **总额计算**: `total_amount` 的值必须精确等于所有 `bets` 中 `amount` 的总和。结算前请务必自行验算一遍。
5.  **修正指令**: 如果用户提供了修正指令，必须优先根据该指令调整分析结果。
6.  **输出格式**: 你的最终回答必须是纯粹的、格式完全正确的JSON。禁止包含任何解释、注释、Markdown标记（如 ```json ... ```）或任何非JSON字符。

**邮件内容示例:**
`客户：张三, 240期。特码 49=100。平一肖 猪=200。三中二 01,07,11=50x3。`

**对应的正确JSON输出:**
`{"draw_period":"240","customer_name":"张三","bets":[{"type":"特码","content":"49","amount":100,"odds":null,"status":"待结算","winnings":0},{"type":"平一肖","content":"猪","amount":200,"odds":null,"status":"待结算","winnings":0},{"type":"三中二","content":"01,07,11","amount":150,"odds":null,"status":"待结算","winnings":0}],"total_amount":450}`
PROMPT;

    $user_prompt = "请为以下邮件内容提取信息：\n\n---\n{$email_body}\n---\n";

    if ($correction_feedback) {
        $user_prompt .= "\n\n请注意，用户提供了以下修正指令，请务必采纳：\n{$correction_feedback}";
    }
    
    $messages = [
        ['role' => 'system', 'content' => $system_prompt],
        ['role' => 'user', 'content' => $user_prompt]
    ];

    // 优先尝试Gemini的JSON模式，因为它更可靠
    $organized_data = unified_ai_json_chat($messages, 'gemini');
    if ($organized_data && is_array($organized_data) && isset($organized_data['bets'])) {
        error_log("【AI助手日志】: 成功使用 Gemini AI (JSON模式) 解析邮件。");
        return $organized_data;
    }

    // 如果Gemini失败，回退到Cloudflare
    error_log("【AI助手警告】: Gemini AI解析失败，回退到 Cloudflare AI。");
    $organized_data = unified_ai_json_chat($messages, 'cloudflare');
    if ($organized_data && is_array($organized_data) && isset($organized_data['bets'])) {
        error_log("【AI助手日志】: 成功使用 Cloudflare AI 解析邮件。");
        return $organized_data;
    }

    error_log("【AI助手错误】: 所有AI服务均未能成功解析邮件。");
    return null;
}

/**
 * [优化] 与AI进行通用聊天。
 * @param string $user_prompt 用户输入的问题。
 * @param string $ai_service 指定使用的AI服务。
 * @return string|null AI的回复文本。
 */
function chat_with_ai(string $user_prompt, string $ai_service = 'cloudflare'): ?string
{
    $system_prompt = '你是一个乐于助人的AI助手。请始终使用中文，清晰、简洁地回答用户的问题。';

    $messages = [
        ['role' => 'system', 'content' => $system_prompt],
        ['role' => 'user', 'content' => $user_prompt]
    ];
    
    return unified_ai_text_chat($messages, $ai_service);
}


/**
 * [辅助函数] 封装了cURL请求，包含错误处理。
 * @param string $url 请求的URL。
 * @param string $data 发送的POST数据。
 * @param array $headers HTTP头信息。
 * @return array|null 成功时返回解码后的JSON数组，失败时返回null。
 */
function curl_request(string $url, string $data, array $headers): ?array
{
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $data,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_TIMEOUT => 30 // 设置30秒超时
    ]);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);

    if ($http_code !== 200) {
        error_log("【cURL请求失败】: URL: {$url}, HTTP Code: {$http_code}, cURL Error: {$curl_error}, Response: {$response}");
        return null;
    }

    $result = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("【cURL响应解析失败】: URL: {$url}, JSON Error: " . json_last_error_msg() . ", Response: " . $response);
        return null;
    }

    return $result;
}
