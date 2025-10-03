<?php
/**
 * Action: register
 *
 * This script handles new user registration. It validates the provided user data,
 * creates a new user with a 'pending' status, and sends a notification to the
 * admin via Telegram for approval.
 *
 * HTTP Method: POST
 *
 * Request Body (JSON):
 * - "email" (string): The user's email address.
 * - "password" (string): The user's desired password (min 8 characters).
 * - "username" (string, optional): The user's desired username.
 *
 * Response:
 * - On success: { "success": true, "message": "Your registration application has been submitted..." }
 * - On error (e.g., invalid input, email exists): { "success": false, "error": "Error message." }
 */

// The main router (index.php) handles initialization.
// Global variables $pdo, $log, and $admin_id are available.

use App; // Use the App namespace for Telegram, User, etc.

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // Method Not Allowed
    $log->warning("Method not allowed for register.", ['method' => $_SERVER['REQUEST_METHOD']]);
    echo json_encode(['success' => false, 'error' => 'Method Not Allowed']);
    exit();
}

$data = json_decode(file_get_contents("php://input"), true);

// 1. Validation
if (
    !isset($data['email']) || !filter_var($data['email'], FILTER_VALIDATE_EMAIL) ||
    !isset($data['password']) || mb_strlen($data['password']) < 8
) {
    http_response_code(400); // Bad Request
    $log->warning("Bad request to register: Invalid or missing fields.", ['data' => $data]);
    echo json_encode(['success' => false, 'error' => 'A valid email and a password of at least 8 characters are required.']);
    exit();
}

$email = $data['email'];
$password = $data['password'];
$username = !empty($data['username']) ? trim($data['username']) : 'WebApp User';

// 2. Check if user already exists
try {
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = :email");
    $stmt->execute([':email' => $email]);
    if ($stmt->fetch()) {
        http_response_code(409); // Conflict
        $log->warning("Registration attempt for existing email.", ['email' => $email]);
        echo json_encode(['success' => false, 'error' => 'This email address is already registered.']);
        exit();
    }

    // 3. Create new user
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    $sql = "INSERT INTO users (email, password, username, status) VALUES (:email, :password, :username, 'pending')";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':email' => $email,
        ':password' => $hashedPassword,
        ':username' => $username
    ]);
    $newUserId = $pdo->lastInsertId();
    $log->info("New user registered with pending status.", ['user_id' => $newUserId, 'email' => $email]);

    // 4. Send Telegram notification to admin for approval
    // This is in a separate try-catch so a notification failure doesn't break registration.
    try {
        if (empty($admin_id)) {
            throw new Exception("Admin Telegram ID is not configured.");
        }

        $notificationText = "新的网站用户注册请求：\n"
                           . "---------------------\n"
                           . "*用户:* `" . htmlspecialchars($username) . "`\n"
                           . "*Email:* `" . htmlspecialchars($email) . "`\n"
                           . "*数据库 ID:* `" . $newUserId . "`\n"
                           . "---------------------\n"
                           . "请批准或拒绝此请求。";

        $approvalKeyboard = json_encode([
            'inline_keyboard' => [[
                ['text' => '✅ 批准', 'callback_data' => 'approve_dbid_' . $newUserId],
                ['text' => '❌ 拒绝', 'callback_data' => 'deny_dbid_' . $newUserId]
            ]]
        ]);

        Telegram::sendMessage($admin_id, $notificationText, $approvalKeyboard);
        $log->info("Sent registration approval notification to admin.", ['user_id' => $newUserId, 'admin_id' => $admin_id]);

    } catch (Throwable $e) {
        // Log the notification error but don't prevent the user from getting a success response.
        $log->error("Failed to send Telegram notification for new user.", [
            'user_id' => $newUserId,
            'error' => $e->getMessage()
        ]);
    }

    // 5. Send success response to the user
    http_response_code(201); // Created
    echo json_encode(['success' => true, 'message' => '您的注册申请已提交，请等待管理员批准。']);

} catch (PDOException $e) {
    // The global exception handler in init.php will catch this.
    $log->error("Database error during registration.", ['email' => $email, 'error' => $e->getMessage()]);
    throw $e;
}
?>