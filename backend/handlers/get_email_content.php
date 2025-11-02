<?php
// backend/handlers/get_email_content.php

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    send_json_response(['status' => 'error', 'message' => 'Invalid request method.'], 405);
}

if (!isset($current_user_id)) {
     send_json_response(['status' => 'error', 'message' => 'Authentication required.'], 401);
}

$email_id = $_GET['id'] ?? null;
if (!$email_id) {
    send_json_response(['status' => 'error', 'message' => 'Email ID is required.'], 400);
}

try {
    $stmt = $pdo->prepare("SELECT raw_email FROM user_emails WHERE id = ? AND user_id = ?");
    $stmt->execute([$email_id, $current_user_id]);
    $email = $stmt->fetch();

    if (!$email) {
        send_json_response(['status' => 'error', 'message' => 'Email not found.'], 404);
        exit;
    }

    $raw_email = $email['raw_email'];

    // --- Advanced MIME Parsing ---

    // Split headers and body
    $parts = explode("\r\n\r\n", $raw_email, 2);
    $headers_part = $parts[0];
    $body_part = $parts[1] ?? '';

    // Get the boundary for multipart messages
    $boundary = null;
    if (preg_match('/boundary="([^"]+)"/i', $headers_part, $matches)) {
        $boundary = $matches[1];
    }

    $html_body = null;
    $text_body = null;

    if ($boundary) {
        // --- Multipart Message ---
        $body_parts = explode('--' . $boundary, $body_part);

        foreach ($body_parts as $part) {
            if (empty(trim($part))) continue;

            $part_headers = '';
            $part_body = '';

            $part_split = explode("\r\n\r\n", $part, 2);
            $part_headers = $part_split[0];
            $part_body = $part_split[1] ?? '';

            $content_type = '';
            if (preg_match('/Content-Type: ([^\s;]+)/i', $part_headers, $matches)) {
                $content_type = strtolower($matches[1]);
            }

            $encoding = '';
            if (preg_match('/Content-Transfer-Encoding: (\S+)/i', $part_headers, $matches)) {
                $encoding = strtolower(trim($matches[1]));
            }

            // Decode the body based on encoding
            $decoded_part_body = $part_body;
            if ($encoding === 'base64') {
                $decoded_part_body = base64_decode($part_body);
            } elseif ($encoding === 'quoted-printable') {
                $decoded_part_body = quoted_printable_decode($part_body);
            }

            if ($content_type === 'text/html') {
                $html_body = $decoded_part_body;
            } elseif ($content_type === 'text/plain') {
                $text_body = $decoded_part_body;
            }
        }
    } else {
        // --- Single Part Message ---
        $encoding = '';
        if (preg_match('/Content-Transfer-Encoding: (\S+)/i', $headers_part, $matches)) {
            $encoding = strtolower(trim($matches[1]));
        }

        $decoded_body = $body_part;
        if ($encoding === 'base64') {
            $decoded_body = base64_decode($body_part);
        } elseif ($encoding === 'quoted-printable') {
            $decoded_body = quoted_printable_decode($body_part);
        }

        $content_type = '';
        if (preg_match('/Content-Type: ([^\s;]+)/i', $headers_part, $matches)) {
            $content_type = strtolower($matches[1]);
        }

        if ($content_type === 'text/html') {
            $html_body = $decoded_body;
        } else {
            // Assume plain text if not HTML
            $text_body = $decoded_body;
        }
    }

    // Prioritize HTML body, fall back to plain text, then to an empty string.
    $final_body = $html_body ?? $text_body ?? '';

    send_json_response(['status' => 'success', 'data' => ['body' => $final_body]]);

} catch (PDOException $e) {
    error_log("Get email content error: " . $e->getMessage());
    send_json_response(['status' => 'error', 'message' => 'Failed to fetch email content.'], 500);
}
