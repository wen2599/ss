#!/bin/bash

# ==============================================================================
#  Diagnostic Script to Test Server Handling of POST Requests
# ==============================================================================
#  This script is designed to be run directly on your server via an SSH
#  connection. It tests whether your server is correctly processing
#  HTTP POST requests, which are essential for features like registration
#  and login.
# ==============================================================================

# --- Configuration ---
# Please set this to the publicly accessible URL of your backend.
# The script will test the test_post.php file located in your api directory.
BACKEND_URL="https://wenge.cloudns.ch"
# --- End of Configuration ---

TARGET_URL="${BACKEND_URL}/test_post.php"

echo "Starting server diagnostics..."
echo "Target URL: ${TARGET_URL}"
echo "------------------------------------------------------"
echo ""

# --- Test 1: GET Request ---
# This first test ensures that the diagnostic file is correctly uploaded
# and accessible from the web. You should see a success message from the script.
echo "[TEST 1/2] Sending a GET request to the server..."
echo "This will verify the script is accessible."
echo ""
curl --verbose "${TARGET_URL}"
echo ""
echo "------------------------------------------------------"
echo ""

# --- Test 2: POST Request ---
# This is the critical test. It sends a POST request to the same file.
# If your server is blocking POST requests, this command will likely fail
# with an error like "502 Request rejected" or a similar message.
# A successful response MUST contain the line:
# "SUCCESS: The server correctly received a POST request."
echo "[TEST 2/2] Sending a POST request to the server..."
echo "This is the critical test to check for a POST block."
echo ""
curl --verbose -X POST --data "test=true" "${TARGET_URL}"
echo ""
echo "------------------------------------------------------"
echo ""
echo "Diagnostic complete."
echo ""
echo "--- How to Interpret the Results ---"
echo "=> If TEST 1 failed, ensure you have uploaded the 'test_post.php' file to the 'backend/api/' directory."
echo "=> If TEST 2 failed (e.g., showed a 502 error) but TEST 1 succeeded, you have confirmed that your server is blocking POST requests."
echo "   You should provide the output of this script to your hosting provider's support team as proof."
echo "------------------------------------------------------"
