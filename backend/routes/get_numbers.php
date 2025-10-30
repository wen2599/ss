<?php
// backend/routes/get_numbers.php

function handle_get_numbers($conn) {
    $sql = "SELECT id, number, created_at FROM your_table_name ORDER BY created_at DESC LIMIT 100";
    $result = $conn->query($sql);
    $numbers = [];
    if ($result && $result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $numbers[] = $row;
        }
    }
    echo json_encode($numbers);
}
?>
