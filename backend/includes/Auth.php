<?php
class Auth {
    public static function generateToken($userId) {
        $secret = getenv('JWT_SECRET');
        $header = json_encode(['typ' => 'JWT', 'alg' => 'HS256']);
        $payload = json_encode(['user_id' => $userId, 'exp' => time() + (60*60*24)]); // 24小时过期
        
        $base64UrlHeader = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($header));
        $base64UrlPayload = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($payload));
        
        $signature = hash_hmac('sha256', $base64UrlHeader . "." . $base64UrlPayload, $secret, true);
        $base64UrlSignature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));
        
        return $base64UrlHeader . "." . $base64UrlPayload . "." . $base64UrlSignature;
    }

    public static function verifyToken() {
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if (!preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
            return null;
        }
        $token = $matches[1];
        $parts = explode('.', $token);
        if (count($parts) !== 3) return null;

        list($header, $payload, $signature) = $parts;
        $secret = getenv('JWT_SECRET');
        
        $signatureCheck = hash_hmac('sha256', $header . "." . $payload, $secret, true);
        $signatureCheck = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signatureCheck));

        if ($signature !== $signatureCheck) return null;

        $payloadData = json_decode(base64_decode(str_replace(['-', '_'], ['+', '/'], $payload)), true);
        if ($payloadData['exp'] < time()) return null; // Token expired
        
        return $payloadData;
    }
}
