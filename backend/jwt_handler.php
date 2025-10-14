<?php

require_once 'config.php';

/**
 * URL-safe Base64 encoding.
 * @param string $data The string to encode.
 * @return string The encoded string.
 */
function base64_url_encode($data) {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

/**
 * URL-safe Base64 decoding.
 * @param string $data The string to decode.
 * @return string The decoded string.
 */
function base64_url_decode($data) {
    return base64_decode(strtr($data, '-_', '+/') . str_repeat('=', 3 - (3 + strlen($data)) % 4));
}

/**
 * Generates a JSON Web Token (JWT).
 * @param array $payload The payload to include in the token.
 * @return string The generated JWT.
 */
function generate_jwt($payload) {
    // Create the token header
    $header = json_encode(['typ' => 'JWT', 'alg' => 'HS256']);
    $base64UrlHeader = base64_url_encode($header);

    // Add standard claims (iat, exp) to the payload
    $payload['iat'] = time(); // Issued At
    $payload['exp'] = time() + JWT_TOKEN_LIFETIME; // Expiration Time
    $base64UrlPayload = base64_url_encode(json_encode($payload));

    // Create the signature
    $signature = hash_hmac('sha256', $base64UrlHeader . "." . $base64UrlPayload, JWT_SECRET_KEY, true);
    $base64UrlSignature = base64_url_encode($signature);

    // Concatenate the three parts to form the JWT
    return $base64UrlHeader . "." . $base64UrlPayload . "." . $base64UrlSignature;
}

/**
 * Validates a JWT and returns its payload.
 * @param string $jwt The token to validate.
 * @return array|null The payload if the token is valid, null otherwise.
 */
function validate_jwt($jwt) {
    $parts = explode('.', $jwt);
    if (count($parts) !== 3) {
        return null; // Invalid token format
    }

    list($base64UrlHeader, $base64UrlPayload, $base64UrlSignature) = $parts;

    // Re-create the signature
    $signature = hash_hmac('sha256', $base64UrlHeader . "." . $base64UrlPayload, JWT_SECRET_KEY, true);
    $expectedSignature = base64_url_decode($base64UrlSignature);

    // Compare the signatures
    if (!hash_equals($signature, $expectedSignature)) {
        return null; // Invalid signature
    }

    // Decode the payload
    $payload = json_decode(base64_url_decode($base64UrlPayload), true);

    // Check expiration
    if ($payload['exp'] < time()) {
        return null; // Token expired
    }

    return $payload;
}

?>
