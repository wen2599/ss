<?php
// backend/api/get_logs.php

session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401); // Unauthorized
    echo json_encode(['success' => false, 'message' => 'Authentication required.']);
    exit();
}

// === 错误报告设置 ===
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// === 设置响应内容类型为 JSON ===
header('Content-Type: application/json');

require_once __DIR__ . '/database.php';

$response = ['success' => false, 'message' => 'An unknown error occurred.'];

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $pdo = getDbConnection();
        $user_id = $_SESSION['user_id'];

        $stmt = $pdo->prepare("SELECT id, filename, parsed_data, created_at FROM chat_logs WHERE user_id = :user_id ORDER BY created_at DESC");
        $stmt->execute([':user_id' => $user_id]);
        $logs = $stmt->fetchAll();

        // The parsed_data is stored as a JSON string, so we need to decode it
        foreach ($logs as &$log) {
            $log['parsed_data'] = json_decode($log['parsed_data']);
        }

        $response = [
            'success' => true,
            'data' => $logs
        ];

    } catch (PDOException $e) {
        $response['message'] = 'Failed to retrieve logs from the database.';
        http_response_code(500);
    }
} else {
    $response['message'] = 'Only GET requests are accepted.';
    http_response_code(405); // Method Not Allowed
}

echo json_encode($response);
?>
