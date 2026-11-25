<?php
declare(strict_types=1);

/** Each runner owns a randomly named MySQL schema and a scoped temporary account. */
final class DatabaseSandbox
{
    public readonly string $driver;
    public readonly array $environment;
    private ?PDO $administrator = null;
    private ?string $mysqlDatabase = null;
    private ?string $mysqlUser = null;

    public function __construct(array $arguments = [])
    {
        $this->driver = 'mysql';
        register_shutdown_function([$this, 'cleanup']);
        $environment = ['BRAINLOOP_DB_DRIVER' => $this->driver, 'BRAINLOOP_ENV' => 'test', 'BRAINLOOP_HTTPS' => '0'];
        if (!extension_loaded('pdo_mysql')) { throw new RuntimeException('MySQL tests require pdo_mysql.'); }
        $host = getenv('BRAINLOOP_TEST_DB_HOST') ?: '127.0.0.1';
        $port = getenv('BRAINLOOP_TEST_DB_PORT') ?: '3306';
        $user = getenv('BRAINLOOP_TEST_DB_USER') ?: 'root';
        $password = getenv('BRAINLOOP_TEST_DB_PASSWORD');
        $password = $password === false ? '' : $password;
        $this->administrator = new PDO('mysql:host=' . $host . ';port=' . $port . ';charset=utf8mb4', $user, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]);
        $name = 'brainloop_test_' . bin2hex(random_bytes(8));
        $this->administrator->exec('CREATE DATABASE `' . $name . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        $this->mysqlDatabase = $name;
        // A nonempty private password also survives Windows child-process environment handling.
        $testUser = 'bl_test_' . bin2hex(random_bytes(8));
        $testPassword = bin2hex(random_bytes(24));
        $account = $this->administrator->quote($testUser) . "@'%'";
        $this->administrator->exec('CREATE USER ' . $account . ' IDENTIFIED BY ' . $this->administrator->quote($testPassword));
        $this->mysqlUser = $testUser;
        $grantDatabase = str_replace('_', '\\_', $name);
        $this->administrator->exec('GRANT ALL PRIVILEGES ON `' . $grantDatabase . '`.* TO ' . $account);
        $environment += ['BRAINLOOP_DB_HOST' => $host, 'BRAINLOOP_DB_PORT' => $port, 'BRAINLOOP_DB_NAME' => $name, 'BRAINLOOP_DB_USER' => $testUser, 'BRAINLOOP_DB_PASSWORD' => $testPassword];
        $this->environment = $environment;
    }

    public function apply(): void
    {
        foreach ($this->environment as $key => $value) { putenv($key . '=' . $value); }
    }

    public function assertConfiguration(array $configuration): void
    {
        $configuredDriver = $configuration['database']['driver'] ?? '';
        if ($configuredDriver !== $this->driver) { throw new RuntimeException('Refusing to run: application configuration did not select the isolated test backend.'); }
        foreach (['host', 'port', 'name', 'user', 'password'] as $key) {
            if ((string) ($configuration['database'][$key] ?? '') !== (string) $this->environment['BRAINLOOP_DB_' . strtoupper($key)]) { throw new RuntimeException('Refusing to run: application configuration did not select the isolated MySQL test connection.'); }
        }
    }

    public function connection(): PDO
    {
        $e = $this->environment;
        return new PDO('mysql:host=' . $e['BRAINLOOP_DB_HOST'] . ';port=' . $e['BRAINLOOP_DB_PORT'] . ';dbname=' . $e['BRAINLOOP_DB_NAME'] . ';charset=utf8mb4', $e['BRAINLOOP_DB_USER'], $e['BRAINLOOP_DB_PASSWORD'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false]);
    }

    public function cleanup(): void
    {
        // Never drop a configured application database: only this object's generated schema is eligible.
        if ($this->administrator && $this->mysqlDatabase && preg_match('/^brainloop_test_[a-f0-9]{16}$/D', $this->mysqlDatabase)) {
            $this->administrator->exec('DROP DATABASE IF EXISTS `' . $this->mysqlDatabase . '`');
            $this->mysqlDatabase = null;
        }
        if ($this->administrator && $this->mysqlUser && preg_match('/^bl_test_[a-f0-9]{16}$/D', $this->mysqlUser)) {
            $this->administrator->exec('DROP USER IF EXISTS ' . $this->administrator->quote($this->mysqlUser) . "@'%'");
            $this->mysqlUser = null;
        }
    }

    public function description(): string
    {
        return 'an isolated temporary MySQL/MariaDB schema';
    }
}
