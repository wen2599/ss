<?php
// 文件名: auth.php
// 路径: core/auth.php
require_once __DIR__ . '/../config.php';

function base64UrlEncode($data) { return rtrim(strtr(base64_encode($data), '+/', '-_'), '='); }
function base64UrlDecode($data) { return base64_decode(str_pad(strtr($data, '-_', '+/'), strlen($data) % 4, '=', STR_PAD_RIGHT)); }

function generate_jwt($user_id, $email) {
    $header = json_encode(['typ' => 'JWT', 'alg' => 'HS256']);
    $payload = json_encode(['user_id' => $user_id, 'email' => $email, 'exp' => time() + JWT_EXPIRATION_TIME]);
    $base64UrlHeader = base64UrlEncode($header);
    $base64UrlPayload = base64UrlEncode($payload);
    $signature = hash_hmac('sha256', $base64UrlHeader . "." . $base64UrlPayload, JWT_SECRET, true);
    $base64UrlSignature = base64UrlEncode($signature);
    return $base64UrlHeader . "." . $base64UrlPayload . "." . $base64UrlSignature;
}

function get_auth_user() {
    $headers = getallheaders();
    if (!isset($headers['Authorization'])) return null;
    list($jwt) = sscanf($headers['Authorization'], 'Bearer %s');
    if (!$jwt) return null;
    try {
        $tokenParts = explode('.', $jwt);
        $payload = base64UrlDecode($tokenParts[1]);
        $decoded_payload = json_decode($payload);
        if ($decoded_payload->exp < time()) return null; // Expired
        $header = base64UrlDecode($tokenParts[0]);
        $base64UrlHeader = base64UrlEncode($header);
        $base64UrlPayload = base64UrlEncode($payload);
        $signature = hash_hmac('sha256', $base64UrlHeader . "." . $base64UrlPayload, JWT_SECRET, true);
        $base64UrlSignature = base64UrlEncode($signature);
        if ($base64UrlSignature !== $tokenParts[2]) return null; // Invalid signature
        return $decoded_payload;
    } catch (Exception $e) { return null; }
}