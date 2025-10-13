<?php
// --- Standalone Command-Line Diagnostic Tool for Cloudflare API ---

echo "--- Starting Cloudflare API Diagnostic Test ---\n\n";

// --- Step 1: Load Configuration ---
echo "Step 1: Loading core configuration and helpers...\n";
try {
    // Replicate the application's environment loading
    function load_env_diag_cf() {
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
    load_env_diag_cf();

    // Include the necessary helpers
    require_once __DIR__ . '/api_curl_helper.php';
    require_once __DIR__ . '/cloudflare_ai_helper.php';
    echo "  [SUCCESS] Core components loaded.\n\n";
} catch (Throwable $t) {
    echo "  [FATAL] A fatal error occurred while including files.\n";
    echo "  Error: " . $t->getMessage() . "\n";
    exit(1);
}

// --- Step 2: Check Credential Accessibility ---
echo "Step 2: Checking if getenv() can access the credentials...\n";
$accountId = getenv('CLOUDFLARE_ACCOUNT_ID');
$apiToken = getenv('CLOUDFLARE_API_TOKEN');

if ($accountId) {
    echo "  [SUCCESS] CLOUDFLARE_ACCOUNT_ID is accessible.\n";
} else {
    echo "  [FAILURE] CLOUDFLARE_ACCOUNT_ID is NOT accessible.\n";
}
if ($apiToken) {
    echo "  [SUCCESS] CLOUDFLARE_API_TOKEN is accessible.\n\n";
} else {
    echo "  [FAILURE] CLOUDFLARE_API_TOKEN is NOT accessible.\n\n";
}

if (!$accountId || !$apiToken) {
    echo "  [CONCLUSION] The script cannot read the credentials. Please check the .env file and file permissions.\n";
    exit(1);
}

// --- Step 3: Attempt Real API Call ---
echo "Step 3: Attempting a real API call to Cloudflare AI...\n";
$prompt = "Hello, world!";
echo "  - Sending prompt: '{$prompt}'\n";

$response = call_cloudflare_ai_api($prompt);

echo "  - Received response from Cloudflare:\n";
echo "----------------------------------------\n";
print_r($response);
echo "\n----------------------------------------\n\n";

// --- Step 4: Final Conclusion ---
echo "Step 4: Conclusion\n";
if (strpos($response, '❌') !== false) {
    echo "  [FAILURE] The API call failed. Since we confirmed the credentials are being loaded, this proves the Account ID or API Token in your .env file are incorrect, expired, or lack the required permissions.\n";
} else {
    echo "  [SUCCESS] The API call was successful! This means your credentials are correct and the command-line environment is working. The issue likely lies with how the web server process (e.g., Apache or Nginx) is loading the environment variables.\n";
}

echo "\n--- End of Diagnostic ---\n";
?>