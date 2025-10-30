<?php
/**
 * 文件名: ai_client.php
 * 路径: core/ai_client.php
 * 描述: 封装对 AI API 的调用。
 */

// 这个文件通常会被其他文件 require，所以它会共享已经加载的 config.php
// require_once __DIR__ . '/../config.php'; 
// require_once __DIR__ . '/db.php';

/**
 * 从数据库中获取 Gemini API 密钥。
 * 如果数据库中没有设置，则回退到 config.php 中定义的默认值。
 *
 * @return string Gemini API 密钥。
 */
function get_gemini_api_key_from_db() {
    try {
        $db = get_db_connection();
        $stmt = $db->prepare("SELECT key_value FROM settings WHERE key_name = 'gemini_api_key'");
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // 如果数据库中有值且不为空，则使用数据库中的值
        if ($result && !empty($result['key_value'])) {
            return $result['key_value'];
        }
        
        // 否则，回退到常量定义的值
        return defined('GEMINI_API_KEY') ? GEMINI_API_KEY : '';
    } catch (Exception $e) {
        error_log("Failed to get Gemini key from DB: " . $e->getMessage());
        // 出错时同样回退
        return defined('GEMINI_API_KEY') ? GEMINI_API_KEY : '';
    }
}

/**
 * 调用 Google Gemini Pro API。
 *
 * @param string $prompt 发送给 AI 的完整提示。
 * @return string 从 AI 返回的纯文本内容。
 * @throws Exception 如果 API 调用失败或返回无效响应。
 */
function call_gemini_api($prompt) {
    $api_key = get_gemini_api_key_from_db();
    if (empty($api_key)) {
        throw new Exception("Gemini API key is not configured.");
    }
    
    $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-pro:generateContent?key=" . $api_key;
    
    $data = [
        "contents" => [
            [
                "parts" => [
                    ["text" => $prompt]
                ]
            ]
        ],
        // 可以添加一些安全设置来避免不当内容
        "safetySettings" => [
            ["category" => "HARM_CATEGORY_HARASSMENT", "threshold" => "BLOCK_NONE"],
            ["category" => "HARM_CATEGORY_HATE_SPEECH", "threshold" => "BLOCK_NONE"],
            ["category" => "HARM_CATEGORY_SEXUALLY_EXPLICIT", "threshold" => "BLOCK_NONE"],
            ["category" => "HARM_CATEGORY_DANGEROUS_CONTENT", "threshold" => "BLOCK_NONE"],
        ]
    ];
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_TIMEOUT, 30); // 设置30秒超时
    
    $response_body = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);
    
    if ($http_code !== 200) {
        throw new Exception("Gemini API request failed with status code $http_code. Response: $response_body. cURL Error: $curl_error");
    }
    
    $result = json_decode($response_body, true);
    
    // 检查 AI 是否因为安全原因或其他问题而没有返回内容
    if (!isset($result['candidates'][0]['content']['parts'][0]['text'])) {
        // 将完整的错误响应记录到日志，以便调试
        error_log("Invalid response structure from Gemini API: " . $response_body);
        throw new Exception("Invalid or empty response from Gemini API. It might be due to safety settings or other issues.");
    }

    $text_content = $result['candidates'][0]['content']['parts'][0]['text'];

    // 尝试清理 AI 可能返回的 Markdown JSON 代码块标记
    // 例如 ```json\n{...}\n```
    $cleaned_json = preg_replace('/^```(json)?\s*|\s*```$/m', '', $text_content);

    return trim($cleaned_json);
}