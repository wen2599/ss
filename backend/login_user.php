<?php

require_once __DIR__ . '/api_header.php';

global $debug_info; // Access the global debug_info array

// --- Input and Validation ---
$data = json_decode(file_get_contents('php://input'), true);

if (!$data) {
    http_response_code(400);
    echo json_encode([
        'error' => 'Invalid JSON received.'
    ]);
    exit;
}

$email = $data['email'] ?? null;
$password = $data['password'] ?? null;

if (empty($email) || empty($password)) {
    http_response_code(400);
    echo json_encode([
        'error' => 'Email and password are required.'
    ]);
    exit;
}

// --- Database and Authentication ---
$pdo = get_db_connection();
if (!$pdo) {
    http_response_code(503);
    echo json_encode([
        'error' => 'Database connection is currently unavailable.'
    ]);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT id, username, email, password_hash FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password_hash'])) {
        // --- Session Creation ---
        // The session cookie is now configured globally in api_header.php.
        // We just need to set the session variables.
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['email'] = $user['email'];

        // Set a flag to trigger session ID regeneration in the next request
        // to enhance security against session fixation.
        $_SESSION['regenerate_id'] = true;

        http_response_code(200);
        echo json_encode([
            'status' => 'success',
            'message' => 'Login successful!',
            'user' => [
                'id' => $user['id'],
                'email' => $user['email'],
                'username' => $user['username']
            ]
        ]);

    } else {
        http_response_code(401); // Unauthorized
        echo json_encode([
            'error' => 'Invalid email or password.'
        ]);
    }

} catch (PDOException $e) {
    error_log("User login error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'error' => 'An internal database error occurred.'
    ]);
}

?>
