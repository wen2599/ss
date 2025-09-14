<?php
// backend/api/get_latest_draw.php
require_once 'config.php';
header('Content-Type: application/json');

try {
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);

    $stmt = $pdo->query("SELECT period, winning_numbers, draw_time FROM draws ORDER BY draw_time DESC LIMIT 1");
    $latest_draw = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($latest_draw) {
        echo json_encode(['success' => true, 'data' => $latest_draw]);
    } else {
        echo json_encode(['success' => false, 'message' => 'No draw data found.']);
    }

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
