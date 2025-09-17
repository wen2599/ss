<?php
// backend/api/session_config.php

// This configuration ensures that session cookies work correctly
// when the frontend and backend are on different domains.

// The frontend is on a subdomain of .wenxiuxiu.eu.org
// The backend is on a different domain, but proxied through the same Cloudflare Worker.
// We need to set the cookie domain to allow subdomains and be sent cross-site.

// Set cookie parameters BEFORE starting the session.
session_set_cookie_params([
    'lifetime' => 86400 * 7, // 7 days
    'path' => '/',
    'domain' => '.wenxiuxiu.eu.org', // The leading dot allows the cookie to be shared across all subdomains.
    'secure' => true,   // The cookie will only be sent over HTTPS connections.
    'httponly' => true, // The cookie cannot be accessed by client-side scripts, which helps prevent XSS attacks.
    'samesite' => 'None' // Required for the cookie to be sent in cross-site requests (e.g., from your frontend domain to the proxied backend).
]);
?>
