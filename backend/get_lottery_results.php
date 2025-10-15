<?php
require_once __DIR__ . '/api_header.php';

$log_file = __DIR__ . '/lottery_api.log';
function write_log($message) {
    global $log_file;
    file_put_contents($log_file, date('[Y-m-d H:i:s]') . ' ' . $message . PHP_EOL, FILE_APPEND);
}

write_log("--- [API CALL STARTED] ---");

try {
    $pdo = get_db_connection();
    $lotteryType = $_GET['type'] ?? null;
    write_log("Request received for type: " . ($lotteryType ?? 'None'));

    $sql = "SELECT lottery_type, issue_number, winning_numbers, zodiac_signs, colors, drawing_date
            FROM lottery_results";

    if ($lotteryType) {
        $sql .= " WHERE lottery_type = :lottery_type ORDER BY id DESC LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':lottery_type' => $lotteryType]);
    } else {
        // If no type is specified, get the absolute latest result of any type
        $sql .= " ORDER BY id DESC LIMIT 1";
        $stmt = $pdo->query($sql);
    }

    write_log("Executing SQL: " . $sql);
    if ($lotteryType) {
        write_log("With parameter: " . $lotteryType);
    }

    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    write_log("Database result: " . ($result ? json_encode($result) : 'No result found.'));

    if ($result) {
        // Explode comma-separated strings into arrays for easier frontend consumption
        $result['winning_numbers'] = explode(',', $result['winning_numbers']);
        $result['zodiac_signs'] = explode(',', $result['zodiac_signs']);
        $result['colors'] = explode(',', $result['colors']);
        echo json_encode(['status' => 'success', 'data' => $result]);
    } else {
        // No results found for the given type, or table is empty
        echo json_encode(['status' => 'success', 'data' => null]);
    }

} catch (PDOException $e) {
    write_log("DATABASE ERROR: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database error while fetching lottery results.']);
}
?>