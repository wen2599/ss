<?php
// backend/utils/jwt_handler.php

class JWTHandler {
    private static function base64url_encode($data) {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private static function base64url_decode($data) {
        return base64_decode(strtr($data, '-_', '+/'));
    }

    public static function generate_token($user_id) {
        $secret = $_ENV['JWT_SECRET'];
        $expiration = $_ENV['JWT_EXPIRATION'];

        $header = self::base64url_encode(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
        
        $payload = self::base64url_encode(json_encode([
            'user_id' => $user_id,
            'iat' => time(), // Issued at
            'exp' => time() + $expiration // Expiration time
        ]));

        $signature = self::base64url_encode(hash_hmac('sha256', "$header.$payload", $secret, true));

        return "$header.$payload.$signature";
    }

    public static function validate_token($token) {
        $secret = $_ENV['JWT_SECRET'];
        $parts = explode('.', $token);

        if (count($parts) !== 3) {
            return null; // Invalid token structure
        }

        list($header, $payload, $signature) = $parts;

        $expected_signature = self::base64url_encode(hash_hmac('sha256', "$header.$payload", $secret, true));

        if ($signature !== $expected_signature) {
            return null; // Invalid signature
        }

        $decoded_payload = json_decode(self::base64url_decode($payload), true);

        if ($decoded_payload['exp'] < time()) {
            return null; // Token expired
        }

        return $decoded_payload; // Token is valid
    }
}
