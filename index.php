<?php
declare(strict_types=1);

// The project can be copied intact into any XAMPP htdocs subfolder.
require_once __DIR__ . '/app/core/urls.php';
define('BRAINLOOP_BASE_PATH', detect_base_path((string) ($_SERVER['SCRIPT_NAME'] ?? '/index.php')));
require __DIR__ . '/public/index.php';
