<?php
// backend/utils/ai_parsers.php

require_once __DIR__ . '/../config.php';

/**
 * 使用AI（Gemini或Cloudflare Workers AI）来解析文本格式的赔率表
 *
 * @param string $odds_text 用户输入的包含赔率信息的文本.
 * @return string|false 返回JSON格式的赔ry串，如果失败则返回false.
 */
function parseOddsWithAI($odds_text) {
    // 优先使用Gemini
    $gemini_api_key = $_ENV['GEMINI_API_KEY'] ?? null;
    if (!empty($gemini_api_key)) {
        return parseWithGemini($odds_text, $gemini_api_key);
    }

    // 备选方案：Cloudflare Workers AI
    $cf_account_id = $_ENV['CLOUDFLARE_ACCOUNT_ID'] ?? null;
    $cf_api_token = $_ENV['CLOUDFLARE_API_TOKEN'] ?? null;
    if (!empty($cf_account_id) && !empty($cf_api_token)) {
        return parseWithCloudflareAI($odds_text, $cf_account_id, $cf_api_token);
    }

    // 如果两种AI都未配置
    throw new Exception("No AI provider (Gemini or Cloudflare) is configured in the .env file.");
}


/**
 * 使用Google Gemini进行解析
 */
function parseWithGemini($odds_text, $api_key) {
    $api_url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash-latest:generateContent?key={$api_key}";

    $prompt = "你是一个专业的六合彩赔率数据结构化工具。请将以下用户输入的赔率文本转换为一个结构化的JSON对象。
    - JSON的顶级键应该是玩法的大分类，例如 '特码', '平特肖', '波色', '连码' 等。
    - 每个分类下，键是具体的玩法名称，值是对应的赔率 (必须是数字)。
    - 如果文本中没有明确的分类，请根据玩法名称自行归类。
    - 忽略所有与赔率无关的文本。
    - 最终输出必须是一个纯粹的、格式正确的JSON字符串，不要包含任何额外的解释或代码块标记。
    
    赔率文本如下：
    ---
    {$odds_text}
    ---
    ";

    $data = [
        'contents' => [['parts' => [['text' => $prompt]]]]
    ];

    $ch = curl_init($api_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_TIMEOUT, 30); // 30秒超时

    $response = curl_exec($ch);
    if (curl_errno($ch)) {
        $error_msg = curl_error($ch);
        curl_close($ch);
        throw new Exception("cURL Error calling Gemini: " . $error_msg);
    }
    curl_close($ch);

    $result = json_decode($response, true);
    $generated_text = $result['candidates'][0]['content']['parts'][0]['text'] ?? null;

    if (empty($generated_text)) {
         throw new Exception("AI parsing failed. The AI returned an empty or invalid response.");
    }
    
    // 清理AI返回的文本，移除可能存在的代码块标记
    $json_string = trim(str_replace(['```json', '```'], '', $generated_text));

    // 验证清理后的字符串是否是有效的JSON
    json_decode($json_string);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception("AI returned a malformed JSON string. Raw AI output: " . $generated_text);
    }
    
    return $json_string;
}


/**
 * 使用Cloudflare Workers AI进行解析 (作为备选)
 */
function parseWithCloudflareAI($odds_text, $account_id, $api_token) {
    $api_url = "https://api.cloudflare.com/client/v4/accounts/{$account_id}/ai/run/@cf/meta/llama-2-7b-chat-int8";
    
     $prompt = "As an expert lottery odds structuring tool, convert the user's text into a JSON object. The top-level keys should be play categories (e.g., '特码', '平特肖'). Under each category, keys are the play names and values are the odds (numbers only). Output only a pure, valid JSON string. Odds text: --- {$odds_text} ---";

    $data = ['prompt' => $prompt];

    $ch = curl_init($api_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer {$api_token}",
        "Content-Type: application/json"
    ]);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);

    $response = curl_exec($ch);
    if (curl_errno($ch)) {
        $error_msg = curl_error($ch);
        curl_close($ch);
        throw new Exception("cURL Error calling Cloudflare AI: " . $error_msg);
    }
    curl_close($ch);

    $result = json_decode($response, true);
    $generated_text = $result['result']['response'] ?? null;
    
    if (empty($generated_text)) {
         throw new Exception("AI parsing failed. The AI returned an empty or invalid response.");
    }

    $json_string = trim(str_replace(['```json', '```'], '', $generated_text));
    json_decode($json_string);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception("AI returned a malformed JSON string. Raw AI output: " . $generated_text);
    }
    
    return $json_string;
}