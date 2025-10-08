<?php
// backend/lib/ai_utils.php

require_once __DIR__ . '/deepseek_api.php';

/**
 * Gets a response from the DeepSeek API.
 *
 * @param string $prompt The system prompt.
 * @param string $text_input The user-provided text.
 * @return array An associative array with 'success' (bool) and 'data' or 'error'.
 */
function get_ai_response(string $prompt, string $text_input): array
{
    $api_key = getenv('DEEPSEEK_API_KEY');
    if (empty($api_key)) {
        return ['success' => false, 'error' => 'DEEPSEEK_API_KEY is not set in your .env file.'];
    }

    return call_deepseek_api($prompt, $text_input, $api_key);
}
