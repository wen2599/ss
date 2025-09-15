<?php
// backend/api/place_bet_from_email.php
require_once 'config.php';
require_once 'db_connect.php';
header('Content-Type: application/json');

// This script is called by the Cloudflare Worker.
// It should be protected by a secret key to prevent unauthorized access.
$request_secret = $_SERVER['HTTP_X_WORKER_SECRET'] ?? '';

if ($request_secret !== WORKER_SECRET) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid JSON.']);
    exit;
}

$from_email = $data['from_email'] ?? null;
$bet_text = $data['bet_text'] ?? null;

if (empty($from_email) || empty($bet_text)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'From email and bet text are required.']);
    exit;
}

// Extract username from email
$username = strstr($from_email, '@', true);

$conn = db_connect();
if (!$conn) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection failed.']);
    exit;
}

try {
    // Get user_id from username
    $stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    if (!$user) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'User not found.']);
        exit;
    }
    $user_id = $user['id'];

    // TODO: Implement a more robust parser for the bet text.
    // The current parser assumes the format: <lottery_type> <number1> <number2> ...
    // It also assumes that the lottery type is either one or two words.
    // A more robust parser would handle different formats and variations in the input.
    $parts = explode(' ', $bet_text);
    $lottery_type = '';
    $numbers = [];

    if (count($parts) >= 2 && in_array($parts[0] . ' ' . $parts[1], ['Xin Ao', 'Lao Ao', 'Gang Cai'])) {
        $lottery_type = $parts[0] . ' ' . $parts[1];
        $numbers = array_slice($parts, 2);
    } else {
        $lottery_type = $parts[0];
        $numbers = array_slice($parts, 1);
    }
    $numbers_str = implode(',', $numbers);

    // Get the latest draw period for the specified lottery type
    $stmt = $conn->prepare("SELECT period FROM draws WHERE lottery_type = ? ORDER BY draw_time DESC LIMIT 1");
    $stmt->bind_param("s", $lottery_type);
    $stmt->execute();
    $result = $stmt->get_result();
    $latest_draw = $result->fetch_assoc();

    if (!$latest_draw) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'No active draw period found for this lottery type.']);
        $conn->close();
        exit;
    }
    $period = $latest_draw['period'];

    // Insert the bet into the database
    $stmt = $conn->prepare("INSERT INTO bets (user_id, numbers, period, lottery_type) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("isss", $user_id, $numbers_str, $period, $lottery_type);
    $stmt->execute();

    echo json_encode(['success' => true, 'message' => 'Bet placed successfully from email!']);
    $stmt->close();

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database query failed.']);
}

$conn->close();
?>
