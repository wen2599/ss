<?php
// This script is for testing the authentication flow.
// It should be run from the command line: php auth_test.php

require_once '../bootstrap.php';

// --- Test Configuration ---
$base_url = 'http://localhost'; // Replace with your actual local dev URL
$test_user = [
    'email' => 'testuser_' . uniqid() . '@example.com',
    'password' => 'password123'
];

// --- cURL Helper ---
function perform_curl_request($url, $method = 'POST', $data = [], &$cookies = '') {
    $ch = curl_init();
    $payload = json_encode($data);

    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Content-Length: ' . strlen($payload)
    ]);
    curl_setopt($ch, CURLOPT_COOKIE, $cookies);
    curl_setopt($ch, CURLOPT_COOKIEJAR, 'cookie.txt');
    curl_setopt($ch, CURLOPT_COOKIEFILE, 'cookie.txt');
    curl_setopt($ch, CURLOPT_HEADER, true);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $header = substr($response, 0, $header_size);
    $body = substr($response, $header_size);
    curl_close($ch);

    // Extract cookies from header
    preg_match_all('/^Set-Cookie:\s*([^;]*)/mi', $header, $matches);
    $cookies = implode('; ', $matches[1]);

    return ['status' => $http_code, 'body' => json_decode($body, true)];
}

// --- Test Functions ---

function test_registration(&$cookies) {
    global $base_url, $test_user;
    echo "--- Testing Registration ---\n";

    $data = [
        'action' => 'register',
        'email' => $test_user['email'],
        'password' => $test_user['password']
    ];
    $response = perform_curl_request("{$base_url}/api/auth.php", 'POST', $data, $cookies);

    if ($response['status'] === 201 && isset($response['body']['user'])) {
        echo "✅ Registration successful.\n";
        return true;
    } else {
        echo "❌ Registration failed. Status: {$response['status']}\n";
        print_r($response['body']);
        return false;
    }
}

function test_logout(&$cookies) {
    global $base_url;
    echo "\n--- Testing Logout ---\n";

    $data = ['action' => 'logout'];
    $response = perform_curl_request("{$base_url}/api/auth.php", 'POST', $data, $cookies);

    if ($response['status'] === 200) {
        echo "✅ Logout successful.\n";
        $cookies = ''; // Clear cookies after logout
        return true;
    } else {
        echo "❌ Logout failed. Status: {$response['status']}\n";
        print_r($response['body']);
        return false;
    }
}

function test_login(&$cookies) {
    global $base_url, $test_user;
    echo "\n--- Testing Login ---\n";

    $data = [
        'action' => 'login',
        'email' => $test_user['email'],
        'password' => $test_user['password']
    ];
    $response = perform_curl_request("{$base_url}/api/auth.php", 'POST', $data, $cookies);

    if ($response['status'] === 200 && isset($response['body']['user'])) {
        echo "✅ Login successful.\n";
        return true;
    } else {
        echo "❌ Login failed. Status: {$response['status']}\n";
        print_r($response['body']);
        return false;
    }
}

function test_session_check(&$cookies) {
    global $base_url;
    echo "\n--- Testing Session Check ---\n";

    $response = perform_curl_request("{$base_url}/api/check-session.php", 'GET', [], $cookies);

    if ($response['status'] === 200 && $response['body']['loggedIn'] === true) {
        echo "✅ Session check successful (Logged In).\n";
        return true;
    } else {
        echo "❌ Session check failed (Expected Logged In). Status: {$response['status']}\n";
        print_r($response['body']);
        return false;
    }
}

function test_session_check_logged_out(&$cookies) {
    global $base_url;
    echo "\n--- Testing Session Check (Logged Out) ---\n";

    $response = perform_curl_request("{$base_url}/api/check-session.php", 'GET', [], $cookies);

    if ($response['status'] === 401 && $response['body']['loggedIn'] === false) {
        echo "✅ Session check successful (Logged Out).\n";
        return true;
    } else {
        echo "❌ Session check failed (Expected Logged Out). Status: {$response['status']}\n";
        print_r($response['body']);
        return false;
    }
}

function cleanup_test_user() {
    global $db_connection, $test_user;
    echo "\n--- Cleaning up test user ---\n";
    $stmt = $db_connection->prepare("DELETE FROM users WHERE email = ?");
    $stmt->bind_param("s", $test_user['email']);
    if ($stmt->execute()) {
        echo "✅ Test user deleted successfully.\n";
    } else {
        echo "❌ Failed to delete test user.\n";
    }
    $stmt->close();
}


// --- Run Test Suite ---
$cookies = '';

if (test_registration($cookies)) {
    test_session_check($cookies);
    test_logout($cookies);
    test_session_check_logged_out($cookies);
    test_login($cookies);
    test_session_check($cookies);
}

cleanup_test_user();
@unlink('cookie.txt');
