<?php
declare(strict_types=1);

date_default_timezone_set('UTC');
$GLOBALS['brainloop_config'] = require __DIR__ . '/config.php';
spl_autoload_register(static function (string $class): void {
    if (!str_starts_with($class, 'App\\')) { return; }
    $parts = explode('\\', substr($class, 4));
    $filename = array_pop($parts);
    $path = __DIR__ . '/' . strtolower(implode('/', $parts)) . '/' . $filename . '.php';
    if (is_file($path)) { require $path; }
});
require_once __DIR__ . '/core/urls.php';
require __DIR__ . '/core/helpers.php';
$iconFile = __DIR__ . '/views/partials/icons.php';
if (is_file($iconFile)) { require $iconFile; }
ini_set('display_errors', '0');
ini_set('log_errors', '1');

if (!defined('BRAINLOOP_CLI')) {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_name('brainloop_session');
    session_set_cookie_params(['lifetime' => 0, 'path' => base_path() . '/', 'secure' => config()['https'], 'httponly' => true, 'samesite' => 'Lax']);
    session_start();
    header('Content-Type: text/html; charset=UTF-8');
    header("Content-Security-Policy: default-src 'self'; script-src 'none'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; font-src 'self'; connect-src 'none'; object-src 'none'; base-uri 'none'; form-action 'self'; frame-ancestors 'none'");
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    header('Cache-Control: no-store, private');
    if (config()['https']) { header('Strict-Transport-Security: max-age=31536000'); }
}
