<?php
require_once __DIR__ . '/bootstrap.php';

header('Content-Type: text/plain');

echo "Checking environment variables...\n";

$telegramWebhookSecret = getenv('TELEGRAM_WEBHOOK_SECRET');

if ($telegramWebhookSecret) {
    echo "TELEGRAM_WEBHOOK_SECRET is set: [VALUE_LOADED]\n";
} else {
    echo "TELEGRAM_WEBHOOK_SECRET is NOT set or empty.\n";
}

$adminId = getenv('TELEGRAM_ADMIN_ID');
if ($adminId) {
    echo "TELEGRAM_ADMIN_ID is set: {$adminId}\n";
} else {
    echo "TELEGRAM_ADMIN_ID is NOT set or empty.\n";
}

$backendUrl = getenv('BACKEND_URL');
if ($backendUrl) {
    echo "BACKEND_URL is set: {$backendUrl}\n";
} else {
    echo "BACKEND_URL is NOT set or empty.\n";
}

echo "\nRaw $_ENV contents:\n";
print_r($_ENV);

echo "\nRaw $_SERVER contents (relevant parts):\n";
foreach ($_SERVER as $key => $value) {
    if (strpos($key, 'TELEGRAM') !== false || strpos($key, 'BACKEND') !== false || strpos($key, 'SECRET') !== false || strpos($key, 'ENV') !== false) {
        echo "{$key} = {$value}\n";
    }
}

?>