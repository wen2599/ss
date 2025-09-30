<?php

class GeminiCorrectionService {
    private $apiKey;
    private $apiUrl = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-pro:generateContent';

    public function __construct(string $apiKey) {
        $this->apiKey = $apiKey;
    }

    /**
     * Sends the unparsed text to the Gemini API to get a corrected parsing and a suggested regex.
     *
     * @param string $unparsedText The text that the original parser failed to understand.
     * @return array|null An associative array with 'corrected_data' and 'suggested_regex', or null on failure.
     */
    public function getCorrection(string $unparsedText): ?array {
        if (empty($this->apiKey) || $this->apiKey === 'YOUR_GEMINI_API_KEY') {
            error_log("Gemini API key is not configured.");
            return null;
        }

        $prompt = $this->buildPrompt($unparsedText);

        $postData = [
            'contents' => [
                [
                    'parts' => [
                        ['text' => $prompt]
                    ]
                ]
            ]
        ];

        $ch = curl_init($this->apiUrl . '?key=' . $this->apiKey);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http_code !== 200) {
            error_log("Gemini API request failed with HTTP code $http_code: $response");
            return null;
        }

        $responseData = json_decode($response, true);
        $geminiText = $responseData['candidates'][0]['content']['parts'][0]['text'] ?? '';

        return $this->parseGeminiResponse($geminiText);
    }

    /**
     * Builds the specific prompt to send to the Gemini API.
     *
     * @param string $unparsedText
     * @return string
     */
    private function buildPrompt(string $unparsedText): string {
        // This prompt is carefully designed to instruct the AI on its role and desired output format.
        return "You are an expert lottery bet slip parsing assistant. Your task is to analyze the following text, which our system failed to parse, and provide two things in a specific JSON format.

        1.  `corrected_data`: A JSON object representing the bets found in the text. The format should be an array of 'number_bets', where each bet has 'numbers' (an array of strings), 'cost_per_number' (an integer), and 'cost' (an integer).
        2.  `suggested_regex`: A single, PHP-compatible regular expression (as a JSON string) that could be used to parse this type of text in the future.

        Here is the text to analyze:
        \"$unparsedText\"

        Please provide your response inside a single JSON object, like this example:
        {
          \"corrected_data\": {
            \"number_bets\": [
              {
                \"numbers\": [\"01\", \"02\"],
                \"cost_per_number\": 10,
                \"cost\": 20
              }
            ]
          },
          \"suggested_regex\": \"/your-regex-pattern/u\"
        }";
    }

    /**
     * Parses the raw text response from Gemini into a structured array.
     *
     * @param string $geminiText
     * @return array|null
     */
    private function parseGeminiResponse(string $geminiText): ?array {
        // Find the JSON block in the response, in case the AI adds extra text.
        preg_match('/\{.*?\}/s', $geminiText, $matches);
        if (empty($matches[0])) {
            error_log("Could not find a valid JSON block in the Gemini response.");
            return null;
        }

        $json_data = json_decode($matches[0], true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log("Failed to decode JSON from Gemini response: " . json_last_error_msg());
            return null;
        }

        return [
            'corrected_data' => $json_data['corrected_data'] ?? null,
            'suggested_regex' => $json_data['suggested_regex'] ?? null
        ];
    }
}
?>