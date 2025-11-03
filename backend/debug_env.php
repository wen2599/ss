<?php
// backend/debug_env.php

// Enable full error reporting to catch any issues
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "<h1>Environment Debugging Script</h1>";
echo "<p>This script checks if the application can load the necessary configuration.</p>";
echo "<hr>";

// --- Step 1: Check PHP Version and Basic Info ---
echo "<h2>System Information</h2>";
echo "<p>PHP Version: " . phpversion() . "</p>";
echo "<p>Current Directory (getcwd): <code>" . getcwd() . "</code></p>";
echo "<p>Script Path (__DIR__): <code>" . __DIR__ . "</code></p>";
echo "<hr>";

// --- Step 2: Locate and Load the Config Loader ---
echo "<h2>Configuration Loader</h2>";
$config_loader_path = __DIR__ . '/utils/config_loader.php';
$absolute_config_loader_path = realpath($config_loader_path);

echo "<p>Attempting to locate config loader at: <code>" . $config_loader_path . "</code></p>";
echo "<p>Absolute path resolved to: <code>" . ($absolute_config_loader_path ? $absolute_config_loader_path : 'Not Found') . "</code></p>";

if ($absolute_config_loader_path && file_exists($absolute_config_loader_path)) {
    echo "<p style='color:green; font-weight:bold;'>Config loader file was found successfully.</p>";
    require_once $absolute_config_loader_path;
} else {
    echo "<p style='color:red; font-weight:bold;'>CRITICAL ERROR: Config loader file not found at the expected path. The application cannot proceed.</p>";
    exit;
}
echo "<hr>";


// --- Step 3: Verify the .env File Path and Content ---
echo "<h2>.env File Verification</h2>";
$dotenv_path_in_loader = __DIR__ . '/.env';
$absolute_dotenv_path = realpath($dotenv_path_in_loader);

echo "<p>The config loader is designed to find the <code>.env</code> file in the <code>backend</code> directory.</p>";
echo "<p>Checking for .env file at path: <code>" . $dotenv_path_in_loader . "</code></p>";
echo "<p>Absolute path resolved to: <code>" . ($absolute_dotenv_path ? $absolute_dotenv_path : 'Not Found') . "</code></p>";


if ($absolute_dotenv_path && file_exists($absolute_dotenv_path)) {
    echo "<p style='color:green; font-weight:bold;'>Successfully found the .env file.</p>";
} else {
    echo "<p style='color:red; font-weight:bold;'>CRITICAL ERROR: The .env file was not found at the expected path. This is the most likely cause of the problem.</p>";
    echo "<p>Please ensure a file named <code>.env</code> exists in the <code>backend/</code> directory and that the web server has permission to read it.</p>";
}
echo "<hr>";


// --- Step 4: Check if the Telegram Bot Token is Loaded ---
echo "<h2>Token Verification</h2>";
$bot_token = getenv('TELEGRAM_BOT_TOKEN');

if ($bot_token) {
    echo "<p style='color:green; font-weight:bold;'>SUCCESS: The TELEGRAM_BOT_TOKEN was loaded correctly.</p>";
    // To avoid exposing the full token, we show only a portion of it.
    echo "<p>Loaded Token (first 8 chars): <code>" . substr($bot_token, 0, 8) . "...</code></p>";
    echo "<p>If you see this message, the configuration is likely correct. The issue may be with the Telegram API or the webhook setup itself.</p>";
} else {
    echo "<p style='color:red; font-weight:bold;'>CRITICAL ERROR: TELEGRAM_BOT_TOKEN is NOT loaded.</p>";
    echo "<p>This confirms that the application cannot read its configuration. This is why the bot is unresponsive.</p>";
    echo "<p>This is usually caused by:</p>";
    echo "<ul>";
    echo "<li>The <code>.env</code> file not being found (see error in Step 3).</li>";
    echo "<li>The <code>.env</code> file not containing the line: <code>TELEGRAM_BOT_TOKEN=your_token_here</code></li>";
    echo "<li>A syntax error in the <code>.env</code> file (e.g., extra spaces around the '=').</li>";
    echo "</ul>";
}
echo "<hr>";

echo "<h2>Next Steps</h2>";
echo "<p>Please share a screenshot or copy-paste the full output of this page with me so I can diagnose the problem and provide a fix.</p>";
