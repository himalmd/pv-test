<?php

declare(strict_types=1);

/**
 * PHPUnit test bootstrap.
 *
 * Loads autoloader and sets up test environment.
 */

// Load composer autoloader
require_once __DIR__ . '/../vendor/autoload.php';

// Set error reporting for tests
error_reporting(E_ALL);
ini_set('display_errors', '1');

// Set timezone for consistent test results
date_default_timezone_set('UTC');
