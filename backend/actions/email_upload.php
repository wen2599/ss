<?php
require_once __DIR__ . '/../lib/BetCalculator.php';

// 智能编码转换：优先用邮件头charset，否则自动检测
function smart_convert_encoding($text, $prefer_charset = null) {
    if (!$text) return $text;
    // 优先用邮件头 charset（GBK/GB2312都按GB18030处理更保险）
    if ($prefer_charset) {
        $prefer_charset = strtoupper($prefer_charset);
        $charset = ($prefer_charset === 'GBK' || $prefer_charset === 'GB2312') ? 'GB18030' : $prefer_charset;
        if ($charset !== 'UTF-8') {
            return mb_convert_encoding($text, 'UTF-8', $charset);
        }
    }
    // 自动检测编码
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

...
// 在主流程里这样调用
$detected_charset = null;
$text_body = get_plain_text_body_from_email($raw_email_content, $detected_charset);
if ($text_body === null) $text_body = $raw_email_content;
$text_body = smart_convert_encoding($text_body, $detected_charset);

// HTML正文也智能转码
$html_body = null;
if (isset($_FILES['html_body']) && $_FILES['html_body']['error'] === UPLOAD_ERR_OK) {
    $html_raw = file_get_contents($_FILES['html_body']['tmp_name']);
    $html_body = smart_convert_encoding($html_raw, $detected_charset);
} elseif (!empty($_POST['html_body'])) {
    $html_body = smart_convert_encoding($_POST['html_body'], $detected_charset);
}
...
