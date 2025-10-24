#!/bin/sh
echo "--- Running Backend Diagnostic Test ---"
echo "This script will use curl to test the diag_test.php script directly on the server."
echo ""

# Change to the script's directory to ensure correct relative paths
cd "$(dirname "$0")"

echo "Attempting to run diag_test.php with php-cgi..."
php-cgi -f ./diag_test.php

echo ""
echo "--- Test Complete ---"
