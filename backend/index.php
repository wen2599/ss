<?php

// --- Main API Router ---

// Bootstrap the application
require_once __DIR__ . '/config.php';

// Set global headers for JSON responses
header('Content-Type: application/json');

// --- Routing Logic ---
$requestUri = $_SERVER['REQUEST_URI'];
$path = parse_url($requestUri, PHP_URL_PATH);
$endpoint = $_GET['endpoint'] ?? null; // For query string-based routing

// Priority 1: Check for `endpoint` query parameter
if ($endpoint) {
    $filePath = __DIR__ . '/' . basename($endpoint) . '.php';
    if (file_exists($filePath)) {
        require $filePath;
        exit;
    }
}

// Priority 2: Check for path-based routing (e.g., /api/status)
// This is more for a modern API structure.
$api_path = str_replace('/api/', '', $path);

switch ($api_path) {
    case 'status':
        echo json_encode(['status' => 'ok', 'message' => 'API is running']);
        break;

    case 'db-check':
        // The database connection is now handled by get_db_connection() from db_operations.php
        $pdo = get_db_connection();
        if ($pdo) {
            echo json_encode(['status' => 'ok', 'message' => '数据库连接成功!']);
        } else {
            // get_db_connection logs the detailed error, so we send a generic one here.
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => '数据库连接失败.']);
        }
        break;

    // --- Deprecated Path-Based Routes ---
    // These are kept for compatibility but should be migrated to query string endpoints.
    case 'telegram_webhook':
        require __DIR__ . '/telegramWebhook.php';
        break;

    case 'email_upload':
        require __DIR__ . '/email_handler.php';
        break;

    default:
        // Handle 404 Not Found for any other API routes
        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'Endpoint not found']);
        break;
}