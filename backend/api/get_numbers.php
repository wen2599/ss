<?php
header("Content-Type: application/json");

$data_file = __DIR__ . '/../data/numbers.json';

if (file_exists($data_file)) {
    $numbers = file_get_contents($data_file);
    echo $numbers;
} else {
    echo json_encode(['error' => 'Data file not found.']);
}
?>