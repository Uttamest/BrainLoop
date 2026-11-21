<?php
declare(strict_types=1);

function detect_base_path(string $scriptName): string
{
    $scriptName = str_replace('\\', '/', $scriptName);
    if (!str_ends_with($scriptName, '/index.php') || preg_match('/[\x00-\x1f?#]/', $scriptName)) { return ''; }
    $directory = substr($scriptName, 0, -strlen('/index.php'));
    if ($directory === '' || $directory === '/') { return ''; }
    $parts = explode('/', trim($directory, '/'));
    foreach ($parts as &$part) {
        $part = rawurldecode($part);
        if ($part === '.' || $part === '..' || preg_match('/[\x00-\x1f\/\\\\]/', $part)) { return ''; }
        $part = rawurlencode($part);
    }
    unset($part);
    return '/' . implode('/', $parts);
}

function base_path(): string
{
    if (defined('BRAINLOOP_BASE_PATH')) { return BRAINLOOP_BASE_PATH; }
    if (PHP_SAPI === 'cli-server') { return ''; }
    return detect_base_path((string) ($_SERVER['SCRIPT_NAME'] ?? ''));
}

/** Build a local application URL, including the folder it was copied into. */
function url(string $route = '/'): string
{
    if (str_starts_with($route, '#')) { return $route; }
    if (!str_starts_with($route, '/') || str_starts_with($route, '//') || preg_match('/[\x00-\x20\\\\]/', $route)) {
        throw new InvalidArgumentException('Application URLs must be local absolute paths.');
    }
    return base_path() . $route;
}

/** Refresh local assets after a copied-folder update without a browser cache reset. */
function asset_url(string $path): string
{
    if (!str_starts_with($path, '/assets/') || str_contains($path, '..')) { throw new InvalidArgumentException('Expected a local asset path.'); }
    $file = dirname(__DIR__, 2) . '/public' . $path;
    return url($path . (is_file($file) ? '?v=' . filemtime($file) : ''));
}

/** Return a logical route without the installation folder or query string. */
function request_path(): string
{
    $path = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
    if (!is_string($path) || !str_starts_with($path, '/')) { return '/__invalid_request__'; }
    $base = base_path();
    if ($base !== '') {
        if ($path === $base) { $path = '/'; }
        elseif (str_starts_with($path, $base . '/')) { $path = substr($path, strlen($base)); }
        else { return '/__outside_installation__'; }
    }
    return $path === '/index.php' ? '/' : $path;
}

function needs_installation(): bool
{
    return config()['database']['driver'] === 'mysql'
        && config()['env'] !== 'test'
        && getenv('BRAINLOOP_DB_USER') === false
        && App\Services\LocalInstaller::needsSetup();
}
