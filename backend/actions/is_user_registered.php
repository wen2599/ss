<?php
// This endpoint is called by the Cloudflare Worker to check if an email address
// belongs to a registered user before processing and uploading the email.

// This action is public in the sense that it's called by the worker,
// not a logged-in user. The security is handled by the worker_secret.
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_response(['success' => false, 'error' => 'Only GET method is allowed.'], 405);
}

$email = $_GET['email'] ?? null;

if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    json_response(['success' => false, 'error' => 'A valid email address is required.'], 400);
}

try {
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user_exists = $stmt->fetch() !== false;

    json_response([
        'success' => true,
        'is_registered' => $user_exists,
    ], 200);

} catch (PDOException $e) {
    // The global exception handler in init.php will catch this,
    // but we can also handle it here for more specific logging if needed.
    json_response(['success' => false, 'error' => 'Database query failed.'], 500);
}
?>