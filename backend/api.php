<?php
// Health Check api.php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); // Temporarily open CORS for testing
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

echo json_encode(['status' => 'ok', 'message' => 'Backend is reachable']);
exit;
