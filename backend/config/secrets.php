<?php
// backend/config/secrets.php

// --- .env and Environment Variable Loader ---

function get_env($key, $default = null) {
    $value = getenv($key);
    if ($value !== false) {
        return $value;
    }

    static $env = null;
    if ($env === null) {
        $env_path = __DIR__ . '/../../.env'; 
        if (file_exists($env_path)) {
            $env = parse_ini_file($env_path);
        } else {
            $env = [];
        }
    }
    return $env[$key] ?? $default;
}

// --- Static .env Variable Accessors ---

function get_telegram_token() {
    return get_env('TELEGRAM_BOT_TOKEN');
}

function get_telegram_admin_id() {
    return get_env('TELEGRAM_ADMIN_ID');
}

function get_cloudflare_account_id() {
    return get_env('CLOUDFLARE_ACCOUNT_ID');
}

function get_cloudflare_api_token() {
    return get_env('CLOUDFLARE_API_TOKEN');
}

/**
 * Gets the secret key used to validate incoming webhooks from the email worker.
 */
function get_email_webhook_secret() {
    return get_env('EMAIL_WEBHOOK_SECRET');
}

// --- Dynamic Database-driven Secret Accessors ---

/**
 * Gets the Google Gemini API Key from the database.
 */
function get_gemini_api_key($conn) {
    $sql = "SELECT setting_value FROM system_settings WHERE setting_key = 'GEMINI_API_KEY' LIMIT 1";
    $result = $conn->query($sql);
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        return $row['setting_value'];
    }
    return null; 
}

/**
 * Updates a system setting in the database.
 */
function update_system_setting($conn, $key, $value) {
    $sql = "UPDATE system_settings SET setting_value = ? WHERE setting_key = ?";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('ss', $value, $key);
    $success = $stmt->execute();
    $stmt->close();
    return $success;
}

?>
