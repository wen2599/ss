<?php
// backend/lib/gemini_ai.php

require_once __DIR__ . '/../bootstrap.php';

/**
 * Calls the Gemini API to parse text based on a given prompt.
 *
 * @param string $prompt The instructional text for the AI.
 * @param string $text_to_analyze The user-provided text to be analyzed.
 * @return array An associative array with 'success' (bool) and 'data' or 'error' (string).
 */
function call_gemini_api($prompt, $text_to_analyze) {
    $api_key = get_api_key('gemini');
    if (!$api_key) {
        return ['success' => false, 'error' => 'Gemini API key not found in the database.'];
    }

    $api_url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-pro:generateContent?key=" . $api_key;

    $full_prompt = $prompt . "\n\n---\n\n" . $text_to_analyze;

    $payload = [
        'contents' => [
            [
                'parts' => [
                    ['text' => $full_prompt]
                ]
            ]
        ]
    ];

    $options = [
        'http' => [
            'method'  => 'POST',
            'header'  => "Content-Type: application/json\r\n",
            'content' => json_encode($payload),
            'ignore_errors' => true // Important to see error messages from the API
        ]
    ];

    $context = stream_context_create($options);
    $result = file_get_contents($api_url, false, $context);

    if ($result === false) {
        return ['success' => false, 'error' => 'API request failed. Check server logs for details.'];
    }

    $response_data = json_decode($result, true);

    // Check for explicit API errors in the response body
    if (isset($response_data['error'])) {
        $error_message = $response_data['error']['message'] ?? 'Unknown API error';
        return ['success' => false, 'error' => "API Error: " . $error_message];
    }

    // Check for the expected content structure
    $generated_text = $response_data['candidates'][0]['content']['parts'][0]['text'] ?? null;

    if ($generated_text) {
        return ['success' => true, 'data' => $generated_text];
    } else {
        // Log the full response for debugging if the expected structure is not found
        error_log("Gemini API - Unexpected response format: " . $result);
        return ['success' => false, 'error' => 'Could not extract generated text from API response.'];
    }
}

?>