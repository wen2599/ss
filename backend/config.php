<?php

// --- JWT Configuration ---
// IMPORTANT: This key should be long, random, and kept secret in a production environment.
// You can generate a strong key using a tool like `openssl rand -base64 32`.
define('JWT_SECRET_KEY', 'your-super-secret-and-long-key-that-no-one-knows');

// --- Token Lifetime ---
// Define how long the token is valid for. 24 hours is a common choice.
define('JWT_TOKEN_LIFETIME', 86400); // 86400 seconds = 24 hours

?>
