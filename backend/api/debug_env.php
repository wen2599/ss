<?php
// backend/api/debug_env.php

// Set headers for clear JSON output
header('Content-Type: application/json');

// Include the loader we want to test
require_once __DIR__ . '/../env_loader.php';

// This is the core test: call the function and see what it returns.
$loaded_vars = load_env();

$file_path = __DIR__ . '/../.env';

// Prepare a report
$report = [
    'test_description' => 'This script checks if env_loader.php can correctly read the .env file.',
    'env_file_path_checked' => $file_path,
    'file_exists' => file_exists($file_path),
    'is_readable' => is_readable($file_path),
    'variables_loaded' => !empty($loaded_vars),
    'loaded_data' => $loaded_vars
];

// Print the report as JSON
echo json_encode($report, JSON_PRETTY_PRINT);

