<?php
header('Access-Control-Allow-Origin: https://ss.wenxiuxiu.eu.org');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

try {
    require_once 'config.php';
    $db = new Database();
    $pdo = $db->getConnection();
    
    // 测试查询
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM lottery_results");
    $count = $stmt->fetch()['count'];
    
    echo json_encode([
        'success' => true,
        'message' => 'Database connection successful!',
        'record_count' => $count,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Database connection failed: ' . $e->getMessage()
    ]);
}
?>