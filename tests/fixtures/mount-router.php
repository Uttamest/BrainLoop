<?php
declare(strict_types=1);

/** Development-server fixture for a project copied to a /BrainLoop/ document-root subdirectory. */
$root = dirname(__DIR__, 2);
$mount = '/BrainLoop';
$path = rawurldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/');
if ($path !== $mount && !str_starts_with($path, $mount . '/')) { http_response_code(404); exit('Not found.'); }
$logical = substr($path, strlen($mount)) ?: '/';
if (preg_match('~^/(?:app|database|docs|scripts|storage|tests|public|\.git)(?:/|$)~i', $logical)) { http_response_code(404); exit('Not found.'); }
$assetPath = $logical;
if (str_starts_with($assetPath, '/assets/')) {
    $assetRoot = realpath($root . '/public/assets');
    $file = realpath($root . '/public' . $assetPath);
    $extension = strtolower(pathinfo($file ?: '', PATHINFO_EXTENSION));
    $types = ['css' => 'text/css; charset=UTF-8', 'svg' => 'image/svg+xml', 'png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'webp' => 'image/webp', 'ico' => 'image/x-icon'];
    if (!$file || !$assetRoot || !str_starts_with($file, $assetRoot . DIRECTORY_SEPARATOR) || !is_file($file) || !isset($types[$extension])) { http_response_code(404); exit('Not found.'); }
    header('Content-Type: ' . $types[$extension]);
    readfile($file);
    exit;
}
$_SERVER['SCRIPT_NAME'] = $mount . '/index.php';
$_SERVER['PHP_SELF'] = $_SERVER['SCRIPT_NAME'];
$_SERVER['SCRIPT_FILENAME'] = $root . '/index.php';
require $root . '/index.php';
