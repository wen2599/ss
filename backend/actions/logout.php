<?php
// Action: Log out a user

// Unset all of the session variables
$_SESSION = array();

// Destroy the session.
session_destroy();

echo json_encode(['success' => true, 'message' => 'Logged out successfully.']);
?>
