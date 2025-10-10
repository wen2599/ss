<?php

// --- Ultimate Dependency Debugger ---
// This script will require files one by one to find the exact point of failure.
header('Content-Type: text/plain; charset=utf-8');
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "--- STARTING DEPENDENCY TEST ---\n\n";

try {
    echo "[TESTING] Requiring: Response.php\n";
    require_once __DIR__ . '/../src/core/Response.php';
    echo "[SUCCESS] Response.php loaded successfully.\n\n";

    echo "[TESTING] Requiring: DotEnv.php (dependency for config.php)\n";
    require_once __DIR__ . '/../src/core/DotEnv.php';
    echo "[SUCCESS] DotEnv.php loaded successfully.\n\n";

    echo "[TESTING] Requiring: config.php (This is the most likely point of failure)\n";
    require_once __DIR__ . '/../src/config.php';
    echo "[SUCCESS] config.php loaded successfully.\n\n";

    echo "[TESTING] Requiring: Database.php\n";
    require_once __DIR__ . '/../src/core/Database.php';
    echo "[SUCCESS] Database.php loaded successfully.\n\n";

    echo "[TESTING] Requiring: Telegram.php\n";
    require_once __DIR__ . '/../src/core/Telegram.php';
    echo "[SUCCESS] Telegram.php loaded successfully.\n\n";

    echo "--- ALL CORE FILES LOADED SUCCESSFULLY ---";

} catch (Throwable $e) {
    echo "\n\n--- A FATAL ERROR OCCURRED ---\n";
    echo "Error Type: " . get_class($e) . "\n";
    echo "Message: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
    echo "-------------------------------------\n";
}