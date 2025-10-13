<?php
// --- Standalone Command-Line Script to List Available Gemini Models ---

echo "--- Starting Gemini Model Diagnostic ---\n\n";

// --- Step 1: Load Environment Variables ---
echo "Step 1: Loading .env file...\n";
function load_env_diag_models() {
    $envPath = __DIR__ . '/.env';
    if (file_exists($envPath)) {
        $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (strpos(trim($line), '#') === 0) continue;
            list($name, $value) = explode('=', $line, 2);
            $value = trim(trim($value), '"');
            putenv(trim($name) . '=' . $value);
        }
        echo "  [SUCCESS] .env file loaded.\n\n";
    } else {
        echo "  [FAILURE] .env file not found at {$envPath}.\n\n";
        exit(1);
    }
}
load_env_diag_models();

// --- Step 2: Get API Key ---
echo "Step 2: Checking for GEMINI_API_KEY...\n";
$apiKey = getenv('GEMINI_API_KEY');
if (empty($apiKey) || $apiKey === 'your_gemini_api_key_here') {
    echo "  [FAILURE] GEMINI_API_KEY is not set in your .env file.\n\n";
    exit(1);
}
echo "  [SUCCESS] GEMINI_API_KEY is present.\n\n";

// --- Step 3: Call the ListModels API Endpoint ---
echo "Step 3: Calling Google's ListModels API...\n";
$url = "https://generativelanguage.googleapis.com/v1/models?key={$apiKey}";

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
$responseBody = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

// --- Step 4: Display Results ---
echo "Step 4: Analyzing the response...\n";
if ($httpCode !== 200) {
    echo "  [FAILURE] API call failed!\n";
    echo "  - HTTP Status Code: {$httpCode}\n";
    echo "  - Response Body: {$responseBody}\n";
    echo "  - cURL Error: {$curlError}\n\n";
    exit(1);
}

$responseData = json_decode($responseBody, true);
if (json_last_error() !== JSON_ERROR_NONE || !isset($responseData['models'])) {
    echo "  [FAILURE] Could not parse the JSON response from Google.\n";
    echo "  - Raw Response: {$responseBody}\n\n";
    exit(1);
}

echo "  [SUCCESS] Found the following models available for your API key:\n";
echo "------------------------------------------------------------------\n";
foreach ($responseData['models'] as $model) {
    // We only care about models that support 'generateContent'
    if (in_array('generateContent', $model['supportedGenerationMethods'])) {
        echo "  - Model Name: " . $model['name'] . "\n";
        echo "    Display Name: " . $model['displayName'] . "\n";
        echo "    Description: " . substr($model['description'], 0, 70) . "...\n\n";
    }
}
echo "------------------------------------------------------------------\n";
echo "\n--- End of Diagnostic ---\n";
?>