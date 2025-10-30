<?php
// 文件名: ai_client.php
// 路径: backend/core/ai_client.php

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/db.php';

function get_gemini_api_key_from_db() {
    try {
        $db = get_db_connection();
        $stmt = $db->prepare("SELECT key_value FROM settings WHERE key_name = 'gemini_api_key'");
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? $result['key_value'] : GEMINI_API_KEY; // Fallback to config if not in DB
    } catch (PDOException $e) {
        error_log("Failed to get Gemini key from DB: " . $e->getMessage());
        return GEMINI_API_KEY; // Fallback to config on error
    }
}

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
        ]
    ];
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code !== 200) {
        throw new Exception("Gemini API request failed with status code $http_code: $response");
    }
    
    $result = json_decode($response, true);
    $text_content = $result['candidates'][0]['content']['parts'][0]['text'] ?? null;

    if (!$text_content) {
        throw new Exception("Invalid response from Gemini API.");
    }

    // 尝试清理AI返回的```json ... ```标记
    $cleaned_json = preg_replace('/^```json\s*|```\s*$/', '', $text_content);

    return $cleaned_json;
}