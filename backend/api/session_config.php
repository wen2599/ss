<?php
// backend/api/session_config.php

// This configuration ensures that session cookies work correctly
// when the frontend and backend are on different domains, proxied by Cloudflare.

session_set_cookie_params([
    'lifetime' => 86400 * 7, // 7 days
    'path' => '/',
    // By not setting a 'domain', the browser will default to the origin,
    // which is the correct behavior for a proxied setup.
    'secure' => true,   // The cookie will only be sent over HTTPS connections.
    'httponly' => true, // The cookie cannot be accessed by client-side scripts, which helps prevent XSS attacks.
    'samesite' => 'None' // Required for the cookie to be sent in cross-site requests. 'secure' must be true.
]);
?>
