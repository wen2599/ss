<?php
require_once __DIR__ . '/../lib/BetCalculator.php';
require_once __DIR__ . '/../lib/GeminiCorrectionService.php';

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

// 智能编码转换：优先用邮件头charset，否则自动检测
function smart_convert_encoding($text, $prefer_charset = null) {
    if (!$text) return $text;
    if ($prefer_charset) {
        $prefer_charset = strtoupper($prefer_charset);
        $charset = ($prefer_charset === 'GBK' || $prefer_charset === 'GB2312') ? 'GB18030' : $prefer_charset;
        if ($charset !== 'UTF-8') {
            return mb_convert_encoding($text, 'UTF-8', $charset);
        }
    }
    $encoding = mb_detect_encoding($text, ['UTF-8','GB18030','GBK','GB2312','BIG5','ISO-8859-1','Windows-1252'], true);
    if ($encoding && strtoupper($encoding) !== 'UTF-8') {
        $convert_from = ($encoding === 'ISO-8859-1' || $encoding === 'Windows-1252') ? 'GB18030' : $encoding;
        return mb_convert_encoding($text, 'UTF-8', $convert_from);
    }
    return $text;
}

// 优化邮件正文提取，能返回charset
function get_plain_text_body_from_email($raw_email, &$detected_charset = null) {
    if (!preg_match('/boundary="?([^"]+)"?/i', $raw_email, $matches)) {
        $bodyPos = strpos($raw_email, "\r\n\r\n");
        if ($bodyPos !== false) {
            $body = substr($raw_email, $bodyPos + 4);
            $detected_charset = null;
            return $body;
        }
        $detected_charset = null;
        return $raw_email;
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
            $charset = null;
            if (preg_match('/charset="?([^"]+)"?/i', $header_part, $charset_matches)) {
                $charset = strtoupper($charset_matches[1]);
            }
            $detected_charset = $charset;
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
            return $decoded_body;
        }
    }
    $detected_charset = null;
    return null;
}

// 认证及参数检查
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
$stmt = $pdo->prepare("SELECT id FROM users WHERE email = :email");
$stmt->execute([':email' => $user_email]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$user) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'User not found for the provided email.']);
    exit();
}
$user_id = $user['id'];

// 邮件正文处理
$detected_charset = null;
$text_body = get_plain_text_body_from_email($raw_email_content, $detected_charset);
$text_body = smart_convert_encoding($text_body, $detected_charset);

// HTML正文也智能转码
$html_body = null;
if (isset($_FILES['html_body']) && $_FILES['html_body']['error'] === UPLOAD_ERR_OK) {
    $html_raw = file_get_contents($_FILES['html_body']['tmp_name']);
    $html_body = smart_convert_encoding($html_raw, $detected_charset);
} elseif (!empty($_POST['html_body'])) {
    $html_body = smart_convert_encoding($_POST['html_body'], $detected_charset);
}

$attachments_meta = handle_attachments($user_id);

// 确定用于计算的最终内容
$calculation_content = trim($text_body);

// 如果纯文本内容为空，则尝试从HTML转换
if (empty($calculation_content) && !empty($html_body)) {
    // Convert basic block tags to newlines for better structure, then strip all tags.
    $processed_html = preg_replace(['/<br\s*\/?>/i', '/<\/p>/i', '/<div/i'], ["\n", "\n\n", "\n<div"], $html_body);
    $calculation_content = trim(strip_tags($processed_html));
    $calculation_content = html_entity_decode($calculation_content, ENT_QUOTES, 'UTF-8');
}

// 如果内容仍然为空，则回退到原始邮件内容（不太可能，但作为保障）
if (empty($calculation_content)) {
    $calculation_content = smart_convert_encoding($raw_email_content, $detected_charset);
}

// 多段结算
$parsed_bill = BetCalculator::calculateMulti($calculation_content);
$status = 'unrecognized';
$settlement_details = null;
$total_cost = null;

// Only proceed if the calculator found valid bet slips.
if ($parsed_bill !== null && !empty($parsed_bill['slips'])) {
    // --- AI-Assisted Parsing Correction ---
    foreach ($parsed_bill['slips'] as &$slip) { // Use reference to modify the slip directly
        if (!empty($slip['result']['unparsed_text'])) {
            write_log("Unparsed text found. Calling Gemini for correction...");
            $geminiService = new GeminiCorrectionService($gemini_api_key);
            $correction = $geminiService->getCorrection($slip['result']['unparsed_text']);

            if ($correction && !empty($correction['corrected_data']['number_bets'])) {
                // Merge Gemini's results into the slip
                $slip['result']['number_bets'] = array_merge($slip['result']['number_bets'], $correction['corrected_data']['number_bets']);

                // Recalculate summary for the slip
                $slip_total_cost = 0;
                $slip_number_count = 0;
                foreach ($slip['result']['number_bets'] as $bet) {
                    $slip_total_cost += $bet['cost'];
                    $slip_number_count += count($bet['numbers']);
                }
                $slip['result']['summary']['total_cost'] = $slip_total_cost;
                $slip['result']['summary']['number_count'] = $slip_number_count;

                write_log("Gemini successfully corrected the slip.");
            }

            if ($correction && !empty($correction['suggested_regex'])) {
                // Log the suggested regex for future improvement
                write_log("Gemini suggested a new regex: " . $correction['suggested_regex']);
            }

            // Clear the unparsed text field after processing
            $slip['result']['unparsed_text'] = '';
        }
    }
    unset($slip); // Unset the reference

    // Recalculate the overall bill summary after potential corrections
    $new_total_cost = 0;
    $new_total_number_count = 0;
    foreach ($parsed_bill['slips'] as $slip) {
        $new_total_cost += $slip['result']['summary']['total_cost'] ?? 0;
        $new_total_number_count += $slip['result']['summary']['number_count'] ?? 0;
    }
    $parsed_bill['summary']['total_cost'] = $new_total_cost;
    $parsed_bill['summary']['total_number_count'] = $new_total_number_count;
    // --- End of AI-Assisted Parsing ---

    // Fetch latest lottery results for automatic settlement
    $lottery_types = ['香港', '新澳门'];
    $lottery_results_map = [];
    foreach ($lottery_types as $type) {
        $sql = "SELECT numbers FROM lottery_results WHERE lottery_name LIKE :lottery_name ORDER BY parsed_at DESC, id DESC LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':lottery_name' => '%' . $type . '%']);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($result) {
            $lottery_results_map[$type] = explode(',', $result['numbers']);
        }
    }

    // If we have results, perform auto-settlement
    if (!empty($lottery_results_map)) {
        $settled_bill = BetCalculator::settle($parsed_bill, $lottery_results_map);
        $settlement_details = json_encode($settled_bill, JSON_UNESCAPED_UNICODE);
        $status = 'settled'; // Mark as settled immediately
    } else {
        // If no lottery results, save as processed but not settled
        $settlement_details = json_encode($parsed_bill, JSON_UNESCAPED_UNICODE);
        $status = 'processed';
    }

    $total_cost = $parsed_bill['summary']['total_cost'] ?? 0;
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
