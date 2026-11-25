<?php
declare(strict_types=1);

define('BRAINLOOP_CLI', true);
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
if (version_compare(PHP_VERSION, '8.2.0', '<')) {
    fwrite(STDERR, "BrainLoop needs PHP 8.2 or newer.\n");
    exit(1);
}
require dirname(__DIR__) . '/app/bootstrap.php';
$driver = App\Core\Database::driver();
foreach (['PDO', 'pdo_' . $driver] as $extension) {
    if (!extension_loaded($extension)) {
        fwrite(STDERR, "Missing PHP extension: {$extension}. Enable it in the CLI php.ini (php --ini).\n");
        exit(1);
    }
}
try {
    if (in_array('--create-database', $argv, true)) {
        if ($driver !== 'mysql') { throw new RuntimeException('--create-database requires the mysql driver.'); }
        $name = App\Core\Database::databaseName(config()['database']['name']);
        App\Core\Database::mysqlConnection(false)->exec('CREATE DATABASE IF NOT EXISTS `' . $name . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    }
    App\Core\Database::migrate();
    echo 'BrainLoop database initialized. PHP ' . PHP_VERSION . "; PDO MySQL/MariaDB ready.\n";
    echo "No demo account or sample history was created. Register in your browser to begin.\n";
} catch (Throwable $error) {
    fwrite(STDERR, 'Setup failed: ' . $error->getMessage() . "\n");
    exit(1);
}
