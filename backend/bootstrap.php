<?php
// backend/bootstrap.php

// --- Error Reporting & Logging ---
ini_set('display_errors', 0);
ini_set('log_errors', 1);
$log_path = __DIR__ . '/../backend.log';
ini_set('error_log', $log_path);

// --- Centralized Logging Function ---
if (!function_exists('write_log')) {
    function write_log($message) {
        global $log_path;
        $timestamp = date('Y-m-d H:i:s');
        if (!is_string($message)) {
            $message = print_r($message, true);
        }
        @file_put_contents($log_path, "[{$timestamp}] " . $message . "\n", FILE_APPEND);
    }
}

// --- Environment Variable Loading ---
if (!function_exists('load_env')) {
    function load_env($path) {
        if (!file_exists($path)) { return; }
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) { return; }
        foreach ($lines as $line) {
            if (strpos(trim($line), '#') === 0) { continue; }
            list($name, $value) = explode('=', $line, 2);
            $name = trim($name);
            $value = trim($value);
            if (!array_key_exists($name, $_SERVER) && !array_key_exists($name, $_ENV)) {
                putenv(sprintf('%s=%s', $name, $value));
                $_ENV[$name] = $value;
                $_SERVER[$name] = $value;
            }
        }
    }
}

// Load .env file from the project root
load_env(__DIR__ . '/../.env');

// --- Standardized JSON Response Function ---
if (!function_exists('json_response')) {
    function json_response($status, $data = null, $http_code = 200) {
        http_response_code($http_code);
        header('Content-Type: application/json; charset=utf-8');
        $response = ['status' => $status];
        if ($data !== null) {
            $response[$status === 'error' ? 'message' : 'data'] = $data;
        }
        echo json_encode($response);
        exit;
    }
}

// --- API Header Logic ---
// Moved up to handle pre-flight requests before potential fatal errors.
header("Access-Control-Allow-Origin: https://ss.wenxiuxiu.eu.org");
header("Access-Control-Allow-Credentials: true");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS, DELETE");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With, X-Telegram-Bot-Api-Secret-Token");

// Handle pre-flight OPTIONS requests immediately
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    json_response('success', 'Pre-flight check successful.');
}

// --- Global Exception & Error Handlers ---
set_exception_handler(function($exception) {
    write_log(
        "--- UNCAUGHT EXCEPTION ---\n" .
        "Message: " . $exception->getMessage() . "\n" .
        "File: " . $exception->getFile() . " on line " . $exception->getLine() . "\n" .
        "Trace: " . $exception->getTraceAsString() .
        "\n--------------------------"
    );
    json_response('error', 'An unexpected internal server error occurred.', 500);
});

register_shutdown_function(function () {
    $error = error_get_last();
    if ($error !== null && in_array($error['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR])) {
        write_log(
            "--- FATAL ERROR ---\n" .
            "Type: " . $error['type'] . "\n" .
            "Message: " . $error['message'] . "\n" .
            "File: " . $error['file'] . " on line " . $error['line'] .
            "\n---------------------"
        );
        if (!headers_sent()) {
            json_response('error', 'A critical internal server error occurred.', 500);
        }
    }
});

// --- Session Management ---
// Settings must be set before session_start()
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 1); // Enforce secure cookies for cross-site
ini_set('session.cookie_samesite', 'None'); // Required for cross-site cookies

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// --- Database Connection ---
require_once __DIR__ . '/db_operations.php';

// --- Include all helper functions ---
require_once __DIR__ . '/api_curl_helper.php';
require_once __DIR__ . '/cloudflare_ai_helper.php';
require_once __DIR__ . '/gemini_ai_helper.php';
require_once __DIR__ . '/telegram_helpers.php';
require_once __DIR__ . '/user_state_manager.php';
require_once __DIR__ . '/email_handler.php';
require_once __DIR__ . '/process_email_ai.php';

write_log("Bootstrap finished for: " . ($_SERVER['REQUEST_URI'] ?? 'unknown_request'));
?>