<?php
/**
 * General Utility Functions
 *
 * This file contains helper functions that can be used across the application.
 */

/**
 * Simple file-based logger for debugging.
 *
 * @param string $message The message to log.
 * @return void
 */
function write_log($message) {
    $log_file = __DIR__ . '/../debug_log.txt';
    $timestamp = date('Y-m-d H:i:s');
    // Add a separator for new requests to make the log easier to read
    if (strpos($message, '---') !== false) {
        file_put_contents($log_file, "\n" . $message . "\n", FILE_APPEND);
    } else {
        file_put_contents($log_file, "[$timestamp] " . $message . "\n", FILE_APPEND);
    }
}

/**
 * Handles file attachments from a POST request.
 * Saves them to a user-specific directory.
 *
 * @param int $user_id The ID of the user uploading the files.
 * @return array A list of metadata for successfully saved attachments.
 */
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

/**
 * Intelligently converts a string to UTF-8.
 * It uses a preferred charset if provided, otherwise it auto-detects the encoding.
 *
 * @param string|null $text The input string.
 * @param string|null $prefer_charset The preferred source charset.
 * @return string|null The UTF-8 encoded string.
 */
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

/**
 * Extracts the plain text body from a raw email string.
 * It also attempts to detect the charset from the email headers.
 *
 * @param string $raw_email The full raw email content.
 * @param string|null &$detected_charset A variable to store the detected charset.
 * @return string|null The decoded plain text body.
 */
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