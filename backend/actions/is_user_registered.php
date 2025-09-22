<?php
// Action: Check if a user is registered (for email worker)

if (!isset($_GET['worker_secret']) || $_GET['worker_secret'] !== $worker_secret) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Access denied. Invalid secret.']);
    exit();
}
if (!isset($_GET['email']) || !filter_var($_GET['email'], FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid or missing email parameter.']);
    exit();
}

$email = $_GET['email'];
$is_registered = false;

try {
    // The $pdo variable is inherited from index.php
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = :email");
    $stmt->execute([':email' => $email]);

    if ($stmt->fetchColumn() > 0) {
        $is_registered = true;
    }
} catch (PDOException $e) {
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
