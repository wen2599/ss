<?php
// public/index.php

// Set the base path for the entire application
$basePath = __DIR__ . '/../';

// The actual, more complex logic is in backend/public/index.php
// We simply include it here. This keeps the public root clean.
require_once $basePath . 'backend/public/index.php';
