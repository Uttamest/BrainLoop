<?php
declare(strict_types=1);

/**
 * BrainLoop — Application Entry Point
 *
 * Supports installation in any XAMPP htdocs subdirectory
 * without requiring hardcoded project paths.
 */

// Load URL and path utilities.
require_once __DIR__ . '/app/core/urls.php';

// Define the application base path once.
if (!defined('BRAINLOOP_BASE_PATH')) {
    $scriptName = $_SERVER['SCRIPT_NAME'] ?? '/index.php';

    if (!is_string($scriptName) || $scriptName === '') {
        $scriptName = '/index.php';
    }

    define(
        'BRAINLOOP_BASE_PATH',
        detect_base_path($scriptName)
    );
}

// Load the public application entry point.
require __DIR__ . '/public/index.php';
