<?php
// backend/bootstrap.php

// --- Error Reporting & Logging ---
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../backend.log');

// --- Centralized Logging Function ---
if (!function_exists('write_log')) {
    function write_log($message) {
        $log_path = __DIR__ . '/../backend.log';
        $timestamp = date('Y-m-d H:i:s');
        if (!is_string($message)) {
            $message = print_r($message, true);
        }
        // Use @ to suppress errors if logging fails (e.g., file permissions)
        @file_put_contents($log_path, "[{$timestamp}] " . $message . "\n", FILE_APPEND);
    }
}

// --- Environment Variable Loading ---
if (!function_exists('load_env')) {
    function load_env($path)
    {
        if (!file_exists($path)) {
            write_log("Warning: .env file not found at path: {$path}");
            return;
        }
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            write_log("Warning: Could not read .env file at path: {$path}");
            return;
        }
        foreach ($lines as $line) {
            if (strpos(trim($line), '#') === 0) {
                continue;
            }
            list($name, $value) = explode('=', $line, 2);
            $name = trim($name);
            $value = trim($value);
            if (!array_key_exists($name, $_SERVER) && !array_key_exists($name, $_ENV)) {
                putenv(sprintf('%s=%s', $name, $value));
                $_ENV[$name] = $value;
                $_SERVER[$name] = $value;
                // Log the loaded environment variable
                if ($name === 'TELEGRAM_WEBHOOK_SECRET') {
                    write_log("Loaded env var: {$name} = [SECRET_VALUE_LOADED]");
                } else {
                    write_log("Loaded env var: {$name} = {$value}");
                }
            }
        }
    }
}

// Load .env file
load_env(__DIR__ . '/../.env');

// --- Standardized JSON Response Function ---
function json_response($status, $data = null, $http_code = 200) {
    http_response_code($http_code);
    header('Content-Type: application/json; charset=utf-8');
    $response = ['status' => $status];
    if ($data !== null) {
        if ($status === 'error') {
            $response['message'] = $data;
        } else {
            $response['data'] = $data;
        }
    }
    echo json_encode($response);
    exit;
}

// --- Global Exception Handler ---
set_exception_handler(function($exception) {
    // Log the exception to the main log file
    write_log("--- UNCAUGHT EXCEPTION ---");
    write_log("Message: " . $exception->getMessage());
    write_log("File: " . $exception->getFile() . " on line " . $exception->getLine());
    write_log("Trace: " . $exception->getTraceAsString());
    write_log("--------------------------");

    // Send a generic error response to the client
    json_response('error', 'An unexpected internal server error occurred.', 500);
});

// --- Fatal Error Handler ---
register_shutdown_function(function () {
    $error = error_get_last();
    if ($error !== null && in_array($error['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR])) {
        // Log the fatal error
        write_log("--- FATAL ERROR ---");
        write_log("Type: " . $error['type']);
        write_log("Message: " . $error['message']);
        write_log("File: " . $error['file'] . " on line " . $error['line']);
        write_log("---------------------");

        // Check if headers have already been sent to avoid "headers already sent" error
        if (!headers_sent()) {
            // Send a generic error response to the client
            json_response('error', 'A critical internal server error occurred.', 500);
        }
    }
});

// --- Session Management ---
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', isset($_SERVER['HTTPS']));
ini_set('session.cookie_samesite', 'Lax');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// --- Database Connection ---
require_once __DIR__ . '/db_operations.php';

// --- API Header Logic ---
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS, DELETE");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With, X-Telegram-Bot-Api-Secret-Token");

// Handle pre-flight OPTIONS requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    json_response('success', 'Pre-flight check successful.');
}

// --- Include all helper functions ---
require_once __DIR__ . '/api_curl_helper.php';
require_once __DIR__ . '/cloudflare_ai_helper.php';
require_once __DIR__ . '/gemini_ai_helper.php';
require_once __DIR__ . '/telegram_helpers.php';
require_once __DIR__ . '/user_state_manager.php';
require_once __DIR__ . '/email_handler.php';
require_once __DIR__ . '/process_email_ai.php';


write_log("Bootstrap finished for: " . basename($_SERVER['SCRIPT_NAME']));
