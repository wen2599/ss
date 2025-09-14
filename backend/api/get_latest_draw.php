<?php
// backend/api/get_latest_draw.php
require_once 'config.php';
header('Content-Type: application/json');

try {
    if (!file_exists(DB_PATH)) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database does not exist. Please run db_init.php']);
        exit;
    }

    $pdo = new PDO('sqlite:' . DB_PATH);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

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
