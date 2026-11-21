<?php
declare(strict_types=1);
namespace App\Core;

use PDO;
use RuntimeException;

final class Database
{
    private static ?PDO $pdo = null;

    public static function driver(): string { return (string) config()['database']['driver']; }

    public static function mysqlConnection(bool $withDatabase = true, ?array $settings = null): PDO
    {
        if (!extension_loaded('pdo_mysql')) { throw new RuntimeException('Enable pdo_mysql in your PHP configuration. See README.'); }
        $settings ??= config()['database'];
        if (!preg_match('/^[A-Za-z0-9_.:-]+$/D', (string) $settings['host']) || (int) $settings['port'] < 1 || (int) $settings['port'] > 65535) { throw new RuntimeException('Invalid database host or port.'); }
        $name = self::databaseName((string) $settings['name']);
        $dsn = 'mysql:host=' . $settings['host'] . ';port=' . (int) $settings['port'] . ($withDatabase ? ';dbname=' . $name : '') . ';charset=utf8mb4';
        $pdo = new PDO($dsn, (string) $settings['user'], (string) $settings['password'], self::options());
        $pdo->exec("SET time_zone = '+00:00'");
        $pdo->exec("SET SESSION sql_mode = 'STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION,ONLY_FULL_GROUP_BY'");
        return $pdo;
    }

    public static function databaseName(string $name): string
    {
        if (!preg_match('/^[A-Za-z0-9_]{1,64}$/D', $name)) { throw new RuntimeException('Use only letters, numbers, and underscores in the database name (maximum 64 characters).'); }
        return $name;
    }

    private static function options(): array
    {
        return [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false];
    }

    public static function connection(): PDO
    {
        if (self::$pdo !== null) { return self::$pdo; }
        if (self::driver() === 'mysql') { return self::$pdo = self::mysqlConnection(); }
        if (self::driver() !== 'sqlite') { throw new RuntimeException('Database driver must be mysql or sqlite.'); }
        if (!extension_loaded('pdo_sqlite')) { throw new RuntimeException('PDO SQLite is required for the optional SQLite test/legacy driver.'); }
        $path = config()['db_path'];
        if ($path !== ':memory:') {
            $directory = dirname($path);
            if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) { throw new RuntimeException('Unable to create private database directory.'); }
        }
        self::$pdo = new PDO('sqlite:' . $path, null, null, self::options());
        self::$pdo->exec('PRAGMA foreign_keys = ON');
        self::$pdo->exec('PRAGMA busy_timeout = 5000');
        self::$pdo->exec('PRAGMA journal_mode = WAL');
        return self::$pdo;
    }

    public static function migrate(?PDO $connection = null): void
    {
        $pdo = $connection ?? self::connection();
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        $file = dirname(__DIR__, 2) . '/database/schema.' . $driver . '.sql';
        $schema = file_get_contents($file);
        if ($schema === false) { throw new RuntimeException('The database schema file is missing.'); }
        // The checked-in schema contains CREATE statements without stored routines.
        foreach (explode(';', $schema) as $statement) {
            if (trim($statement) !== '') { $pdo->exec($statement); }
        }
    }
}
