<?php
// backend/cloudflare_ai_helper.php
// Contains functions to interact with Cloudflare AI for email parsing.

// NOTE: Cloudflare AI configuration is now loaded from .env via bootstrap.php

/**
 * Sends an email content to Cloudflare AI for parsing bill information.
 * @param string $emailContent The raw or text content of the email.
 * @return array|null An associative array of parsed data (amount, due_date) or null on failure.
 */
function parseEmailWithCloudflareAI(string $emailContent): ?array
{
    if (!isset($_ENV['CLOUDFLARE_ACCOUNT_ID']) || empty($_ENV['CLOUDFLARE_ACCOUNT_ID']) ||
        !isset($_ENV['CLOUDFLARE_API_TOKEN']) || empty($_ENV['CLOUDFLARE_API_TOKEN'])) {
        error_log("Cloudflare AI configuration (ACCOUNT_ID or API_TOKEN) missing in .env.");
        return null;
    }

    $accountId = $_ENV['CLOUDFLARE_ACCOUNT_ID'];
    $apiToken = $_ENV['CLOUDFLARE_API_TOKEN'];

    // Using Mistral-7b-instruct-v0.2 as a common model. Adjust if a different model is desired.
    $url = "https://api.cloudflare.com/client/v4/accounts/{$accountId}/ai/run/@cf/mistral/mistral-7b-instruct-v0.2";
    $authHeader = "Bearer {$apiToken}";

    $prompt = "Please extract the bill amount and due date from the following email content. " .
              "If it's a lottery ticket email, extract the lottery numbers. " .
              "Respond only with a JSON object containing 'amount' (float or null), 'due_date' (YYYY-MM-DD or null), " .
              " and 'lottery_numbers' (comma-separated string or null). Example: {"amount": 123.45, "due_date": "2023-12-31", "lottery_numbers": null}. " .
              "Email content: " . $emailContent;

    $data = [
        'messages' => [
            ['role' => 'user', 'content' => $prompt]
        ]
    ];

    $options = [
        'http' => [
            'header'  => [
                "Content-Type: application/json",
                $authHeader
            ],
            'method'  => 'POST',
            'content' => json_encode($data),
            'timeout' => 60, // seconds
        ],
    ];
    $context  = stream_context_create($options);
    $result = file_get_contents($url, false, $context);

    if ($result === FALSE) {
        error_log("Error communicating with Cloudflare AI API.");
        return null;
    }

    $response = json_decode($result, true);

    if (isset($response['result']['response'])) {
        $aiResponseText = $response['result']['response']);

        // Cloudflare AI might return markdown, try to extract JSON from it
        if (preg_match('/```json\n(.*)\n```/s', $aiResponseText, $matches)) {
            $jsonString = $matches[1];
        } else {
            $jsonString = $aiResponseText; // Assume it's directly JSON if no markdown
        }
        
        $parsedData = json_decode($jsonString, true);

        if (json_last_error() === JSON_ERROR_NONE) {
            return $parsedData;
        } else {
            error_log("Failed to decode Cloudflare AI JSON response: " . json_last_error_msg() . "\nRaw: " . $jsonString);
            return null;
        }
    } else if (isset($response['errors'])) {
        error_log("Cloudflare AI Error: " . json_encode($response['errors']));
        return null;
    } else {
        error_log("Unexpected Cloudflare AI response structure.");
        return null;
    }
}
