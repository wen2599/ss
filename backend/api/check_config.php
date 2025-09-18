<?php
// backend/api/check_config.php
// A simple health-check endpoint to verify server configuration.

header('Content-Type: application/json');

$response = [
    'status' => 'OK',
    'checks' => []
];

// --- 1. Check if config.php exists and is readable ---
if (!is_readable(__DIR__ . '/config.php')) {
    $response['status'] = 'ERROR';
    $response['checks']['config_file'] = [
        'status' => 'FAILED',
        'message' => 'config.php does not exist or is not readable.'
    ];
    http_response_code(500);
    echo json_encode($response);
    exit();
}
require_once __DIR__ . '/config.php';
$response['checks']['config_file'] = ['status' => 'OK', 'message' => 'config.php loaded successfully.'];

// --- 2. Check for required constants ---
$required_constants = [
    'DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASS',
    'TELEGRAM_BOT_TOKEN', 'TELEGRAM_CHANNEL_ID', 'TELEGRAM_SUPER_ADMIN_ID'
];
$all_constants_defined = true;

foreach ($required_constants as $const) {
    if (!defined($const) || empty(constant($const)) || strpos(constant($const), 'your_') === 0) {
        $response['checks'][$const] = [
            'status' => 'FAILED',
            'message' => "Constant '$const' is not defined, is empty, or still has the default placeholder value."
        ];
        $all_constants_defined = false;
    } else {
        $response['checks'][$const] = ['status' => 'OK'];
    }
}

if (!$all_constants_defined) {
    $response['status'] = 'ERROR';
    $response['overall_message'] = 'One or more required constants in config.php are missing or not set correctly.';
    http_response_code(500);
    echo json_encode($response);
    exit();
}

// --- 3. Check Database Connection ---
try {
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
        'message' => 'Failed to connect to the database. Error: ' . $e->getMessage()
    ];
    $response['overall_message'] = 'The database credentials in config.php appear to be incorrect.';
    http_response_code(500);
}

// --- 4. Final Response ---
if ($response['status'] === 'OK') {
    $response['overall_message'] = 'Configuration seems correct!';
}

echo json_encode($response, JSON_PRETTY_PRINT);
?>
