<?php
// backend/lib/AIParser.php

class AIParser {
    public static function parse(string $content): ?array {
        global $cloudflare_worker_url, $worker_secret;

        if (empty($cloudflare_worker_url) || $cloudflare_worker_url === 'https://your-worker-name.your-subdomain.workers.dev') {
            error_log('Cloudflare worker URL is not configured in config.php');
            return ['error' => 'AI worker not configured.'];
        }

        $payload = json_encode(['content' => $content]);

        $ch = curl_init($cloudflare_worker_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $worker_secret,
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60); // 60 seconds timeout for AI response

        $response_body = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);

        if ($curl_error) {
            error_log("cURL Error calling AI worker: " . $curl_error);
            return ['error' => "AI service connection error: " . $curl_error];
        }

        if ($http_code !== 200) {
            error_log("AI worker returned HTTP status $http_code. Response: $response_body");
            return ['error' => "AI service returned status $http_code."];
        }

        $decoded_response = json_decode($response_body, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log("Failed to decode JSON response from AI worker. Response: $response_body");
            return ['error' => "Invalid response from AI service."];
        }

        return $decoded_response;
    }
}
?>