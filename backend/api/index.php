<?php

header('Content-Type: application/json');

// This is the simplest possible PHP script.
// It does not use any classes, controllers, or external files.

$response = [
    'status' => 'ok',
    'message' => 'This is a minimal PHP script running without any classes.',
    'php_version' => phpversion()
];

echo json_encode($response);

// All class-based logic has been removed to prove the server environment itself is the issue.
