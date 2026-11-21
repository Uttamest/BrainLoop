<?php
declare(strict_types=1);

// Copy to config.local.php and enter your credentials, or run setup-xampp.php.
// Environment variables take precedence. Never commit the real private file.
return [
    'env' => 'development',
    'https' => false,
    'database' => [
        'driver' => 'mysql',
        'host' => '127.0.0.1',
        'port' => 3306,
        'name' => 'brainloop',
        'user' => 'brainloop',
        'password' => 'ENTER_YOUR_DATABASE_PASSWORD',
    ],
];
