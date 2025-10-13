<?php
// --- Standalone Command-Line Script to Test Outbound Connectivity ---

echo "--- Starting Server Connectivity Diagnostic ---\n\n";

function test_url($url) {
    echo "Testing connection to: {$url}\n";
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10); // 10-second timeout
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    // For HTTPS, we want to verify the peer's certificate
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);

    $responseBody = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    $totalTime = curl_getinfo($ch, CURLINFO_TOTAL_TIME);
    curl_close($ch);

    if ($curlError) {
        echo "  [FAILURE] cURL Error: {$curlError}\n";
    } else {
        echo "  [SUCCESS] Connection successful.\n";
        echo "  - HTTP Status Code: {$httpCode}\n";
        echo "  - Response Time: {$totalTime} seconds\n";
    }
    echo "----------------------------------------\n";
}

// --- Test 1: General Internet Connectivity ---
echo "Test 1: Checking general outbound HTTPS connectivity...\n";
test_url('https://www.google.com');

// --- Test 2: Connectivity to Self (Backend) ---
echo "\nTest 2: Checking if the server can connect to its own backend URL...\n";

// Load environment to get the backend URL
function load_env_diag_conn() {
    $envPath = __DIR__ . '/.env';
    if (file_exists($envPath)) {
        $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (strpos(trim($line), '#') === 0) continue;
            list($name, $value) = explode('=', $line, 2);
            $value = trim(trim($value), '"');
            putenv(trim($name) . '=' . $value);
        }
    }
}
load_env_diag_conn();
$backendUrl = getenv('PUBLIC_API_ENDPOINT');

if (empty($backendUrl)) {
    echo "  [FAILURE] PUBLIC_API_ENDPOINT is not set in your .env file.\n";
} else {
    test_url($backendUrl);
}

echo "\n--- End of Diagnostic ---\n";
?>