<?php
// Action: Check if a user is registered (for email worker)
write_log("is_user_registered.php: Script started.");

if (!isset($_GET['worker_secret']) || $_GET['worker_secret'] !== $worker_secret) {
    write_log("is_user_registered.php: CRITICAL - Worker secret mismatch or not provided.");
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Access denied. Invalid secret.']);
    exit();
}
write_log("is_user_registered.php: Worker secret check passed.");

if (!isset($_GET['email']) || !filter_var($_GET['email'], FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid or missing email parameter.']);
    exit();
}

$email = $_GET['email'];
$is_registered = false;
write_log("is_user_registered.php: Checking email: " . $email);

try {
    // The $pdo variable is inherited from index.php
    write_log("is_user_registered.php: Preparing DB query.");
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = :email");

    write_log("is_user_registered.php: Executing DB query.");
    $stmt->execute([':email' => $email]);
    write_log("is_user_registered.php: Query executed.");

    if ($stmt->fetchColumn() > 0) {
        $is_registered = true;
        write_log("is_user_registered.php: User was found in database.");
    } else {
        write_log("is_user_registered.php: User was NOT found in database.");
    }
} catch (PDOException $e) {
    write_log("is_user_registered.php: CRITICAL - PDOException: " . $e->getMessage());
    error_log("User verification DB error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'A server error occurred during verification.']);
    exit();
}

http_response_code(200);
echo json_encode([
    'success' => true,
    'is_registered' => $is_registered
]);
?>
