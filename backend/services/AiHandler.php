<?php
// File: backend/services/AiHandler.php
// Description: A service class to interact with various AI APIs.

class AiHandler {
    private $conn; // Database connection for dynamic keys
    private $cloudflare_account_id;
    private $cloudflare_api_token;
    private $gemini_api_key;

    public function __construct($conn) {
        $this->conn = $conn;
        $this->cloudflare_account_id = get_cloudflare_account_id();
        $this->cloudflare_api_token = get_cloudflare_api_token();
        $this->gemini_api_key = get_gemini_api_key($this->conn);
    }

    /**
     * Creates a standardized prompt for template generation.
     */
    private function createTemplateGenerationPrompt($email_content) {
        return "Analyze the following email content and create a reusable template from it. The template should use placeholders like {{variable_name}} for any dynamic data (e.g., names, dates, numbers, specific details). Focus on capturing the core structure and boilerplate text of the email. Return ONLY the template content, without any introductory text or explanations.\n\n--- Email Content ---\n{$email_content}\n\n--- Generated Template ---";
    }

    /**
     * Generates a template using Cloudflare Workers AI.
     */
    public function generateTemplateWithCloudflare($email_content, $model = '@cf/meta/llama-2-7b-chat-int8') {
        if (!$this->cloudflare_account_id || !$this->cloudflare_api_token) {
            return ['success' => false, 'error' => 'Cloudflare credentials are not configured.'];
        }

        $api_url = "https://api.cloudflare.com/client/v4/accounts/{$this->cloudflare_account_id}/ai/run/{$model}";
        $prompt = $this->createTemplateGenerationPrompt($email_content);
        $payload = json_encode(['prompt' => $prompt]);
        $headers = ["Authorization: Bearer {$this->cloudflare_api_token}"];

        return $this->executeCurlRequest($api_url, $payload, $headers);
    }

    /**
     * Generates a template using Google Gemini.
     */
    public function generateTemplateWithGemini($email_content, $model = 'gemini-1.5-pro-latest') {
        if (!$this->gemini_api_key || strpos($this->gemini_api_key, 'UPDATE_VIA_BOT') !== false) {
            return ['success' => false, 'error' => 'Gemini API key is not configured. Please set it via the bot.'];
        }

        $api_url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$this->gemini_api_key}";
        $prompt = $this->createTemplateGenerationPrompt($email_content);
        $payload = json_encode([
            'contents' => [
                ['parts' => [['text' => $prompt]]]
            ]
        ]);
        $headers = ['Content-Type: application/json'];

        return $this->executeCurlRequest($api_url, $payload, $headers);
    }

    /**
     * A generic cURL executor for making API calls.
     */
    private function executeCurlRequest($url, $payload, $headers) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 45); // Increased timeout for potentially slower AI models

        $response_body = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);

        if ($curl_error) {
            return ['success' => false, 'error' => 'cURL Error: ' . $curl_error];
        }

        if ($http_code >= 400) {
            return ['success' => false, 'error' => "API Error (HTTP {$http_code}): " . $response_body, 'raw_response' => $response_body];
        }
        
        $response_data = json_decode($response_body, true);

        // --- Universal Response Parser ---
        // Try to find the generated text from different possible API response structures.
        
        // Cloudflare structure
        if (isset($response_data['result']['response'])) {
            return ['success' => true, 'text' => trim($response_data['result']['response'])];
        }
        // Gemini structure
        if (isset($response_data['candidates'][0]['content']['parts'][0]['text'])) {
             return ['success' => true, 'text' => trim($response_data['candidates'][0]['content']['parts'][0]['text'])];
        }

        return ['success' => false, 'error' => 'Could not parse a valid response from the AI API.', 'raw_response' => $response_body];
    }
}
?>
