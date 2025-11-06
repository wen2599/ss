<?php
// JWT generation and validation
require_once __DIR__ . '/config.php';

function base64url_encode($data) {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function base64url_decode($data) {
    return base64_decode(strtr($data, '-_', '+/'));
}

function generate_jwt($user_id, $email) {
    global $config;
    $secret = $config['JWT_SECRET_KEY'];

    $header = json_encode(['typ' => 'JWT', 'alg' => 'HS256']);
    $payload = json_encode([
        'user_id' => $user_id,
        'email' => $email,
        'iat' => time(),
        'exp' => time() + (60 * 60 * 24) // 24 hours
    ]);

    $base64UrlHeader = base64url_encode($header);
    $base64UrlPayload = base64url_encode($payload);
    $signature = hash_hmac('sha256', $base64UrlHeader . "." . $base64UrlPayload, $secret, true);
    $base64UrlSignature = base64url_encode($signature);

    return $base64UrlHeader . "." . $base64UrlPayload . "." . $base64UrlSignature;
}

function validate_jwt() {
    global $config;
    $secret = $config['JWT_SECRET_KEY'];
    $headers = getallheaders();

    if (!isset($headers['Authorization'])) {
        return false;
    }

    $auth_header = $headers['Authorization'];
    if (preg_match('/Bearer\s(\S+)/', $auth_header, $matches)) {
        $jwt = $matches[1];
    } else {
        return false;
    }

    $token_parts = explode('.', $jwt);
    if (count($token_parts) !== 3) {
        return false;
    }

    list($header, $payload, $signature) = $token_parts;

    $signature_provided = base64url_decode($signature);
    $base64UrlHeader = $header;
    $base64UrlPayload = $payload;

    $expected_signature = hash_hmac('sha256', $base64UrlHeader . "." . $base64UrlPayload, $secret, true);

    if (!hash_equals($expected_signature, $signature_provided)) {
        return false;
    }

    $payload_data = json_decode(base64url_decode($payload), true);
    if ($payload_data['exp'] < time()) {
        return false; // Token expired
    }

    return $payload_data;
}
