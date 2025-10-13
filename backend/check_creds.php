<?php
// This is a temporary diagnostic script.
// It will be deleted after the issue is resolved.

// Load the same configuration as the main application.
require_once __DIR__ . '/config.php';

echo "--- Cloudflare Credential Check ---\n\n";

$accountId = $_ENV['CLOUDFLARE_ACCOUNT_ID'] ?? null;
$apiToken = $_ENV['CLOUDFLARE_API_TOKEN'] ?? null;

if (!empty($accountId)) {
    echo "[SUCCESS] CLOUDFLARE_ACCOUNT_ID is loaded.\n";
} else {
    echo "[FAILURE] CLOUDFLARE_ACCOUNT_ID is NOT loaded. Please check your .env file.\n";
}

if (!empty($apiToken)) {
    echo "[SUCCESS] CLOUDFLARE_API_TOKEN is loaded.\n";
} else {
    echo "[FAILURE] CLOUDFLARE_API_TOKEN is NOT loaded. Please check your .env file.\n";
}

echo "\n--- End of Check ---\n";
?>