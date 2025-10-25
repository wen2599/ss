<?php
// backend/bootstrap.php

// 1. Load Environment Variables from .env file
require_once __DIR__ . '/load_env.php';

// 2. Set up Database Connection
require_once __DIR__ . '/api/db.php';

// Optional: Set default timezone or other global settings here
date_default_timezone_set('UTC');

// Optional: Set global headers, like CORS. 
// Note: This is a basic example. For production, you might need more complex logic.
// header("Access-Control-Allow-Origin: *");
// header("Content-Type: application/json; charset=UTF-8");
