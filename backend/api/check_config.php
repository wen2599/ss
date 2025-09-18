<?php
// backend/api/check_config.php
// An improved health-check endpoint to verify server configuration via .env file.

header('Content-Type: application/json');

$response = [
    'status' => 'OK',
    'checks' => []
];

// --- 1. Check for .env file ---
$env_path = __DIR__ . '/../.env';
if (!is_readable($env_path)) {
    $response['status'] = 'ERROR';
    $response['checks']['env_file'] = [
        'status' => 'FAILED',
        'message' => 'The .env file is missing or not readable. Please copy .env.example to .env in the `backend/` directory and fill in your credentials.'
    ];
    http_response_code(500);
    echo json_encode($response, JSON_PRETTY_PRINT);
    exit();
}
$response['checks']['env_file'] = ['status' => 'OK', 'message' => '.env file found and is readable.'];

// --- 2. Load config, which loads .env ---
// The new config.php handles the loading. If it fails, it will exit and return a JSON error.
require_once __DIR__ . '/config.php';
$response['checks']['config_file'] = ['status' => 'OK', 'message' => 'config.php loaded successfully.'];

// --- 3. Check for required environment variables (via constants) ---
$required_constants = [
    'DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASS',
    'TELEGRAM_BOT_TOKEN', 'TELEGRAM_CHANNEL_ID', 'TELEGRAM_SUPER_ADMIN_ID'
];
$all_vars_set = true;

foreach ($required_constants as $const) {
    if (!defined($const) || empty(constant($const))) {
        $response['checks']['env_variable_' . $const] = [
            'status' => 'FAILED',
            'message' => "The required environment variable '$const' is missing or empty in your .env file."
        ];
        $all_vars_set = false;
    } else {
        // For security, don't show the actual value, just that it's set.
        $response['checks']['env_variable_' . $const] = ['status' => 'OK', 'message' => "Variable '$const' is set."];
    }
}

if (!$all_vars_set) {
    $response['status'] = 'ERROR';
    $response['overall_message'] = 'One or more required environment variables are missing from your .env file. Please check the details above.';
    http_response_code(500);
    echo json_encode($response, JSON_PRETTY_PRINT);
    exit();
}

// --- 4. Check Database Connection ---
try {
    // Use the constants defined in config.php
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    $response['checks']['database_connection'] = [
        'status' => 'OK',
        'message' => 'Successfully connected to the database.'
    ];
} catch (PDOException $e) {
    $response['status'] = 'ERROR';
    $response['checks']['database_connection'] = [
        'status' => 'FAILED',
        // Provide a more helpful message without leaking too much info
        'message' => 'Failed to connect to the database. Please double-check your DB_HOST, DB_NAME, DB_USER, and DB_PASS values in the .env file.'
        // 'debug_error' => $e->getMessage() // Avoid exposing this in production
    ];
    $response['overall_message'] = 'The database credentials in your .env file appear to be incorrect.';
    http_response_code(500);
    echo json_encode($response, JSON_PRETTY_PRINT);
    exit();
}

// --- 5. Final Response ---
if ($response['status'] === 'OK') {
    $response['overall_message'] = 'Your server configuration appears to be correct!';
}

echo json_encode($response, JSON_PRETTY_PRINT);
?>
