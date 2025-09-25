<?php
require_once __DIR__ . '/../lib/BetCalculator.php';

// ============ 调试日志：用于排查 Worker 上传问题 =============
// 日志文件路径
$debug_log_file = __DIR__ . '/../debug_upload.txt';
// 记录所有 POST 和 FILES 字段
file_put_contents($debug_log_file, "----------\n" . date('Y-m-d H:i:s') . "\n", FILE_APPEND);
file_put_contents($debug_log_file, "POST:\n" . print_r($_POST, true) . "\n", FILE_APPEND);
file_put_contents($debug_log_file, "FILES:\n" . print_r($_FILES, true) . "\n", FILE_APPEND);

// Helper: Save attachments to disk and return their metadata
function handle_attachments($user_id) {
    $attachments_meta = [];
    $upload_dir = UPLOAD_DIR . "user_$user_id/";
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    if (!empty($_FILES['attachment'])) {
        $files = $_FILES['attachment'];
        $count = is_array($files['name']) ? count($files['name']) : 1;
        for ($i = 0; $i < $count; $i++) {
            $name = is_array($files['name']) ? $files['name'][$i] : $files['name'];
            $tmp_name = is_array($files['tmp_name']) ? $files['tmp_name'][$i] : $files['tmp_name'];
            $error = is_array($files['error']) ? $files['error'][$i] : $files['error'];
            $size = is_array($files['size']) ? $files['size'][$i] : $files['size'];
            $type = is_array($files['type']) ? $files['type'][$i] : $files['type'];

            if ($error === UPLOAD_ERR_OK) {
                $save_path = $upload_dir . uniqid() . '-' . basename($name);
                if (move_uploaded_file($tmp_name, $save_path)) {
                    $attachments_meta[] = [
                        'filename' => $name,
                        'saved_as' => $save_path,
                        'size' => $size,
                        'type' => $type
                    ];
                }
            }
        }
    }
    return $attachments_meta;
}

function get_plain_text_body_from_email($raw_email) {
    if (!preg_match('/boundary="?([^"]+)"?/i', $raw_email, $matches)) {
        $bodyPos = strpos($raw_email, "\r\n\r\n");
        if ($bodyPos !== false) {
            return mb_convert_encoding(substr($raw_email, $bodyPos + 4), 'UTF-8', 'auto');
        }
        return mb_convert_encoding($raw_email, 'UTF-8', 'auto');
    }
    $boundary = $matches[1];
    $parts = explode('--' . $boundary, $raw_email);
    array_shift($parts);
    foreach ($parts as $part) {
        if (trim($part) == '--') continue;
        if (stripos($part, 'Content-Type: text/plain') !== false && stripos($part, 'Content-Disposition: attachment') === false) {
            $header_part = '';
            $body_part = '';
            $split_pos = strpos($part, "\r\n\r\n");
            if ($split_pos !== false) {
                $header_part = substr($part, 0, $split_pos);
                $body_part = substr($part, $split_pos + 4);
            } else {
                continue;
            }
            $transfer_encoding = '7bit';
            if (preg_match('/Content-Transfer-Encoding:\s*(\S+)/i', $header_part, $encoding_matches)) {
                $transfer_encoding = strtolower($encoding_matches[1]);
            }
            $charset = 'UTF-8';
            if (preg_match('/charset="?([^"]+)"?/i', $header_part, $charset_matches)) {
                $charset = strtoupper($charset_matches[1]);
            }
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
            return mb_convert_encoding($decoded_body, 'UTF-8', $charset);
        }
    }
    return null;
}

// ==== 安全性校验 ====
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

// ==== 业务逻辑 ====
$user_email = $_POST['user_email'];
$file_tmp_path = $_FILES['raw_email_file']['tmp_name'];
$raw_email_content = file_get_contents($file_tmp_path);
if ($raw_email_content === false) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Could not read uploaded email file.']);
    exit();
}
$stmt = $pdo->prepare("SELECT id FROM users WHERE email = :email");
$stmt->execute([':email' => $user_email]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$user) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'User not found for the provided email.']);
    exit();
}
$user_id = $user['id'];

$text_body = get_plain_text_body_from_email($raw_email_content);
if ($text_body === null) $text_body = $raw_email_content;

$html_body = null;
if (isset($_FILES['html_body']) && $_FILES['html_body']['error'] === UPLOAD_ERR_OK) {
    $html_body = file_get_contents($_FILES['html_body']['tmp_name']);
} elseif (!empty($_POST['html_body'])) {
    $html_body = $_POST['html_body'];
}

$attachments_meta = handle_attachments($user_id);

$settlement_slip = BetCalculator::calculate($text_body);
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
        ':raw_content' => $text_body . ($html_body ? "\n\n---HTML正文---\n" . $html_body : ''),
        ':settlement_details' => $settlement_details,
        ':total_cost' => $total_cost,
        ':status' => $status
    ]);
    http_response_code(201);
    echo json_encode([
        'success' => true,
        'message' => 'Bill processed and saved successfully.',
        'status' => $status,
        'attachments' => $attachments_meta
    ]);
} catch (PDOException $e) {
    error_log("Bill insertion error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to save the bill to the database.']);
}
?>
