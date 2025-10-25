<?php
require_once __DIR__ . '/../bootstrap.php';

header("Content-Type: application/json; charset=UTF-8");

global $db_connection;

try {
    $stmt = $db_connection->prepare("SELECT draw_number, draw_date, numbers FROM lottery_draws ORDER BY draw_date DESC LIMIT 1");
    if (!$stmt) {
        throw new Exception("Failed to prepare statement: " . $db_connection->error);
    }

    $stmt->execute();
    $result = $stmt->get_result();

    if ($draw = $result->fetch_assoc()) {
        http_response_code(200);
        echo json_encode($draw);
    } else {
        http_response_code(404);
        echo json_encode(["message" => "No lottery draw found"]);
    }
    $stmt->close();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["message" => "An error occurred: " . $e->getMessage()]);
} finally {
    if ($db_connection) {
        $db_connection->close();
    }
}
