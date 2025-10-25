<?php

// --- Production-Grade Error Handling ---
// This is the first thing to run to catch any and all errors during startup.
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

echo json_encode(['status' => 'success', 'message' => 'PHP is working correctly and can start without fatal errors.']);
