<?php
// backend/gemini_ai_helper.php
// Contains functions to interact with Google Gemini AI for email parsing.

// NOTE: The API key is now loaded from .env via bootstrap.php

/**
 * Sends an email content to Gemini AI for parsing bill information.
 * @param string $emailContent The raw or text content of the email.
 * @return array|null An associative array of parsed data (amount, due_date) or null on failure.
 */
function parseEmailWithGemini(string $emailContent): ?array
{
    if (!isset($_ENV['GEMINI_API_KEY']) || empty($_ENV['GEMINI_API_KEY'])) {
        error_log("Gemini API key not configured in .env.");
        return null;
    }

    $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-pro:generateContent?key=" . $_ENV['GEMINI_API_KEY'];

    $prompt = "Please extract the bill amount and due date from the following email content. " .
              "If it's a lottery ticket email, extract the lottery numbers. " .
              "Respond only with a JSON object containing 'amount' (float or null), 'due_date' (YYYY-MM-DD or null), " .
              " and 'lottery_numbers' (comma-separated string or null). Example: {"amount": 123.45, "due_date": "2023-12-31", "lottery_numbers": null}. " .
              "Email content: " . $emailContent;

    $data = [
        'contents' => [
            [
                'parts' => [
                    ['text' => $prompt]
                ]
            ]
        ]
    ];

    $options = [
        'http' => [
            'header'  => "Content-Type: application/json",
            'method'  => 'POST',
            'content' => json_encode($data),
            'timeout' => 60, // seconds
        ],
    ];
    $context  = stream_context_create($options);
    $result = file_get_contents($url, false, $context);

    if ($result === FALSE) {
        error_log("Error communicating with Gemini API.");
        return null;
    }

    $response = json_decode($result, true);

    // Check if the response structure is as expected and extract text
    if (isset($response['candidates'][0]['content']['parts'][0]['text'])) {
        $geminiText = $response['candidates'][0]['content']['parts'][0]['text']);
        // Gemini might return markdown, try to extract JSON from it
        if (preg_match('/```json\n(.*)\n```/s', $geminiText, $matches)) {
            $jsonString = $matches[1];
        } else {
            $jsonString = $geminiText; // Assume it's directly JSON if no markdown
        }

        $parsedData = json_decode($jsonString, true);

        if (json_last_error() === JSON_ERROR_NONE) {
            return $parsedData;
        } else {
            error_log("Failed to decode Gemini JSON response: " . json_last_error_msg() . "\nRaw: " . $jsonString);
            return null;
        }
    } else if (isset($response['error'])) {
        error_log("Gemini API Error: " . $response['error']['message']);
        return null;
    } else {
        error_log("Unexpected Gemini API response structure.");
        return null;
    }
}
