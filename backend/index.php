<?php
// index.php
// The central API router.

// All initialization logic is now handled in init.php
require_once __DIR__ . '/init.php';

// The global $log object is now available from init.php

$log->info("--- New API Request ---", ['uri' => $_SERVER['REQUEST_URI'] ?? 'Unknown']);

// --- Security Check: Validate Cloudflare Worker Secret ---
$workerSecret = $_ENV['WORKER_SECRET'] ?? '';
if (!$workerSecret || !isset($_SERVER['HTTP_X_WORKER_SECRET']) || $_SERVER['HTTP_X_WORKER_SECRET'] !== $workerSecret) {
    $log->warning("Forbidden: Invalid or missing X-Worker-Secret header.");
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Forbidden']);
    exit();
}

// --- Routing ---
$action = $_GET['action'] ?? '';

// Whitelist of allowed actions prevents arbitrary file inclusion
$allowedActions = [
    'check_session',
    'delete_bill',
    'email_upload',
    'get_bills',
    'get_game_data',
    'get_lottery_results',
    'is_user_registered',
    'login',
    'logout',
    'process_text',
    'register',
    'update_settlement',
];

if ($action && in_array($action, $allowedActions)) {
    $actionFile = __DIR__ . '/actions/' . $action . '.php';
    if (file_exists($actionFile)) {
        // The global exception handler in init.php will catch any errors within the action file.
        require $actionFile;
    } else {
        $log->error("Action file not found for a whitelisted action.", ['action' => $action]);
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Internal Server Error: Action file missing.']);
    }
} else {
    $log->warning("Unknown or disallowed action requested.", ['action' => $action]);
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Not Found: The requested endpoint does not exist.']);
}

?>