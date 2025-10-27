<?php
require_once '../bootstrap.php';
require_once 'AuthController.php';

$authController = new AuthController();
$authController->handleRequest();
