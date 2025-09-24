<?php
// Action: Handle RAW email upload from the Cloudflare worker and parse it

require_once __DIR__ . '/../lib/BetCalculator.php';

/**
 * A simple MIME parser to extract the plain text body from a raw email.
 *
 * @param string $raw_email The full raw email content.
 * @return string|null The decoded, UTF-8 converted plain text body, or null if not found.
 */
function get_plain_text_body_from_email($raw_email) {
    // Find the main boundary
    if (!preg_match('/boundary="?([^"]+)"?/i', $raw_email, $matches)) {
        // Not a multipart email, or boundary not found. Assume the body is plain text.
        // This is a fallback and might not be perfect.
        $bodyPos = strpos($raw_email, "\r\n\r\n");
        if ($bodyPos !== false) {
            return mb_convert_encoding(substr($raw_email, $bodyPos + 4), 'UTF-8', 'auto');
        }
        return mb_convert_encoding($raw_email, 'UTF-8', 'auto');
    }
    $boundary = $matches[1];

    // Split the email into parts
    $parts = explode('--' . $boundary, $raw_email);
    array_shift($parts); // Remove the part before the first boundary

    foreach ($parts as $part) {
        if (trim($part) == '--') continue; // End boundary

        // We only care about the plain text part that is not an attachment
        if (stripos($part, 'Content-Type: text/plain') !== false && stripos($part, 'Content-Disposition: attachment') === false) {

            // Separate headers from the body
            $header_part = '';
            $body_part = '';
            $split_pos = strpos($part, "\r\n\r\n");
            if ($split_pos !== false) {
                $header_part = substr($part, 0, $split_pos);
                $body_part = substr($part, $split_pos + 4);
            } else {
                continue; // Malformed part
            }

            // Get transfer encoding and charset from the part's headers
            $transfer_encoding = '7bit';
            if (preg_match('/Content-Transfer-Encoding:\s*(\S+)/i', $header_part, $encoding_matches)) {
                $transfer_encoding = strtolower($encoding_matches[1]);
            }
            $charset = 'UTF-8';
            if (preg_match('/charset="?([^"]+)"?/i', $header_part, $charset_matches)) {
                $charset = strtoupper($charset_matches[1]);
            }

            // Decode the body
            $decoded_body = '';
            switch ($transfer_encoding) {
                case 'base64':
                    $decoded_body = base64_decode($body_part);
                    break;
                case 'quoted-printable':
                    $decoded_body = quoted_printable_decode($body_part);
                    break;
                default:
                    $decoded_body = $body_part;
                    break;
            }

            // Convert to UTF-8 and return
            return mb_convert_encoding($decoded_body, 'UTF-8', $charset);
        }
    }

    return null; // No suitable plain text part found
}


// Main script logic starts here

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method Not Allowed']);
    exit();
}

if (!isset($_POST['worker_secret']) || $_POST['worker_secret'] !== $worker_secret) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Access denied.']);
    exit();
}
if (!isset($_POST['user_email']) || !isset($_FILES['raw_email_file'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing required fields from worker.']);
    exit();
}
if ($_FILES['raw_email_file']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'File upload error from worker.']);
    exit();
}

$user_email = $_POST['user_email'];
$file_tmp_path = $_FILES['raw_email_file']['tmp_name'];
$raw_email_content = file_get_contents($file_tmp_path);

if ($raw_email_content === false) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Could not read uploaded email file.']);
    exit();
}

// Use our new parser to get the clean, UTF-8 content
$raw_content = get_plain_text_body_from_email($raw_email_content);

if ($raw_content === null) {
    $raw_content = "Error: Could not find a suitable text/plain part in the email.";
}

// The rest of the script is the same as before
$stmt = $pdo->prepare("SELECT id FROM users WHERE email = :email");
$stmt->execute([':email' => $user_email]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'User not found for the provided email.']);
    exit();
}
$user_id = $user['id'];

$settlement_slip = BetCalculator::calculate($raw_content);

$status = 'unrecognized';
$settlement_details = null;
$total_cost = null;

if ($settlement_slip !== null) {
    $status = 'processed';
    $settlement_details = json_encode($settlement_slip, JSON_UNESCAPED_UNICODE);
    $total_cost = $settlement_slip['summary']['total_cost'];
}

try {
    $sql = "INSERT INTO bills (user_id, raw_content, settlement_details, total_cost, status)
            VALUES (:user_id, :raw_content, :settlement_details, :total_cost, :status)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':user_id' => $user_id,
        ':raw_content' => $raw_content,
        ':settlement_details' => $settlement_details,
        ':total_cost' => $total_cost,
        ':status' => $status
    ]);

    http_response_code(201);
    echo json_encode([
        'success' => true,
        'message' => 'Bill processed and saved successfully.',
        'status' => $status
    ]);

} catch (PDOException $e) {
    error_log("Bill insertion error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to save the bill to the database.']);
}
?>