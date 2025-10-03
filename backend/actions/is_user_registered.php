<?php
/**
 * Action: is_user_registered
 *
 * This script checks if a user is registered in the database based on their email address.
 * It is intended to be used by external services (like an email processing worker)
 * to verify a user's existence before performing further actions.
 *
 * HTTP Method: GET
 *
 * Query Parameters:
 * - "email" (string): The email address to check.
 *
 * Response:
 * - On success: { "success": true, "is_registered": boolean }
 * - On error (e.g., missing email): { "success": false, "error": "Error message." }
 */

// The main router (index.php) handles initialization and security checks,
// including validating the X-Worker-Secret header.
// Global variables $pdo and $log are available.

// 1. Validation: Check if the email parameter is provided and valid.
if (!isset($_GET['email']) || !filter_var($_GET['email'], FILTER_VALIDATE_EMAIL)) {
    http_response_code(400); // Bad Request
    $log->warning("Bad request to is_user_registered: email is missing or invalid.", ['query' => $_GET]);
    echo json_encode(['success' => false, 'error' => 'A valid email address is required.']);
    exit();
}

$email = $_GET['email'];

// 2. Database Operation
try {
    $sql = "SELECT COUNT(*) FROM users WHERE email = :email";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':email' => $email]);

    $isRegistered = ($stmt->fetchColumn() > 0);

    http_response_code(200);
    $log->info("User registration check completed.", ['email' => $email, 'is_registered' => $isRegistered]);
    echo json_encode([
        'success' => true,
        'is_registered' => $isRegistered
    ]);

} catch (PDOException $e) {
    // The global exception handler in init.php will catch this.
    $log->error("Database error during user registration check.", [
        'email' => $email,
        'error' => $e->getMessage()
    ]);
    throw $e;
}
?>