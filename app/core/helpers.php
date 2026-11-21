<?php
declare(strict_types=1);

function config(): array { return $GLOBALS['brainloop_config']; }
function db(): PDO { return App\Core\Database::connection(); }
function store(): App\Models\Store { static $store; return $store ??= new App\Models\Store(db()); }
function e(mixed $value): string { return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function utc_now(): string { return gmdate('Y-m-d H:i:s'); }
function old(string $key, mixed $default = ''): mixed { return $GLOBALS['form_old'][$key] ?? $default; }
function field_error(string $key): string { $error = $GLOBALS['form_errors'][$key] ?? ''; return $error ? '<span class="field-error" id="' . e($key) . '-error" role="alert">' . e($error) . '</span>' : ''; }
function csrf_token(): string { return $_SESSION['csrf_token'] ??= bin2hex(random_bytes(32)); }
function csrf_field(): string { return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">'; }
function redirect(string $path): never { try { $location = url($path); } catch (InvalidArgumentException) { $location = url('/dashboard'); } header('Location: ' . $location, true, 303); exit; }
function flash(string $message, string $type = 'success'): void { $_SESSION['flashes'][] = ['message' => $message, 'type' => $type]; }
function current_user(): ?array { static $loaded = false, $user; if (!$loaded) { $user = !needs_installation() && isset($_SESSION['user_id']) ? store()->user((int) $_SESSION['user_id']) : null; $loaded = true; } return $user; }
function require_user(): array { $user = current_user(); if (!$user) { flash('Log in to continue your learning.', 'info'); redirect('/login'); } return $user; }
function render(string $page, array $data = [], int $status = 200): void {
    http_response_code($status);
    $GLOBALS['form_old'] = $data['old'] ?? [];
    $GLOBALS['form_errors'] = $data['errors'] ?? [];
    $user = array_key_exists('user', $data) ? $data['user'] : current_user(); $title = $data['title'] ?? 'BrainLoop';
    $path = request_path();
    $errors = $data['errors'] ?? [];
    $flashes = $_SESSION['flashes'] ?? []; unset($_SESSION['flashes']);
    extract($data, EXTR_SKIP);
    ob_start(); require dirname(__DIR__) . '/views/pages/' . $page . '.php'; $content = ob_get_clean();
    require dirname(__DIR__) . '/views/layouts/main.php';
}
function abort_request(int $status = 404, string $message = 'This page could not be found.'): never { render('error', compact('status', 'message') + ['title' => $status . ' · BrainLoop'], $status); exit; }
function format_date(?string $utc, string $format = 'M j, Y'): string { return $utc ? (new DateTimeImmutable($utc, new DateTimeZone('UTC')))->format($format) : 'Not reviewed yet'; }
function excerpt(string $text, int $length = 120): string { $chars = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY); return $chars === false || count($chars) <= $length ? $text : implode('', array_slice($chars, 0, $length)) . '…'; }
function input_text(string $name): string { $value = $_POST[$name] ?? ''; return is_string($value) ? trim($value) : ''; }
function input_password(string $name): string { $value = $_POST[$name] ?? ''; return is_string($value) ? $value : ''; }
function text_length(string $text): int { return preg_match_all('/./us', $text) ?: 0; }
function valid_text(string $text, int $min, int $max): bool { return preg_match('//u', $text) === 1 && !str_contains($text, "\0") && text_length($text) >= $min && text_length($text) <= $max; }
function valid_id(mixed $value): int { return is_scalar($value) && preg_match('/^[1-9][0-9]{0,9}$/D', (string) $value) ? (int) $value : 0; }
function like_term(string $query): string { return '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $query) . '%'; }
