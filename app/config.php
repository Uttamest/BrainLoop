<?php
declare(strict_types=1);

$localFile = __DIR__ . '/config.local.php';
$local = is_file($localFile) ? require $localFile : [];
if (!is_array($local)) { throw new RuntimeException('Local configuration must return an array.'); }
$setting = static function (string $variable, mixed $fallback): mixed {
    $value = getenv($variable);
    return $value !== false ? $value : $fallback;
};
$localDatabase = $local['database'] ?? [];
$https = $setting('BRAINLOOP_HTTPS', $local['https'] ?? false);

return [
    'name' => 'BrainLoop',
    'env' => $setting('BRAINLOOP_ENV', $local['env'] ?? 'production'),
    'database' => [
        'driver' => $setting('BRAINLOOP_DB_DRIVER', $localDatabase['driver'] ?? 'mysql'),
        'host' => $setting('BRAINLOOP_DB_HOST', $localDatabase['host'] ?? '127.0.0.1'),
        'port' => (int) $setting('BRAINLOOP_DB_PORT', $localDatabase['port'] ?? 3306),
        'name' => $setting('BRAINLOOP_DB_NAME', $localDatabase['name'] ?? 'brainloop'),
        'user' => $setting('BRAINLOOP_DB_USER', $localDatabase['user'] ?? 'brainloop'),
        'password' => $setting('BRAINLOOP_DB_PASSWORD', $localDatabase['password'] ?? ''),
    ],
    'db_path' => $setting('BRAINLOOP_DB_PATH', $local['db_path'] ?? dirname(__DIR__) . '/storage/brainloop.sqlite'),
    'https' => $https === '1' || $https === true || (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
    'timezone' => 'UTC',
    'colors' => ['#6C5CE7' => 'Purple', '#24B8A6' => 'Teal', '#D98C39' => 'Amber', '#D76880' => 'Rose', '#5685CF' => 'Blue'],
];
