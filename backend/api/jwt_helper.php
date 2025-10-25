<?php
// backend/api/jwt_helper.php

if (!function_exists('base64url_encode')) {
    function base64url_encode($data) {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}

if (!function_exists('base64url_decode')) {
    function base64url_decode($data) {
        return base64_decode(strtr($data, '-_', '+/') . str_repeat('=', 3 - (3 + strlen($data)) % 4));
    }
}

/**
 * Generates a JSON Web Token (JWT).
 *
 * @param array $payload The payload to include in the token.
 * @param string $secret The secret key for signing.
 * @param int $expiration_hours How many hours the token should be valid for.
 * @return string The generated JWT.
 */
function generate_jwt($payload, $secret, $expiration_hours = 24)
{
    // Create token header
    $header = json_encode(['typ' => 'JWT', 'alg' => 'HS256']);

    // Add standard claims to payload
    $payload['iat'] = time(); // Issued at
    $payload['exp'] = time() + ($expiration_hours * 60 * 60); // Expiration time

    // Encode Header and Payload
    $base64UrlHeader = base64url_encode($header);
    $base64UrlPayload = base64url_encode(json_encode($payload));

    // Create Signature Hash
    $signature = hash_hmac('sha256', $base64UrlHeader . "." . $base64UrlPayload, $secret, true);

    // Encode Signature
    $base64UrlSignature = base64url_encode($signature);

    // Return the complete token
    return $base64UrlHeader . "." . $base64UrlPayload . "." . $base64UrlSignature;
}

/**
 * Validates a JSON Web Token (JWT).
 *
 * @param string $jwt The token to validate.
 * @param string $secret The secret key used for signing.
 * @return object|null The decoded payload on success, null on failure.
 */
function validate_jwt($jwt, $secret)
{
    // Split the token
    $tokenParts = explode('.', $jwt);
    if (count($tokenParts) !== 3) {
        return null; // Invalid token structure
    }

    $header = base64url_decode($tokenParts[0]);
    $payload = base64url_decode($tokenParts[1]);
    $signature_provided = $tokenParts[2];

    // Check expiration
    $payload_decoded = json_decode($payload);
    if ($payload_decoded === null || !isset($payload_decoded->exp)) {
        return null; // Invalid payload
    }
    if ($payload_decoded->exp < time()) {
        return null; // Token has expired
    }

    // Build a signature based on the received data
    $base64UrlHeader = base64url_encode($header);
    $base64UrlPayload = base64url_encode($payload);
    $signature = hash_hmac('sha256', $base64UrlHeader . "." . $base64UrlPayload, $secret, true);
    $base64UrlSignature = base64url_encode($signature);

    // Verify the signature
    if (hash_equals($base64UrlSignature, $signature_provided)) {
        return $payload_decoded; // Signature is valid
    } else {
        return null; // Invalid signature
    }
}
