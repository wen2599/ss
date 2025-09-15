<?php
// backend/api/get_bet_history.php
require_once 'config.php';
require_once 'db_connect.php';
header('Content-Type: application/json');

session_set_cookie_params(['samesite' => 'None', 'secure' => true]);
session_start();

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'You must be logged in to view your bet history.']);
    exit;
}
$user_id = $_SESSION['user_id'];

$conn = db_connect();
if (!$conn) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection failed.']);
    exit;
}

try {
    $stmt = $conn->prepare("SELECT id, numbers, period, bet_time, lottery_type, settled, winnings FROM bets WHERE user_id = ? ORDER BY bet_time DESC");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $bet_history = [];
    while($row = $result->fetch_assoc()) {
        $bet_history[] = $row;
    }

    echo json_encode(['success' => true, 'data' => $bet_history]);
    $stmt->close();

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database query failed.']);
}

$conn->close();
?>
