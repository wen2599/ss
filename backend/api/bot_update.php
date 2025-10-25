<?php
require_once __DIR__ . '/../bootstrap.php';

$data = json_decode(file_get_contents("php://input"), true);

// --- Bot Authentication ---
if (!isset($data['bot_secret']) || $data['bot_secret'] !== getenv('BOT_SECRET')) {
    http_response_code(401);
    echo json_encode(["status" => "error", "message" => "Unauthorized"]);
    return;
}

if (isset($data['lottery_type']) && isset($data['draw_period']) && isset($data['draw_date']) && isset($data['numbers']) && isset($data['zodiacs']) && isset($data['colors'])) {
    global $db_connection;

    $stmt = $db_connection->prepare(
        "INSERT INTO lottery_draws (lottery_type, draw_period, draw_date, numbers, zodiacs, colors)
         VALUES (?, ?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE
         draw_date = VALUES(draw_date),
         numbers = VALUES(numbers),
         zodiacs = VALUES(zodiacs),
         colors = VALUES(colors)"
    );

    if (!$stmt) {
        http_response_code(500);
        echo json_encode(["message" => "Prepare failed: (" . $db_connection->errno . ") " . $db_connection->error]);
        return;
    }

    $stmt->bind_param("ssssss",
        $data['lottery_type'],
        $data['draw_period'],
        $data['draw_date'],
        $data['numbers'],
        $data['zodiacs'],
        $data['colors']
    );

    if ($stmt->execute()) {
        http_response_code(200);
        echo json_encode(["message" => "Lottery data saved successfully."]);
    } else {
        http_response_code(500);
        echo json_encode(["message" => "Execute failed: (" . $stmt->errno . ") " . $stmt->error]);
    }

    $stmt->close();
    $db_connection->close();
} else {
    http_response_code(400);
    echo json_encode(["message" => "Invalid lottery data."]);
}
