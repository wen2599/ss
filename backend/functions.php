<?php

// JWT 函数 (纯 PHP 实现)
function generateJWT($payload) {
    global $dotenv;
    $header = base64_encode(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
    $payload = base64_encode(json_encode($payload));
    $signature = hash_hmac('sha256', "$header.$payload", $dotenv['JWT_SECRET'], true);
    $signature = base64_encode($signature);
    return "$header.$payload.$signature";
}

function validateJWT($token) {
    global $dotenv;
    @list($header, $payload, $signature) = explode('.', $token);
    if (!$header || !$payload || !$signature) return false; // Added check for malformed token
    $expected = hash_hmac('sha256', "$header.$payload", $dotenv['JWT_SECRET'], true);
    $expected = base64_encode($expected);
    if ($signature !== $expected) return false;
    return json_decode(base64_decode($payload), true);
}

// 六合彩结算逻辑 (简化示例，参考官方规则)
function calculateSettlement($bets, $result) {
    $settlements = [];
    $winningNumbers = json_decode($result['numbers'], true);
    $winningSpecial = $result['special'];
    foreach ($bets as $bet) {
        $matches = count(array_intersect($bet['numbers'], $winningNumbers));
        $specialMatch = $bet['special'] == $winningSpecial;
        $prize = 0;
        if ($matches == 6 && $specialMatch) $prize = $bet['amount'] * 10000;  // 头奖示例
        elseif ($matches == 6) $prize = $bet['amount'] * 5000;
        // 添加更多规则...
        $settlements[] = ['user' => $bet['user'], 'prize' => $prize, 'matches' => $matches];
    }
    return $settlements;
}

// curl 调用 Gemini
function callGemini($prompt) {
    global $dotenv;
    $url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key=' . $dotenv['GEMINI_API_KEY'];
    $data = ['contents' => [['parts' => [['text' => $prompt]]]]];
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    $response = curl_exec($ch);
    curl_close($ch);
    $decodedResponse = json_decode($response, true);
    if (isset($decodedResponse['candidates'][0]['content']['parts'][0]['text'])) {
        return $decodedResponse['candidates'][0]['content']['parts'][0]['text'];
    } else {
        // Handle error or unexpected response structure
        error_log("Gemini API Error: " . $response);
        return "Error: Unable to get response from Gemini.";
    }
}

// 调用 Cloudflare AI (通过 Workers 代理)
function callCloudflareAI($prompt) {
    global $dotenv;
    $ch = curl_init($dotenv['WORKERS_URL'] . '/ai');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['prompt' => $prompt]));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    $response = curl_exec($ch);
    curl_close($ch);
    return json_decode($response, true);
}

// 全局错误响应函数
function json_error($msg, $statusCode = 400) {
    http_response_code($statusCode);
    echo json_encode(['error' => $msg]);
    exit();
}