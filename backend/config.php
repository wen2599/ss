<?php
// backend/config.php

// Centralized loader for environment variables.
require_once __DIR__ . '/utils/config_loader.php';

// Define a constant for the base directory to ensure consistent paths.
if (!defined('DIR')) {
    define('DIR', __DIR__);
}

// --- PHP Error Reporting Configuration ---
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', DIR . '/debug.log');
error_reporting(E_ALL);

// --- Helper Scripts Inclusion ---
require_once DIR . '/db_operations.php';
require_once DIR . '/telegram_helpers.php';
require_once DIR . '/user_state_manager.php';
require_once DIR . '/api_curl_helper.php';
require_once DIR . '/gemini_ai_helper.php';
require_once DIR . '/cloudflare_ai_helper.php';
require_once DIR . '/env_manager.php';
