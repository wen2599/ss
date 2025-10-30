<?php
// backend/bootstrap.php

// --- Environment Variable Loading ---
// Simple .env file parser.
function load_env($path) {
    if (!file_exists($path)) {
        return;
    }
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) {
            continue;
        }
        list($name, $value) = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value);
        if (!array_key_exists($name, $_SERVER) && !array_key_exists($name, $_ENV)) {
            putenv(sprintf('%s=%s', $name, $value));
            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
        }
    }
}

// Load .env file from the backend directory
load_env(__DIR__ . '/.env');

// --- Database Connection & Initialization ---

// This function will hold the single PDO instance.
$pdo_connection = null;

function get_db_connection() {
    global $pdo_connection;

    // Return the existing connection if it has already been made.
    if ($pdo_connection !== null) {
        return $pdo_connection;
    }

    $host = getenv('DB_HOST');
    $port = getenv('DB_PORT');
    $db   = getenv('DB_NAME');
    $user = getenv('DB_USER');
    $pass = getenv('DB_PASSWORD');
    $charset = 'utf8mb4';

    $dsn = "mysql:host=$host;port=$port;dbname=$db;charset=$charset";
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    try {
        $pdo_connection = new PDO($dsn, $user, $pass, $options);

        // After connecting, ensure the database is initialized.
        initialize_database($pdo_connection);

        return $pdo_connection;
    } catch (PDOException $e) {
        throw new PDOException($e->getMessage(), (int)$e->getCode());
    }
}

/**
 * Checks if the required table exists and creates it if it doesn't.
 * This makes the application self-initializing.
 *
 * @param PDO $pdo The database connection object.
 */
function initialize_database(PDO $pdo) {
    try {
        // Check if the table exists.
        $pdo->query("SELECT 1 FROM lottery_draws LIMIT 1");
    } catch (PDOException $e) {
        // Table does not exist (error code for 'base table or view not found' is usually 42S02),
        // so we proceed with creation.

        // Let's read the setup file first.
        $sql = @file_get_contents(__DIR__ . '/setup.sql');
        if ($sql === false) {
            // Throw a very specific error if the file can't be read.
            throw new Exception("CRITICAL: setup.sql could not be read. Check file permissions.");
        }

        try {
            // Execute the SQL to create the table.
            $pdo->exec($sql);
        } catch (PDOException $setup_e) {
            // If executing the SQL fails, throw a new, more detailed exception.
            // This will give us the exact database error message.
            $errorInfo = $setup_e->errorInfo;
            $errorMessage = sprintf(
                "Database setup FAILED. SQLSTATE[%s] Driver Error[%s]: %s",
                $errorInfo[0] ?? 'N/A',
                $errorInfo[1] ?? 'N/A',
                $errorInfo[2] ?? $setup_e->getMessage()
            );
            throw new Exception($errorMessage);
        }
    }
}

// --- Global Error Handling & Logging ---

// Ensure the helpers file is included if it hasn't been already,
// so we can use sendJsonResponse in our error handlers.
if (!function_exists('sendJsonResponse')) {
    require_once __DIR__ . '/helpers.php';
}
// Ensure the logging function from bot.php is available.
// We can centralize it here.
if (!function_exists('write_log')) {
    function write_log($message) {
        $log_file = __DIR__ . '/debug.log';
        $timestamp = date("Y-m-d H:i:s");
        $formatted_message = is_string($message) ? $message : print_r($message, true);
        file_put_contents($log_file, "[$timestamp] " . $formatted_message . "\n", FILE_APPEND | LOCK_EX);
    }
}


// Set a global exception handler. This will catch any uncaught exceptions.
set_exception_handler(function($exception) {
    write_log("Uncaught Exception: " . $exception->getMessage() . " in " . $exception->getFile() . " on line " . $exception->getLine());

    // Check if headers have already been sent to avoid "Cannot modify header information" errors.
    if (!headers_sent()) {
        sendJsonResponse(['success' => false, 'message' => 'A critical server error occurred.'], 500);
    }
    exit;
});

// Set an error handler to convert all PHP errors (warnings, notices, etc.) into ErrorExceptions.
set_error_handler(function($severity, $message, $file, $line) {
    // Don't throw exception for errors that are suppressed with @
    if (error_reporting() === 0) {
        return false;
    }
    throw new ErrorException($message, 0, $severity, $file, $line);
});

// Register a shutdown function to catch fatal errors that are not caught by the exception handler.
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error !== null && in_array($error['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR])) {
        $message = "Fatal Error: " . $error['message'] . " in " . $error['file'] . " on line " . $error['line'];
        write_log($message);

        // Manually call the exception handler's logic to send a response.
        if (!headers_sent()) {
            sendJsonResponse(['success' => false, 'message' => 'A critical server error occurred.'], 500);
        }
    }
});
