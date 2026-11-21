<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use PDO;
use PDOException;
use RuntimeException;
use Throwable;

/** One-time provisioning for a local XAMPP installation. */
final class LocalInstaller
{
    private const TABLE_COLUMNS = [
        'users' => ['id', 'full_name', 'email', 'password_hash', 'created_at', 'updated_at'],
        'decks' => ['id', 'user_id', 'title', 'description', 'subject', 'color', 'created_at', 'updated_at'],
        'flashcards' => ['id', 'deck_id', 'question', 'answer', 'explanation', 'created_at', 'updated_at'],
        'card_progress' => ['id', 'user_id', 'flashcard_id', 'review_state', 'due_at', 'interval_days', 'ease_factor', 'repetition_count', 'total_reviews', 'last_rating', 'last_reviewed_at'],
        'review_history' => ['id', 'user_id', 'flashcard_id', 'rating', 'interval_before', 'interval_after', 'reviewed_at', 'is_practice', 'submission_key'],
        'login_attempts' => ['id', 'identifier', 'attempted_at'],
    ];

    private const FOREIGN_KEYS = [
        'decks' => ['user_id' => 'users'],
        'flashcards' => ['deck_id' => 'decks'],
        'card_progress' => ['user_id' => 'users', 'flashcard_id' => 'flashcards'],
        'review_history' => ['user_id' => 'users', 'flashcard_id' => 'flashcards'],
    ];

    private static function configPath(): string
    {
        return dirname(__DIR__) . '/config.local.php';
    }

    public static function needsSetup(): bool
    {
        $path = self::configPath();
        return !file_exists($path) && !is_link($path);
    }

    public static function isLocalRequest(): bool
    {
        $address = $_SERVER['REMOTE_ADDR'] ?? null;
        return is_string($address) && in_array($address, ['127.0.0.1', '::1'], true);
    }

    public static function isAvailable(): bool
    {
        return self::needsSetup() && self::isLocalRequest();
    }

    /**
     * The front controller validates the POST method and CSRF token first.
     * All exceptions from this method contain display-safe messages only.
     */
    public static function install(array $input): void
    {
        if (!self::isLocalRequest()) {
            throw new RuntimeException('Local setup is available only from this computer.', 403);
        }
        if (!self::needsSetup()) {
            throw new RuntimeException('Setup is already closed. Existing private settings are never overwritten.', 409);
        }
        if (PHP_VERSION_ID < 80200) {
            throw new RuntimeException('Use XAMPP with PHP 8.2 or newer to run BrainLoop.', 503);
        }
        if (!extension_loaded('pdo_mysql')) {
            throw new RuntimeException('Enable the pdo_mysql extension in XAMPP PHP, then restart Apache and try again.', 503);
        }
        $settings = self::validate($input);
        $project = dirname(__DIR__, 2);
        if (!is_file($project . '/database/schema.mysql.sql')) {
            throw new RuntimeException('The database schema file is missing. Restore the complete BrainLoop folder before setup.', 503);
        }
        if (!is_writable(dirname(self::configPath()))) {
            throw new RuntimeException('Allow Apache to write to the BrainLoop app folder for this one-time setup, then try again.', 503);
        }
        $storage = $project . '/storage';
        if (!is_dir($storage) && !@mkdir($storage, 0700, true) && !is_dir($storage)) {
            throw new RuntimeException('Allow Apache to create the private BrainLoop storage folder, then try again.', 503);
        }
        $lock = @fopen($storage . '/install.lock', 'c');
        if ($lock === false) {
            throw new RuntimeException('Allow Apache to write to the private BrainLoop storage folder, then try again.', 503);
        }
        @chmod($storage . '/install.lock', 0600);
        $locked = false;
        $admin = null;
        $createdAccount = null;
        $createdConfig = false;
        try {
            if (!flock($lock, LOCK_EX | LOCK_NB)) {
                throw new RuntimeException('Another setup is running. Wait a moment and reload this page.', 409);
            }
            $locked = true;
            clearstatcache(true, self::configPath());
            if (!self::needsSetup()) {
                throw new RuntimeException('Setup has already finished in another request. Reload BrainLoop to continue.', 409);
            }
            try {
                $admin = Database::mysqlConnection(false, $settings);
            } catch (Throwable) {
                throw new RuntimeException('Could not connect to local MySQL. Start MySQL in XAMPP and check the port, administrator name, and password.', 422);
            }
            self::assertServerVersion($admin);
            self::assertCompatibleDatabase($admin, $settings['name']);
            $databaseName = $settings['name'];
            try {
                $admin->exec('CREATE DATABASE IF NOT EXISTS `' . $databaseName . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
                $admin->exec('USE `' . $databaseName . '`');
                Database::migrate($admin);
            } catch (Throwable) {
                throw new RuntimeException('Could not initialize the database. Use an administrator with CREATE permissions and a supported MySQL/MariaDB version. Existing data has been kept.', 422);
            }
            // MySQL on Windows may normalize the database name to lowercase.
            $databaseName = Database::databaseName((string) $admin->query('SELECT DATABASE()')->fetchColumn());
            $appSettings = array_replace($settings, [
                'driver' => 'mysql',
                'name' => $databaseName,
                'user' => 'brainloop_' . bin2hex(random_bytes(6)),
                'password' => bin2hex(random_bytes(24)),
            ]);
            $account = $admin->quote($appSettings['user']) . '@' . $admin->quote($appSettings['host']);
            try {
                $admin->exec('CREATE USER ' . $account . ' IDENTIFIED BY ' . $admin->quote($appSettings['password']));
                $createdAccount = $account;
                // In GRANT, an underscore in a database name is a wildcard unless escaped.
                $grantName = str_replace('_', '\\_', $databaseName);
                $admin->exec('GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, INDEX ON `' . $grantName . '`.* TO ' . $account);
                $application = Database::mysqlConnection(true, $appSettings);
                $application->query('SELECT COUNT(*) FROM users')->fetchColumn();
                $application = null;
            } catch (Throwable) {
                throw new RuntimeException('Could not create and verify a dedicated database account. Use a local MySQL administrator with CREATE USER and GRANT permissions. Existing data has been kept.', 422);
            }
            $contents = "<?php\ndeclare(strict_types=1);\n\n// Private local credentials. Excluded from Git.\nreturn "
                . var_export(['env' => 'development', 'https' => false, 'database' => $appSettings], true) . ";\n";
            $file = @fopen(self::configPath(), 'x');
            if ($file === false) {
                throw new RuntimeException('Could not create private settings. Existing files are never overwritten. Check folder permissions and reload BrainLoop.', 409);
            }
            $createdConfig = true;
            @chmod(self::configPath(), 0600);
            try {
                $offset = 0;
                while ($offset < strlen($contents)) {
                    $written = fwrite($file, substr($contents, $offset));
                    if ($written === false || $written === 0) {
                        throw new RuntimeException('Could not save the complete private settings file. Check available disk space and folder permissions, then try again.', 503);
                    }
                    $offset += $written;
                }
                if (!fflush($file)) {
                    throw new RuntimeException('Could not save the private settings file. Check available disk space and try again.', 503);
                }
            } finally {
                fclose($file);
            }
            // Only the dedicated application's credentials are saved; never the administrator's.
            $createdConfig = false;
            $createdAccount = null;
        } catch (Throwable $error) {
            if ($createdConfig) {
                @unlink(self::configPath());
            }
            if ($createdAccount !== null && $admin instanceof PDO) {
                // Remove only the random account created by this attempt, not tables or user data.
                try { $admin->exec('DROP USER ' . $createdAccount); } catch (Throwable) { /* An administrator can remove the unused random account. */ }
            }
            if ($error instanceof RuntimeException && !($error instanceof PDOException)) {
                throw $error;
            }
            throw new RuntimeException('Local setup could not finish. Check XAMPP MySQL and folder permissions, then try again. Existing data has been kept.', 503);
        } finally {
            if ($locked) { flock($lock, LOCK_UN); }
            fclose($lock);
        }
    }

    private static function validate(array $input): array
    {
        foreach (['host', 'port', 'name', 'admin_user', 'admin_password'] as $field) {
            if (isset($input[$field]) && !is_string($input[$field]) && !($field === 'port' && is_int($input[$field]))) {
                throw new RuntimeException('Use ordinary text values in the setup fields.', 422);
            }
        }
        $host = strtolower(trim((string) ($input['host'] ?? '127.0.0.1')));
        if (!in_array($host, ['127.0.0.1', 'localhost'], true)) {
            throw new RuntimeException('For local XAMPP setup, the database host must be 127.0.0.1 or localhost.', 422);
        }
        $port = trim((string) ($input['port'] ?? '3306'));
        if (!preg_match('/^[1-9][0-9]{0,4}$/D', $port) || (int) $port > 65535) {
            throw new RuntimeException('Enter a MySQL port from 1 to 65535. XAMPP normally uses 3306.', 422);
        }
        $name = trim((string) ($input['name'] ?? 'brainloop'));
        try { Database::databaseName($name); }
        catch (RuntimeException) { throw new RuntimeException('Use only letters, numbers, and underscores in the database name, up to 64 characters.', 422); }
        $username = trim((string) ($input['admin_user'] ?? 'root'));
        $password = (string) ($input['admin_password'] ?? '');
        if (!valid_text($username, 1, 80) || str_contains($password, "\0") || strlen($password) > 4096) {
            throw new RuntimeException('Enter a valid local MySQL administrator name and password.', 422);
        }
        return ['driver' => 'mysql', 'host' => $host, 'port' => (int) $port, 'name' => $name, 'user' => $username, 'password' => $password];
    }

    private static function assertServerVersion(PDO $pdo): void
    {
        $version = (string) $pdo->query('SELECT VERSION()')->fetchColumn();
        $maria = stripos($version, 'MariaDB') !== false;
        $pattern = $maria ? '/(\d+\.\d+\.\d+)-MariaDB/i' : '/^(\d+\.\d+\.\d+)/';
        if (!preg_match($pattern, $version, $match) || version_compare($match[1], $maria ? '10.4.0' : '8.0.16', '<')) {
            throw new RuntimeException('Use XAMPP with MariaDB 10.4 or newer, or MySQL 8.0.16 or newer.', 422);
        }
    }

    /** Refuse unrelated or incompatible tables before executing any CREATE statements. */
    private static function assertCompatibleDatabase(PDO $pdo, string $name): void
    {
        $tables = $pdo->prepare('SELECT TABLE_NAME, TABLE_TYPE, ENGINE, TABLE_COLLATION FROM information_schema.TABLES WHERE TABLE_SCHEMA = ?');
        $tables->execute([$name]);
        foreach ($tables->fetchAll() as $table) {
            $tableName = (string) $table['TABLE_NAME'];
            if (!isset(self::TABLE_COLUMNS[$tableName]) || $table['TABLE_TYPE'] !== 'BASE TABLE'
                || strcasecmp((string) $table['ENGINE'], 'InnoDB') !== 0 || !str_starts_with((string) $table['TABLE_COLLATION'], 'utf8mb4_')) {
                self::incompatibleDatabase();
            }
            $columns = $pdo->prepare('SELECT COLUMN_NAME, DATA_TYPE, COLUMN_TYPE, COLUMN_KEY, EXTRA, CHARACTER_MAXIMUM_LENGTH FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?');
            $columns->execute([$name, $tableName]);
            $found = [];
            foreach ($columns->fetchAll() as $column) {
                $columnName = (string) $column['COLUMN_NAME'];
                $found[] = $columnName;
                if (!self::compatibleColumn($columnName, $column)) { self::incompatibleDatabase(); }
            }
            $expected = self::TABLE_COLUMNS[$tableName];
            sort($found); sort($expected);
            if ($found !== $expected) { self::incompatibleDatabase(); }
            $indexes = $pdo->prepare('SELECT INDEX_NAME, COLUMN_NAME FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND NON_UNIQUE = 0 ORDER BY INDEX_NAME, SEQ_IN_INDEX');
            $indexes->execute([$name, $tableName]);
            $unique = [];
            foreach ($indexes->fetchAll() as $index) { $unique[$index['INDEX_NAME']][] = $index['COLUMN_NAME']; }
            $required = match ($tableName) { 'users' => 'email', 'card_progress' => 'flashcard_id', 'review_history' => 'submission_key', default => null };
            if ($required !== null && !in_array([$required], $unique, true)) { self::incompatibleDatabase(); }
            $foreign = $pdo->prepare('SELECT k.COLUMN_NAME,k.REFERENCED_TABLE_NAME,k.REFERENCED_COLUMN_NAME,r.DELETE_RULE FROM information_schema.KEY_COLUMN_USAGE k JOIN information_schema.REFERENTIAL_CONSTRAINTS r ON k.CONSTRAINT_SCHEMA=r.CONSTRAINT_SCHEMA AND k.TABLE_NAME=r.TABLE_NAME AND k.CONSTRAINT_NAME=r.CONSTRAINT_NAME WHERE k.TABLE_SCHEMA=? AND k.TABLE_NAME=? AND k.REFERENCED_TABLE_NAME IS NOT NULL');
            $foreign->execute([$name, $tableName]);
            $references = [];
            foreach ($foreign->fetchAll() as $key) {
                if ($key['REFERENCED_COLUMN_NAME'] === 'id' && $key['DELETE_RULE'] === 'CASCADE') { $references[$key['COLUMN_NAME']] = $key['REFERENCED_TABLE_NAME']; }
            }
            foreach (self::FOREIGN_KEYS[$tableName] ?? [] as $column => $target) {
                if (($references[$column] ?? null) !== $target) { self::incompatibleDatabase(); }
            }
        }
    }

    private static function compatibleColumn(string $name, array $column): bool
    {
        $type = strtolower((string) $column['DATA_TYPE']);
        if ($name === 'id') {
            return $type === 'bigint' && str_contains((string) $column['COLUMN_TYPE'], 'unsigned')
                && $column['COLUMN_KEY'] === 'PRI' && str_contains((string) $column['EXTRA'], 'auto_increment');
        }
        if (in_array($name, ['user_id', 'deck_id', 'flashcard_id', 'total_reviews', 'attempted_at'], true)) {
            return $type === 'bigint' && str_contains((string) $column['COLUMN_TYPE'], 'unsigned');
        }
        if (in_array($name, ['created_at', 'updated_at', 'due_at', 'last_reviewed_at', 'reviewed_at'], true)) { return $type === 'datetime'; }
        if (in_array($name, ['description', 'question', 'answer', 'explanation'], true)) { return in_array($type, ['text', 'mediumtext', 'longtext'], true); }
        if (in_array($name, ['interval_days', 'interval_before', 'interval_after'], true)) { return in_array($type, ['double', 'float', 'decimal'], true); }
        if ($name === 'ease_factor') { return in_array($type, ['decimal', 'double', 'float'], true); }
        if ($name === 'repetition_count') { return $type === 'int' && str_contains((string) $column['COLUMN_TYPE'], 'unsigned'); }
        if ($name === 'is_practice') { return $type === 'tinyint'; }
        $minimum = ['full_name' => 80, 'email' => 254, 'password_hash' => 255, 'title' => 120, 'subject' => 80, 'color' => 7, 'review_state' => 16, 'last_rating' => 8, 'rating' => 8, 'submission_key' => 64, 'identifier' => 64];
        return isset($minimum[$name]) && in_array($type, ['char', 'varchar'], true) && (int) $column['CHARACTER_MAXIMUM_LENGTH'] >= $minimum[$name];
    }

    private static function incompatibleDatabase(): never
    {
        throw new RuntimeException('This database contains unrelated tables or an incompatible BrainLoop schema. Choose a new database name. Existing tables have not been changed.', 422);
    }
}
