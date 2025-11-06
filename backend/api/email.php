<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Internal-Auth-Key");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once '../core/config.php';
require_once '../core/db.php';
require_once '../core/auth.php';
require_once '../core/ai_handler.php';

$action = $_GET['action'] ?? '';

try {
    $pdo = get_db_connection();

    if ($action === 'receive') {
        // This endpoint is called by the Cloudflare Email Worker
        $internal_key = $_SERVER['HTTP_X_INTERNAL_AUTH_KEY'] ?? '';
        if ($internal_key !== $config['INTERNAL_API_KEY']) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Forbidden: Invalid auth key.']);
            exit;
        }

        $input = json_decode(file_get_contents('php://input'), true);
        $sender = $input['sender'] ?? '';
        $raw_email = $input['raw_email'] ?? '';

        if (empty($sender) || empty($raw_email)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Missing sender or email content.']);
            exit;
        }

        // Find user by sender email
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$sender]);
        $user = $stmt->fetch();

        if ($user) {
            // User exists, save the email
            $stmt = $pdo->prepare("INSERT INTO emails (user_id, raw_content) VALUES (?, ?)");
            $stmt->execute([$user['id'], $raw_email]);
            echo json_encode(['success' => true, 'message' => 'Email received and stored.']);
        } else {
            // User does not exist, discard the email as requested
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'User not found, email discarded.']);
        }

    } elseif ($action === 'list') {
        // This is called by the authenticated frontend user
        $user_data = validate_jwt();
        if (!$user_data) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Authentication failed.']);
            exit;
        }

        $stmt = $pdo->prepare("
            SELECT e.id, e.raw_content, e.status, e.received_at, b.settlement_details 
            FROM emails e
            LEFT JOIN bets b ON e.id = b.email_id
            WHERE e.user_id = ? 
            ORDER BY e.received_at DESC
        ");
        $stmt->execute([$user_data['user_id']]);
        $emails = $stmt->fetchAll();

        echo json_encode(['success' => true, 'emails' => $emails]);

    } elseif ($action === 'process') {
        // Called by frontend to trigger AI processing for a specific email
        $user_data = validate_jwt();
        if (!$user_data) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Authentication failed.']);
            exit;
        }

        $email_id = $_GET['id'] ?? null;
        if (!$email_id) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Email ID is required.']);
            exit;
        }

        // Fetch the email content ensuring it belongs to the user
        $stmt = $pdo->prepare("SELECT raw_content FROM emails WHERE id = ? AND user_id = ? AND status = 'pending'");
        $stmt->execute([$email_id, $user_data['user_id']]);
        $email = $stmt->fetch();

        if (!$email) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Pending email not found for this user.']);
            exit;
        }

        $ai_result = process_email_with_ai($email['raw_content']);
        
        if (isset($ai_result['error'])) {
            // Mark as failed
            $stmt = $pdo->prepare("UPDATE emails SET status = 'failed' WHERE id = ?");
            $stmt->execute([$email_id]);
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => $ai_result['error']]);
        } else {
            // Success, save results and update status
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("UPDATE emails SET status = 'processed' WHERE id = ?");
            $stmt->execute([$email_id]);
            
            $stmt = $pdo->prepare("INSERT INTO bets (email_id, bet_data_json, settlement_details) VALUES (?, ?, ?)");
            $stmt->execute([$email_id, $ai_result['bet_data_json'], $ai_result['settlement_details']]);
            $pdo->commit();

            echo json_encode(['success' => true, 'message' => 'Email processed successfully.', 'result' => $ai_result]);
        }
    } else {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Action not found.']);
    }

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
