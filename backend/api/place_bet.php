<?php
// backend/api/place_bet.php
require_once 'config.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Only POST method is allowed.']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid JSON.']);
    exit;
}

$numbers = $data['numbers'] ?? null;

// --- Validation ---
if (!$numbers || !is_array($numbers) || count($numbers) !== 6) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'You must select exactly 6 numbers.']);
    exit;
}

foreach ($numbers as $num) {
    if (!is_int($num) || $num < 1 || $num > 49) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid number selection. Numbers must be between 1 and 49.']);
        exit;
    }
}

try {
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);

    // Get the latest draw period
    $stmt = $pdo->query("SELECT period FROM draws ORDER BY draw_time DESC LIMIT 1");
    $latest_draw = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$latest_draw) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'No active draw period found.']);
        exit;
    }
    $period = $latest_draw['period'];

    // For now, use a hardcoded user_id
    $user_id = 'user123';
    $numbers_str = implode(',', $numbers);

    $sql = "INSERT INTO bets (user_id, numbers, period) VALUES (:user_id, :numbers, :period)";
    $stmt = $pdo->prepare($sql);

    $stmt->bindParam(':user_id', $user_id);
    $stmt->bindParam(':numbers', $numbers_str);
    $stmt->bindParam(':period', $period);

    $stmt->execute();

    echo json_encode(['success' => true, 'message' => 'Bet placed successfully!']);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
