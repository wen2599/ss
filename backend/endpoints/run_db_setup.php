<?php
// backend/endpoints/run_db_setup.php
// A web-accessible trigger for the database setup script.

// Set a long timeout to ensure the script can finish.
set_time_limit(300);

// Use preformatted text for clean output in the browser.
echo '<pre>';

// Define the path to the main setup script.
// __DIR__ is /backend/endpoints, so we go one level up to /backend.
$setup_script_path = dirname(__DIR__) . '/temporary_db_setup.php';

if (file_exists($setup_script_path)) {
    echo "Attempting to run database setup script...\n\n";
    // Capture the output of the script to display it.
    ob_start();
    include $setup_script_path;
    $output = ob_get_clean();
    echo htmlspecialchars($output); // Sanitize output for browser display
} else {
    echo "ERROR: The main setup script was not found at: " . htmlspecialchars($setup_script_path);
}

echo '</pre>';
?>