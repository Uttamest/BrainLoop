<?php
declare(strict_types=1);

/** Read-only local diagnostics. No credentials, learning records, or server data are changed. */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
define('BRAINLOOP_CLI', true);
echo 'PHP ' . PHP_VERSION . PHP_EOL;
if (PHP_VERSION_ID < 80200 || !extension_loaded('pdo_mysql')) {
    fwrite(STDERR, "Use PHP 8.2+ with PDO MySQL enabled. Run php --ini and php -m.\n");
    exit(1);
}
echo "PDO MySQL: enabled\n";
try {
    require dirname(__DIR__) . '/app/bootstrap.php';
    $connection = db();
    echo 'Database server: ' . $connection->query('SELECT VERSION()')->fetchColumn() . PHP_EOL;
    echo 'Connection timezone: ' . $connection->query('SELECT @@session.time_zone')->fetchColumn() . PHP_EOL;
    echo 'Connection charset: ' . $connection->query('SELECT @@character_set_connection')->fetchColumn() . PHP_EOL;
    $tables = (int) $connection->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name IN ('users','decks','flashcards','card_progress','review_history','login_attempts') AND engine='InnoDB'")->fetchColumn();
    echo 'Required InnoDB tables: ' . $tables . "/6\n";
    if ($tables !== 6) { fwrite(STDERR, "Schema is incomplete. Follow README setup instructions for the intended database.\n"); exit(1); }
    echo "Read-only connection and schema checks passed. No private account or study records were read.\n";
} catch (Throwable) {
    fwrite(STDERR, "Database connection check failed. Start MySQL and verify private connection settings. Administrator logs may contain further details. No credentials are printed by this check.\n");
    exit(1);
}
