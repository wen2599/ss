<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "Starting debug for config.php...<br>";

try {
    require_once 'config.php';
    echo "config.php was included successfully.<br>";
    echo "Script finished without fatal errors.<br>";
} catch (Throwable $e) {
    echo "A throwable error was caught: <pre>" . htmlspecialchars($e) . "</pre>";
}
