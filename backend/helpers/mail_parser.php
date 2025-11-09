<?php
// File: backend/helpers/mail_parser.php

if (!function_exists('parse_email_body')) {
    /**
     * 从原始 MIME 邮件文本中解析并解码出最合适的用户正文。
     * 优先提取 text/plain 部分。
     *
     * @param string $raw_email 完整的原始邮件内容。
     * @return string 清理和解码后的正文文本。
     */
    function parse_email_body(string $raw_email): string {
        // 1. 分离邮件头和邮件体
        $parts = explode("\r\n\r\n", $raw_email, 2);
        if (count($parts) !== 2) {
            return $raw_email; // 格式不规范，返回原文
        }
        list($headers_raw, $body_raw) = $parts;

        // 2. 解析邮件头的 Content-Type
        if (!preg_match('/Content-Type: (.*)/i', $headers_raw, $matches)) {
            return $body_raw; // 没有 Content-Type，直接返回邮件体
        }
        $content_type_header = $matches[1];

        // 3. 如果不是 multipart 邮件，直接解码邮件体
        if (strpos(strtolower($content_type_header), 'multipart/') === false) {
            if (preg_match('/Content-Transfer-Encoding: (base64|quoted-printable)/i', $headers_raw, $encoding_match)) {
                return decode_body_part($body_raw, $encoding_match[1]);
            }
            return $body_raw;
        }

        // 4. 如果是 multipart 邮件，解析 boundary
        if (!preg_match('/boundary="?([^"]+)"?/i', $content_type_header, $boundary_match)) {
            return $body_raw; // 找不到 boundary，无法解析
        }
        $boundary = $boundary_match[1];

        // 5. 按 boundary 分割邮件体
        $body_parts = explode('--' . $boundary, $body_raw);
        array_shift($body_parts); // 移除第一个空部分
        array_pop($body_parts);   // 移除最后一个 '--' 结尾的部分

        $plain_text_body = '';
        $html_body = '';

        // 6. 遍历每个部分，寻找 text/plain 和 text/html
        foreach ($body_parts as $part) {
            if (empty(trim($part))) continue;
            
            $part_parts = explode("\r\n\r\n", $part, 2);
            if (count($part_parts) !== 2) continue;
            list($part_header_raw, $part_body_raw) = $part_parts;

            // 检查当前部分的 Content-Type
            if (preg_match('/Content-Type: text\/plain/i', $part_header_raw)) {
                if (preg_match('/Content-Transfer-Encoding: (base64|quoted-printable)/i', $part_header_raw, $encoding_match)) {
                    $plain_text_body = decode_body_part($part_body_raw, $encoding_match[1]);
                } else {
                    $plain_text_body = $part_body_raw;
                }
            } elseif (preg_match('/Content-Type: text\/html/i', $part_header_raw)) {
                if (preg_match('/Content-Transfer-Encoding: (base64|quoted-printable)/i', $part_header_raw, $encoding_match)) {
                    // 解码HTML并去除所有HTML标签，得到纯文本
                    $html_body = strip_tags(decode_body_part($part_body_raw, $encoding_match[1]));
                } else {
                    $html_body = strip_tags($part_body_raw);
                }
            }
        }

        // 7. 优先返回纯文本正文，如果不存在则返回从HTML中提取的文本
        return trim($plain_text_body) ?: trim($html_body) ?: '无法解析邮件正文';
    }
}

if (!function_exists('decode_body_part')) {
    /**
     * 解码 base64 或 quoted-printable 编码的邮件体部分。
     * @param string $body
     * @param string $encoding
     * @return string
     */
    function decode_body_part(string $body, string $encoding): string {
        $encoding = strtolower(trim($encoding));
        if ($encoding === 'base64') {
            return base64_decode($body);
        } elseif ($encoding === 'quoted-printable') {
            return quoted_printable_decode($body);
        }
        return $body; // 如果编码不认识，返回原文
    }
}
?>