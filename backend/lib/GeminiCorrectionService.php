<?php

namespace App\Lib;

use Monolog\Logger;
use Exception;

/**
 * Class GeminiCorrectionService
 *
 * A service class to interact with the Google Gemini API for the purpose of
 * correcting and parsing unstructured bet text.
 */
class GeminiCorrectionService {
    private string $apiKey;
    private Logger $logger;
    private const API_URL = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-pro:generateContent';

    /**
     * GeminiCorrectionService constructor.
     *
     * @param string $apiKey The API key for the Gemini service.
     * @param Logger $logger A Monolog logger instance for logging.
     */
    public function __construct(string $apiKey, Logger $logger) {
        $this->apiKey = $apiKey;
        $this->logger = $logger;
    }

    /**
     * Sends unparsed text to the Gemini API for correction and returns a structured response.
     *
     * @param string $unparsedText The raw text that failed to parse.
     * @param int|null $userId The ID of the user this text belongs to, for logging purposes.
     * @return array The structured data from Gemini.
     * @throws Exception If the API key is not configured or the API request fails.
     */
    public function getCorrection(string $unparsedText, ?int $userId = null): array {
        if (empty($this->apiKey) || $this->apiKey === 'YOUR_GEMINI_API_KEY') {
            $this->logger->warning("Gemini API key is not configured. Correction service is disabled.", ['user_id' => $userId]);
            throw new Exception("Gemini API key is not configured.");
        }

        $this->logger->info("Requesting correction from Gemini.", ['user_id' => $userId]);
        $prompt = $this->buildPrompt($unparsedText);
        $postData = ['contents' => [['parts' => [['text' => $prompt]]]]]];

        $response = $this->makeRequest($postData);

        $geminiText = $response['candidates'][0]['content']['parts'][0]['text'] ?? '';
        if (empty($geminiText)) {
            $this->logger->error("Gemini response is missing the expected text content.", ['response' => $response, 'user_id' => $userId]);
            throw new Exception("Gemini response was empty or malformed.");
        }

        return $this->parseGeminiResponse($geminiText);
    }

    /**
     * Executes the API request using cURL.
     *
     * @param array $postData The data to be sent to the API.
     * @return array The decoded JSON response from the API.
     * @throws Exception If the cURL request or the API call fails.
     */
    private function makeRequest(array $postData): array {
        $url = self::API_URL . '?key=' . $this->apiKey;

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        $responseBody = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            $this->logger->error("cURL error during Gemini API request.", ['error' => $curlError]);
            throw new Exception("cURL error during Gemini API request: " . $curlError);
        }

        if ($httpCode !== 200) {
            $this->logger->error("Gemini API request failed.", ['http_code' => $httpCode, 'response_body' => $responseBody]);
            throw new Exception("Gemini API request failed with HTTP status {$httpCode}.");
        }

        $responseData = json_decode($responseBody, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->logger->error("Failed to decode JSON from Gemini API response.", ['error' => json_last_error_msg(), 'response_body' => $responseBody]);
            throw new Exception("Failed to decode JSON from Gemini API response.");
        }

        return $responseData;
    }

    /**
     * Builds the specific prompt to send to the Gemini API for bet slip parsing.
     *
     * @param string $unparsedText The raw text to include in the prompt.
     * @return string The fully constructed prompt.
     */
    private function buildPrompt(string $unparsedText): string {
        // The prompt text remains the same as it's highly specific to the task.
        return "You are an expert lottery bet slip parsing assistant. Your task is to analyze the following text, which our system failed to parse, and provide a response in a specific JSON format. The JSON response must contain three top-level keys: `corrected_data`, `suggested_regex`, and `type`.

1.  **`corrected_data`**: A JSON object representing the bets found in the text. The format should be an array of `number_bets`, where each bet has `numbers` (an array of strings), `cost_per_number` (an integer), and `cost` (an integer).
2.  **`suggested_regex`**: A single, PHP-compatible regular expression (as a JSON string) that could be used to parse this type of text in the future. The regex should be designed to be used with PHP's `preg_match`.
3.  **`type`**: A string that categorizes the `suggested_regex`. This is the most important part. The value for `type` **must** be one of the following three exact strings: `zodiac`, `number_list`, or `multiplier`. You must choose the type that best matches the regex you are providing.

Here is the text to analyze:
\"$unparsedText\"

Please provide your response inside a single JSON object. Here is an example of the required format:
```json
{
  \"corrected_data\": {
    \"number_bets\": [
      {
        \"numbers\": [\"01\", \"02\", \"03\"],
        \"cost_per_number\": 5,
        \"cost\": 15
      }
    ]
  },
  \"suggested_regex\": \"/([0-9, ]+)各\\\\s*(\\\\d+)/u\",
  \"type\": \"number_list\"
}
```";
    }

    /**
     * Parses the raw text response from Gemini to find and decode the JSON block.
     *
     * @param string $geminiText The full text response from the Gemini API.
     * @return array The validated, structured data from the JSON block.
     * @throws Exception If a valid JSON block cannot be found, decoded, or validated.
     */
    private function parseGeminiResponse(string $geminiText): array {
        preg_match('/\{.*?\}/s', $geminiText, $matches);
        if (empty($matches[0])) {
            $this->logger->error("Could not find a valid JSON block in the Gemini response.", ['response_text' => $geminiText]);
            throw new Exception("Could not find a valid JSON block in the Gemini response.");
        }

        $jsonData = json_decode($matches[0], true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->logger->error("Failed to decode JSON from Gemini response.", ['error' => json_last_error_msg(), 'json_text' => $matches[0]]);
            throw new Exception("Failed to decode JSON from Gemini response: " . json_last_error_msg());
        }
        
        if (!isset($jsonData['corrected_data'], $jsonData['suggested_regex'], $jsonData['type'])) {
             $this->logger->warning("Gemini response JSON is missing one or more required keys.", ['parsed_json' => $jsonData]);
             throw new Exception("Gemini response JSON is missing one or more required keys.");
        }

        return $jsonData;
    }
}
?>