<?php
// backend/lib/deepseek_api.php

/**
 * Calls the DeepSeek API to get a response for a given prompt.
 *
 * @param string $prompt The system prompt instructing the AI.
 * @param string $text_input The user-provided text to analyze.
 * @param string $api_key The DeepSeek API key.
 * @return array An associative array with 'success' (bool) and 'data' (string) or 'error' (string).
 */
function call_deepseek_api(string $prompt, string $text_input, string $api_key): array
{
    $api_url = 'https://api.deepseek.com/chat/completions';

    $headers = [
        'Content-Type: application/json',
        'Accept: application/json',
        'Authorization: Bearer ' . $api_key,
    ];

    $body = [
        'model' => 'deepseek-chat',
        'messages' => [
            [
                'role' => 'system',
                'content' => $prompt,
            ],
            [
                'role' => 'user',
                'content' => $text_input,
            ],
        ],
        'temperature' => 0.1, // Set low temperature for deterministic output
        'max_tokens' => 2048,
        'stream' => false,
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $api_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60); // 60-second timeout

    $response_body = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);

    if ($curl_error) {
        return ['success' => false, 'error' => 'cURL Error: ' . $curl_error];
    }

    if ($http_code !== 200) {
        return ['success' => false, 'error' => "API request failed with HTTP code {$http_code}. Response: {$response_body}"];
    }

    $response_data = json_decode($response_body, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return ['success' => false, 'error' => 'Failed to decode JSON response: ' . json_last_error_msg()];
    }

    if (!isset($response_data['choices'][0]['message']['content'])) {
        return ['success' => false, 'error' => 'Invalid response structure from DeepSeek API'];
    }

    return ['success' => true, 'data' => $response_data['choices'][0]['message']['content']];
}
