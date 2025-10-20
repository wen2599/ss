<?php
// backend/check_webhook.php
header('Content-Type: text/plain; charset=utf-8');
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/telegram_helpers.php';

echo "--- Telegram Webhook Diagnostic Script ---\n\n";

// --- Configuration Loading ---
$botToken = getenv('TELEGRAM_BOT_TOKEN');
$webhookSecret = getenv('TELEGRAM_WEBHOOK_SECRET');
$backendUrl = getenv('BACKEND_URL'); // e.g., 'https://your.domain.com/backend'

if (empty($botToken) || empty($backendUrl) || empty($webhookSecret)) {
    echo "[FATAL] Missing one or more required environment variables in .env file:\n";
    echo " - TELEGRAM_BOT_TOKEN\n";
    echo " - TELEGRAM_WEBHOOK_SECRET\n";
    echo " - BACKEND_URL\n";
    exit;
}

// Construct the full webhook URL, routing through index.php
$webhookUrl = rtrim($backendUrl, '/') . '/index.php?endpoint=telegram_webhook';
echo "Generated Webhook URL:\n" . $webhookUrl . "\n\n";

// --- Action Handling ---
$action = $_GET['action'] ?? 'status'; // Default action is to check status

if ($action === 'set') {
    echo "--- ACTION: SET WEBHOOK ---\n";
    echo "Registering webhook with Telegram...\n";

    $result = setTelegramWebhook($webhookUrl, $webhookSecret);

    if ($result['ok']) {
        echo "\n[SUCCESS] Webhook was set successfully!\n";
        echo "Telegram's Response: " . $result['description'] . "\n";
    } else {
        echo "\n[FAILURE] Failed to set webhook.\n";
        echo "Error Code: " . ($result['error_code'] ?? 'N/A') . "\n";
        echo "Description: " . ($result['description'] ?? 'No description provided.') . "\n";
        echo "\nDebugging Tips:\n";
        echo "1. Verify your TELEGRAM_BOT_TOKEN is correct.\n";
        echo "2. Ensure the BACKEND_URL is publicly accessible and points to your server's backend directory.\n";
        echo "3. Check for firewall or DNS issues that might prevent Telegram from reaching your server.\n";
    }
} elseif ($action === 'status') {
    echo "--- ACTION: GET WEBHOOK STATUS ---\n";
    echo "Fetching webhook information from Telegram...\n";

    $info = getTelegramWebhookInfo();

    if ($info && $info['ok']) {
        $webhook = $info['result'];
        if (empty($webhook['url'])) {
            echo "\n[INFO] No webhook is currently set for this bot.\n";
            echo "To set it, browse to this script with `?action=set` in the URL.\n";
        } else {
            echo "\n[SUCCESS] Found an active webhook configuration:\n";
            echo "  - URL: " . $webhook['url'] . "\n";
            echo "  - Has Custom Certificate: " . ($webhook['has_custom_certificate'] ? 'Yes' : 'No') . "\n";
            echo "  - Pending Update Count: " . $webhook['pending_update_count'] . "\n";
            echo "  - Max Connections: " . ($webhook['max_connections'] ?? 'N/A') . "\n";
            if (isset($webhook['ip_address'])) {
                echo "  - IP Address: " . $webhook['ip_address'] . "\n";
            }
            if (isset($webhook['last_error_date'])) {
                echo "  - Last Error Date: " . date('Y-m-d H:i:s', $webhook['last_error_date']) . "\n";
                echo "  - Last Error Message: " . $webhook['last_error_message'] . "\n";
            }
            echo "\nVerification:\n";
            if ($webhook['url'] === $webhookUrl) {
                echo "  - URL Check: OK! The URL matches your .env configuration.\n";
            } else {
                echo "  - URL Check: WARNING! The URL set with Telegram does NOT match your .env configuration.\n";
                echo "    Telegram has: " . $webhook['url'] . "\n";
                echo "    You expect:   " . $webhookUrl . "\n";
                echo "    Consider running `?action=set` to correct it.\n";
            }
        }
    } else {
        echo "\n[FAILURE] Could not retrieve webhook information.\n";
        echo "Description: " . ($info['description'] ?? 'No description provided.') . "\n";
    }
} else {
    echo "[ERROR] Invalid action specified. Use `?action=set` or `?action=status`.\n";
}

echo "\n--- DIAGNOSTIC COMPLETE ---\n";
