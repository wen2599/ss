<?php
// Simplified for absolute minimum functionality to diagnose the server environment.
header('Content-Type: application/json');
echo json_encode(['status' => 'ok', 'message' => 'Backend is reachable.']);
?>