<?php
/**
 * is_user_registered.php
 *
 * This script serves as an endpoint for the Cloudflare Worker to verify
 * if an email address belongs to a registered user in the database.
 *
 * --- How It Works ---
 * 1. It expects two GET parameters: 'worker_secret' and 'email'.
 * 2. It validates the secret to ensure the request is from a trusted source (the Worker).
 * 3. It queries the database to check if the provided email exists in the 'users' table.
 * 4. It returns a JSON response indicating whether the user is registered.
 */

// 1. Include Configuration using an absolute path
require_once __DIR__ . '/config.php';

// 2. Set Headers
header('Content-Type: application/json');

// 3. Security Check: Validate the Worker Secret
if (!isset($_GET['worker_secret']) || $_GET['worker_secret'] !== $worker_secret) {
    http_response_code(403); // Forbidden
    echo json_encode(['success' => false, 'error' => 'Access denied. Invalid secret.']);
    exit();
}

// 4. Input Validation: Check for Email Parameter
if (!isset($_GET['email']) || !filter_var($_GET['email'], FILTER_VALIDATE_EMAIL)) {
    http_response_code(400); // Bad Request
    echo json_encode(['success' => false, 'error' => 'Invalid or missing email parameter.']);
    exit();
}

$email = $_GET['email'];
$is_registered = false;

// 5. Database Interaction
try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Check if the email exists in the 'email' column of the 'users' table.
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = :email");
    $stmt->execute([':email' => $email]);
    
    if ($stmt->fetchColumn() > 0) {
        $is_registered = true;
    }

} catch (PDOException $e) {
    // Log database errors but don't expose details to the client
    error_log("User verification DB error: " . $e->getMessage());
    http_response_code(500); // Internal Server Error
    echo json_encode(['success' => false, 'error' => 'A server error occurred during verification.']);
    exit();
}

// 6. Send Success Response
http_response_code(200);
echo json_encode([
    'success' => true,
    'is_registered' => $is_registered
]);

?>
