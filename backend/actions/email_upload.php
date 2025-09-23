<?php
// Action: Handle file upload from email worker
require_once __DIR__ . '/../lib/BetCalculator.php';

// --- Validation ---
if (!isset($_POST['worker_secret']) || $_POST['worker_secret'] !== $worker_secret) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Access denied.']);
    exit();
}
if (!isset($_POST['user_email']) || !filter_var($_POST['user_email'], FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid or missing user_email.']);
    exit();
}
if (!isset($_FILES['chat_file']) || $_FILES['chat_file']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Main text file upload error.']);
    exit();
}

// --- User Verification ---
$user_email = $_POST['user_email'];
$stmt = $pdo->prepare("SELECT id FROM users WHERE email = :email");
$stmt->execute([':email' => $user_email]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'User not found for the provided email.']);
    exit();
}
$user_id = $user['id'];

// --- Content Processing ---
$raw_content = file_get_contents($_FILES['chat_file']['tmp_name']);
// Correctly get html_body from $_POST, not $_FILES
$html_content = isset($_POST['html_body']) ? $_POST['html_body'] : null;

if ($raw_content === false) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Could not read uploaded text file.']);
    exit();
}

$calculation_result = BetCalculator::calculate($raw_content);
$status = 'unrecognized';
$settlement_details = null;
$total_cost = null;

if ($calculation_result !== null) {
    $status = 'processed';
    $settlement_details = json_encode($calculation_result['breakdown'], JSON_UNESCAPED_UNICODE);
    $total_cost = $calculation_result['total_cost'];
}

// --- Database Transaction ---
try {
    $pdo->beginTransaction();

    // 1. Insert the main bill record
    $sql_bill = "INSERT INTO bills (user_id, raw_content, html_content, settlement_details, total_cost, status)
                 VALUES (:user_id, :raw_content, :html_content, :settlement_details, :total_cost, :status)";
    $stmt_bill = $pdo->prepare($sql_bill);
    $stmt_bill->execute([
        ':user_id' => $user_id,
        ':raw_content' => $raw_content,
        ':html_content' => $html_content,
        ':settlement_details' => $settlement_details,
        ':total_cost' => $total_cost,
        ':status' => $status
    ]);
    $bill_id = $pdo->lastInsertId();

    // 2. Process and insert attachments
    $attachments_saved = [];
    if (isset($_FILES['attachment'])) {
        // Normalize the $_FILES array for easier processing
        $attachments = [];
        if (is_array($_FILES['attachment']['name'])) {
            for ($i = 0; $i < count($_FILES['attachment']['name']); $i++) {
                if ($_FILES['attachment']['error'][$i] === UPLOAD_ERR_OK) {
                    $attachments[] = [
                        'name' => $_FILES['attachment']['name'][$i],
                        'type' => $_FILES['attachment']['type'][$i],
                        'tmp_name' => $_FILES['attachment']['tmp_name'][$i],
                        'size' => $_FILES['attachment']['size'][$i]
                    ];
                }
            }
        } elseif ($_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
            $attachments[] = $_FILES['attachment'];
        }

        $upload_dir = UPLOAD_DIR; // From config.php
        if (!is_dir($upload_dir)) {
            if (!mkdir($upload_dir, 0755, true)) {
                throw new Exception("Failed to create upload directory.");
            }
        }

        $sql_attachment = "INSERT INTO bill_attachments (bill_id, original_filename, stored_filename, mime_type, file_size)
                           VALUES (:bill_id, :original_filename, :stored_filename, :mime_type, :file_size)";
        $stmt_attachment = $pdo->prepare($sql_attachment);

        foreach ($attachments as $attachment) {
            $original_filename = basename($attachment['name']);
            $safe_filename = preg_replace("/[^a-zA-Z0-9-_\.]/", "", $original_filename);
            $stored_filename = time() . '-' . uniqid() . '-' . $safe_filename;
            $destination = $upload_dir . $stored_filename;

            if (move_uploaded_file($attachment['tmp_name'], $destination)) {
                $stmt_attachment->execute([
                    ':bill_id' => $bill_id,
                    ':original_filename' => $original_filename,
                    ':stored_filename' => $stored_filename,
                    ':mime_type' => $attachment['type'],
                    ':file_size' => $attachment['size']
                ]);
                $attachments_saved[] = $original_filename;
            } else {
                // If any file fails to move, throw an exception to roll back the transaction
                throw new Exception("Failed to move uploaded file: " . $original_filename);
            }
        }
    }

    $pdo->commit();

    http_response_code(201);
    echo json_encode([
        'success' => true,
        'message' => 'Bill and ' . count($attachments_saved) . ' attachments processed successfully.',
        'status' => $status
    ]);

} catch (Exception $e) { // Catch both PDOException and general Exception
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Bill insertion transaction error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to save the bill to the database. Details: ' . $e->getMessage()]);
}
?>
