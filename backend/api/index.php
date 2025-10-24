<?php
declare(strict_types=1);

// Attempt to load the bootstrap file
require_once __DIR__ . '/bootstrap.php';

// If we reached this point, the bootstrap file was loaded without a fatal error.
header('Content-Type: application/json');
echo json_encode(['status' => 'ok', 'message' => 'bootstrap.php loaded successfully.']);
