<?php
require_once '../bootstrap.php';
require_once 'db.php'; // Include the database connection
require_once 'AuthController.php';

global $db_connection; // Access the global database connection
$authController = new AuthController($db_connection);
$authController->check_session();
