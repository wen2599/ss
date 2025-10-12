<?php

// --- Temporary Script to Add BACKEND_PUBLIC_URL to .env ---

$envPath = __DIR__ . '/.env';
$keyToAdd = 'BACKEND_PUBLIC_URL';
$valueToAdd = 'https://wenge.cloudns.ch';

echo "--- Checking .env file for {$keyToAdd} ---\n";

if (!file_exists($envPath) || !is_readable($envPath) || !is_writable($envPath)) {
    echo "[FAILURE] The .env file at {$envPath} is missing or not readable/writable.\n";
    exit(1);
}

$content = file_get_contents($envPath);

if (strpos($content, "{$keyToAdd}=") !== false) {
    echo "  [INFO] The key '{$keyToAdd}' already exists in the .env file. No changes made.\n";
} else {
    echo "  [ACTION] The key '{$keyToAdd}' was not found. Appending it to the .env file...\n";
    $newLine = "\n{$keyToAdd}=\"{$valueToAdd}\"\n";
    if (file_put_contents($envPath, $newLine, FILE_APPEND)) {
        echo "  [SUCCESS] Successfully added the BACKEND_PUBLIC_URL to your .env file.\n";
    } else {
        echo "  [FAILURE] Failed to write to the .env file. Please check file permissions.\n";
        exit(1);
    }
}

echo "\n--- Script finished. You may now run set_webhook.php again. ---\n";

?>