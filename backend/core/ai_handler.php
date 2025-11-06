<?php
require_once __DIR__ . '/config.php';

function call_cloudflare_ai($prompt, $raw_email) {
    global $config;
    if (empty($config['CF_AI_ACCOUNT_ID']) || empty($config['CF_AI_API_TOKEN'])) {
        return null;
    }

    $url = "https://api.cloudflare.com/client/v4/accounts/{$config['CF_AI_ACCOUNT_ID']}/ai/run/@cf/meta/llama-2-7b-chat-int8";
    $data = [
        "messages" => [
            ["role" => "system", "content" => $prompt],
            ["role" => "user", "content" => $raw_email]
        ]
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $config['CF_AI_API_TOKEN'],
        'Content-Type: application/json'
    ]);

    $response = curl_exec($ch);
    $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpcode == 200) {
        $result = json_decode($response, true);
        return $result['result']['response'] ?? null;
    }
    return null;
}

function call_gemini_ai($prompt, $raw_email) {
    global $config;
    if (empty($config['GEMINI_API_KEY'])) {
        return null;
    }
    $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-pro:generateContent?key={$config['GEMINI_API_KEY']}";
    $data = [
        "contents" => [
            [
                "parts" => [
                    ["text" => $prompt . "\n\n" . $raw_email]
                ]
            ]
        ]
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

    $response = curl_exec($ch);
    $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpcode == 200) {
        $result = json_decode($response, true);
        return $result['candidates'][0]['content']['parts'][0]['text'] ?? null;
    }
    return null;
}

function generate_settlement_slip($bet_data_json) {
    $data = json_decode($bet_data_json, true);
    if (empty($data['bets'])) {
        return "未能识别任何有效的下注信息。";
    }

    $slip = "--- 结算单 ---\n";
    $total_amount = 0;

    foreach ($data['bets'] as $bet) {
        $type = isset($bet['type']) ? $bet['type'] : '未知类型';
        $numbers = isset($bet['numbers']) ? (is_array($bet['numbers']) ? implode(', ', $bet['numbers']) : $bet['numbers']) : '未知号码';
        $amount = isset($bet['amount']) ? floatval($bet['amount']) : 0;
        $slip .= "类型: {$type}\n号码: {$numbers}\n金额: {$amount}\n\n";
        $total_amount += $amount;
    }

    $slip .= "----------------\n";
    $slip .= "总计金额: " . $total_amount . "\n";
    
    return $slip;
}

function process_email_with_ai($raw_email) {
    $prompt = "你是一个专业的六合彩下注单据分析助手。请从以下邮件原文中提取下注信息，并以严格的JSON格式返回。JSON必须包含一个名为 'bets' 的数组，数组中的每个对象都应包含 'type' (字符串, 例如: '特码', '平码'), 'numbers' (字符串或字符串数组, 例如: ['12', '34'] 或 '45'), 和 'amount' (数字) 这三个字段。如果原文中没有可识别的下注信息，请返回一个空的 'bets' 数组，即 {\"bets\": []}。不要返回任何解释性文字，只返回JSON对象。";

    // 优先使用 Cloudflare AI
    $ai_response_str = call_cloudflare_ai($prompt, $raw_email);

    // 如果 CF AI 失败或未配置，则尝试 Gemini
    if ($ai_response_str === null) {
        $ai_response_str = call_gemini_ai($prompt, $raw_email);
    }
    
    if ($ai_response_str === null) {
        return ['error' => 'AI services failed to respond.'];
    }

    // 清理AI返回的字符串，确保它是一个纯粹的JSON
    $json_start = strpos($ai_response_str, '{');
    $json_end = strrpos($ai_response_str, '}');
    if ($json_start === false || $json_end === false) {
        return ['error' => 'AI did not return valid JSON. Response: ' . $ai_response_str];
    }
    $json_str = substr($ai_response_str, $json_start, $json_end - $json_start + 1);
    
    $decoded = json_decode($json_str, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return ['error' => 'Failed to decode AI JSON response. Raw: ' . $json_str];
    }

    $settlement = generate_settlement_slip($json_str);

    return [
        'bet_data_json' => $json_str,
        'settlement_details' => $settlement
    ];
}
