<?php
// backend/api/auth.php
// This file contains the business logic for user authentication and management.

require_once __DIR__ . '/db.php';

// --- Helper Functions ---

/**
 * Sends a JSON response to the client and terminates the script.
 * @param int $statusCode The HTTP status code.
 * @param array $data The data to be JSON encoded.
 */
function send_json_response(int $statusCode, array $data) {
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit();
}

/**
 * Generates a unique 4-digit user ID.
 * @param mysqli $conn The database connection.
 * @return string The unique 4-digit ID.
 */
function generate_unique_user_id(mysqli $conn): string {
    while (true) {
        $user_id = str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);
        $stmt = $conn->prepare("SELECT id FROM users WHERE user_id = ?");
        $stmt->bind_param("s", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows === 0) {
            $stmt->close();
            return $user_id;
        }
        $stmt->close();
    }
}


// --- API Logic Handlers ---

function handleRegister() {
    $input = json_decode(file_get_contents('php://input'), true);

    if (empty($input['phone_number']) || empty($input['password'])) {
        send_json_response(400, ['error' => 'Phone number and password are required.']);
    }

    $conn = get_db();
    $phone_number = $input['phone_number'];

    // Check if user already exists
    $stmt = $conn->prepare("SELECT id FROM users WHERE phone_number = ?");
    $stmt->bind_param("s", $phone_number);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        send_json_response(409, ['error' => 'Phone number already registered.']);
    }
    $stmt->close();

    // Create new user
    $user_id = generate_unique_user_id($conn);
    $password_hash = password_hash($input['password'], PASSWORD_DEFAULT);

    $stmt = $conn->prepare("INSERT INTO users (user_id, phone_number, password_hash) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $user_id, $phone_number, $password_hash);

    if ($stmt->execute()) {
        send_json_response(201, ['success' => true, 'user_id' => $user_id]);
    } else {
        send_json_response(500, ['error' => 'Failed to create user.']);
    }
    $stmt->close();
}

function handleLogin() {
    session_start();
    $input = json_decode(file_get_contents('php://input'), true);

    if (empty($input['phone_number']) || empty($input['password'])) {
        send_json_response(400, ['error' => 'Phone number and password are required.']);
    }

    $conn = get_db();
    $phone_number = $input['phone_number'];

    $stmt = $conn->prepare("SELECT id, user_id, password_hash, points FROM users WHERE phone_number = ?");
    $stmt->bind_param("s", $phone_number);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();

    if ($user && password_verify($input['password'], $user['password_hash'])) {
        // Password is correct, start session
        $_SESSION['user_id'] = $user['user_id'];
        $_SESSION['internal_id'] = $user['id'];
        $_SESSION['phone_number'] = $phone_number;

        send_json_response(200, [
            'success' => true,
            'user_id' => $user['user_id'],
            'points' => $user['points']
        ]);
    } else {
        send_json_response(401, ['error' => 'Invalid phone number or password.']);
    }
}

function handleLogout() {
    session_start();
    session_unset();
    session_destroy();
    send_json_response(200, ['success' => true, 'message' => 'Logged out successfully.']);
}

function handleSearchPlayer() {
    session_start();
    if (!isset($_SESSION['user_id'])) {
        send_json_response(401, ['error' => 'Unauthorized. Please log in.']);
    }

    $phone_number = $_GET['phone_number'] ?? null;
    if (!$phone_number) {
        send_json_response(400, ['error' => 'Phone number parameter is required.']);
    }

    $conn = get_db();
    $stmt = $conn->prepare("SELECT user_id FROM users WHERE phone_number = ?");
    $stmt->bind_param("s", $phone_number);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();

    if ($user) {
        send_json_response(200, ['user_id' => $user['user_id']]);
    } else {
        send_json_response(404, ['error' => 'Player not found.']);
    }
}

function handleTransferPoints() {
    session_start();
    if (!isset($_SESSION['user_id'])) {
        send_json_response(401, ['error' => 'Unauthorized. Please log in.']);
    }

    $input = json_decode(file_get_contents('php://input'), true);
    $recipient_user_id = $input['recipient_id'] ?? null;
    $amount = (int)($input['amount'] ?? 0);

    if (!$recipient_user_id || $amount <= 0) {
        send_json_response(400, ['error' => 'Recipient ID and a positive amount are required.']);
    }

    $sender_internal_id = $_SESSION['internal_id'];
    $conn = get_db();

    $conn->begin_transaction();
    try {
        // 1. Get sender's points and lock the row
        $stmt_sender = $conn->prepare("SELECT points FROM users WHERE id = ? FOR UPDATE");
        $stmt_sender->bind_param("i", $sender_internal_id);
        $stmt_sender->execute();
        $sender = $stmt_sender->get_result()->fetch_assoc();

        if (!$sender || $sender['points'] < $amount) {
            throw new Exception('Insufficient points.');
        }
        $stmt_sender->close();

        // 2. Get recipient's internal id and lock the row
        $stmt_recipient = $conn->prepare("SELECT id FROM users WHERE user_id = ? FOR UPDATE");
        $stmt_recipient->bind_param("s", $recipient_user_id);
        $stmt_recipient->execute();
        $recipient = $stmt_recipient->get_result()->fetch_assoc();

        if (!$recipient) {
            throw new Exception('Recipient not found.');
        }
        $recipient_internal_id = $recipient['id'];
        $stmt_recipient->close();

        // 3. Perform the transfer
        $stmt_debit = $conn->prepare("UPDATE users SET points = points - ? WHERE id = ?");
        $stmt_debit->bind_param("ii", $amount, $sender_internal_id);
        $stmt_debit->execute();
        $stmt_debit->close();

        $stmt_credit = $conn->prepare("UPDATE users SET points = points + ? WHERE id = ?");
        $stmt_credit->bind_param("ii", $amount, $recipient_internal_id);
        $stmt_credit->execute();
        $stmt_credit->close();

        $conn->commit();
        send_json_response(200, ['success' => true, 'message' => 'Points transferred successfully.']);

    } catch (Exception $e) {
        $conn->rollback();
        send_json_response(400, ['error' => $e->getMessage()]);
    }
}
?>
