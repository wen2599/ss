<?php
// backend/routes/post_telegram.php

function handle_post_telegram($conn, $data) {
    if (!isset($data['number'])) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Missing \'number\' field.']);
        return;
    }

    $number = $conn->real_escape_string($data['number']);
    $sql = "INSERT INTO your_table_name (number) VALUES (?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $number);

    if ($stmt->execute()) {
        echo json_encode(['status' => 'success', 'message' => 'Number saved.']);
    } else {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Failed to save number: ' . $stmt->error]);
    }
    $stmt->close();
}
?>
