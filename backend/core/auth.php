<?php

// Helper function to encode data to Base64URL
function base64UrlEncode($data) {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

/**
 * Creates a JWT token.
 * @param array $payload The data to include in the token.
 * @return string The JWT token.
 */
function create_jwt($payload) {
    $header = json_encode(['typ' => 'JWT', 'alg' => 'HS256']);
    $header = base64UrlEncode($header);

    // JWT 过期时间从环境变量中获取，默认为1小时
    $expiration = time() + (int)($_ENV['JWT_EXPIRATION'] ?? 3600);
    $payload['exp'] = $expiration;
    $payload = json_encode($payload);
    $payload = base64UrlEncode($payload);

    $signature = hash_hmac('sha256', "$header.$payload", $_ENV['JWT_SECRET_KEY'], true);
    $signature = base64UrlEncode($signature);

    return "$header.$payload.$signature";
}

/**
 * Validates a JWT token.
 * @param string $jwt The token to validate.
 * @return array|null The decoded payload if valid, null otherwise.
 */
function validate_jwt($jwt) {
    $tokenParts = explode('.', $jwt);
    if (count($tokenParts) !== 3) {
        return null;
    }
    
    $header = $tokenParts[0];
    $payload = $tokenParts[1];
    $signatureProvided = $tokenParts[2];

    // Check expiration
    $payloadDecoded = json_decode(base64_decode(strtr($payload, '-_', '+/')), true);
    if ($payloadDecoded === null || (isset($payloadDecoded['exp']) && $payloadDecoded['exp'] < time())) {
        return null; // Token is expired or invalid payload
    }

    // Build a signature based on the header and payload using the secret
    $signature = hash_hmac('sha256', "$header.$payload", $_ENV['JWT_SECRET_KEY'], true);
    $signature = base64UrlEncode($signature);

    // Verify it matches the signature provided in the token
    if (hash_equals($signature, $signatureProvided)) {
        return $payloadDecoded;
    }

    return null;
}

/**
 * Extracts the JWT token from the Authorization header.
 * @return string|null The token or null if not found.
 */
function get_jwt_from_header() {
    $headers = getallheaders();
    $authHeader = $headers['Authorization'] ?? null;

    if ($authHeader && preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
        return $matches[1];
    }
    
    return null;
}

/**
 * Middleware function to protect an API endpoint.
 * It validates the JWT and returns the user payload or terminates with a 401 error.
 * @return array The user payload from the JWT.
 */
function require_auth() {
    $token = get_jwt_from_header();
    if (!$token) {
        error_response(401, 'Authorization token not found.');
    }
    
    $payload = validate_jwt($token);
    if (!$payload) {
        error_response(401, 'Invalid or expired token.');
    }
    
    return $payload;
}
