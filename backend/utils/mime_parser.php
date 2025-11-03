<?php
// backend/utils/mime_parser.php

/**
 * Parses the raw content of an email to extract the most suitable body part.
 * It prefers text/html over text/plain and handles multipart messages.
 * It also decodes base64 and quoted-printable encoded content.
 *
 * @param string $raw_email The full raw email content.
 * @return array An associative array containing 'body' and 'type' (e.g., 'html' or 'text').
 */
function parse_email_body($raw_email) {
    $body = '';
    $type = 'text'; // Default to text

    // Find the main headers and the start of the body
    $header_end_pos = strpos($raw_email, "\r\n\r\n");
    if ($header_end_pos === false) {
        return ['body' => 'Could not parse email headers.', 'type' => 'text'];
    }
    $headers = substr($raw_email, 0, $header_end_pos);
    $email_body_raw = substr($raw_email, $header_end_pos + 4);

    $content_type = '';
    $boundary = '';

    // Extract Content-Type and boundary from headers
    if (preg_match('/^Content-Type:\s*multipart\/alternative;\s*boundary="?([^"]+)"?/im', $headers, $matches)) {
        $content_type = 'multipart/alternative';
        $boundary = $matches[1];
    } elseif (preg_match('/^Content-Type:\s*([^;]+)/im', $headers, $matches)) {
        $content_type = trim(strtolower($matches[1]));
    }

    if (!empty($boundary)) {
        // --- Handle Multipart Email ---
        $parts = explode('--' . $boundary, $email_body_raw);
        $html_part = '';
        $text_part = '';

        foreach ($parts as $part) {
            if (empty(trim($part)) || strpos($part, '--') === 0) {
                continue;
            }

            $part_header_end = strpos($part, "\r\n\r\n");
            if ($part_header_end === false) continue;

            $part_headers = substr($part, 0, $part_header_end);
            $part_body = substr($part, $part_header_end + 4);

            $part_content_type = '';
            $part_encoding = '';
            $part_charset = ''; // New variable for part-specific charset

            if (preg_match('/^Content-Type:\s*text\/html/im', $part_headers)) {
                $part_content_type = 'text/html';
            } elseif (preg_match('/^Content-Type:\s*text\/plain/im', $part_headers)) {
                $part_content_type = 'text/plain';
            }

            // Extract charset for this specific part
            if (preg_match('/charset="?([^"]+)"?/i', $part_headers, $charset_matches)) {
                $part_charset = strtoupper($charset_matches[1]);
            }

            if (preg_match('/^Content-Transfer-Encoding:\s*base64/im', $part_headers)) {
                $part_encoding = 'base64';
            } elseif (preg_match('/^Content-Transfer-Encoding:\s*quoted-printable/im', $part_headers)) {
                $part_encoding = 'quoted-printable';
            }

            // Decode the body
            $decoded_part_body = '';
            if ($part_encoding === 'base64') {
                // Remove newlines from base64 body before decoding
                $decoded_part_body = base64_decode(str_replace("\r\n", "", $part_body));
            } elseif ($part_encoding === 'quoted-printable') {
                $decoded_part_body = quoted_printable_decode($part_body);
            } else {
                $decoded_part_body = $part_body; // No encoding or 7bit/8bit
            }

            // Convert charset if necessary
            if (!empty($part_charset) && $part_charset !== 'UTF-8' && function_exists('mb_convert_encoding')) {
                $decoded_part_body = mb_convert_encoding($decoded_part_body, 'UTF-8', $part_charset);
            }

            if ($part_content_type === 'text/html') {
                $html_part = $decoded_part_body;
            } elseif ($part_content_type === 'text/plain') {
                $text_part = $decoded_part_body;
            }
        }

        // Prioritize HTML over plain text
        if (!empty($html_part)) {
            $body = $html_part;
            $type = 'html';
        } else {
            $body = $text_part;
            $type = 'text';
        }

    } else {
        // --- Handle Single Part Email ---
        $encoding = '';
        if (preg_match('/^Content-Transfer-Encoding:\s*base64/im', $headers)) {
            $encoding = 'base64';
        } elseif (preg_match('/^Content-Transfer-Encoding:\s*quoted-printable/im', $headers)) {
            $encoding = 'quoted-printable';
        }

        if ($encoding === 'base64') {
            $body = base64_decode($email_body_raw);
        } elseif ($encoding === 'quoted-printable') {
            $body = quoted_printable_decode($email_body_raw);
        } else {
            $body = $email_body_raw;
        }

        if ($content_type === 'text/html') {
            $type = 'html';
        }
    }

    return ['body' => $body, 'type' => $type];
}
