<?php
// backend/config.php

// --- Security ---
// This secret must match the one in the Cloudflare Worker script.
define('WORKER_SECRET', '816429fb-1649-4e48-9288-7629893311a6');

// --- Database Credentials ---
// Replace with your actual database connection details.
define('DB_HOST', 'localhost');
define('DB_USER', 'your_db_user');
define('DB_PASS', 'your_db_password');
define('DB_NAME', 'your_db_name');

// --- File Uploads ---
// The directory where email files and attachments will be stored.
define('UPLOADS_DIR', __DIR__ . '/uploads');
?>