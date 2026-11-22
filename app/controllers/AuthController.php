<?php

declare(strict_types=1);

namespace App\Controllers;

use PDOException;

final class AuthController
{
    private const DUMMY_BCRYPT_HASH = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.';
    private const MAX_BCRYPT_BYTES = 72;
    private const MIN_PASSWORD_CHARS = 10;
    private const MAX_EMAIL_LENGTH = 254;
    private const MAX_NAME_LENGTH = 80;

    public static function register(): void
    {
        if (current_user()) {
            redirect('/dashboard');
        }

        $errors = [];
        $old = [];
        $status = 200;

        if (self::isPost()) {
            $old = [
                'full_name' => input_text('full_name'),
                'email' => strtolower(input_text('email')),
            ];
            $password = input_password('password');
            $confirm = input_password('password_confirmation');

            $errors = self::validateProfileFields($old['full_name'], $old['email']);
            if (!isset($errors['email']) && store()->userByEmail($old['email'])) {
                $errors['email'] = 'An account already uses this email. Please log in.';
            }

            $errors += self::validateNewPassword($password, $confirm);

            if (!$errors) {
                try {
                    $userId = store()->createUser($old['full_name'], $old['email'], $password);
                    self::signIn($userId);
                    flash('Your next chapter starts here. Create your first deck!');
                    redirect('/dashboard');
                } catch (PDOException $e) {
                    if ((string) $e->getCode() !== '23000') {
                        throw $e;
                    }
                    $errors['email'] = 'An account already uses this email.';
                }
            }

            $status = 422;
        }

        render('register', [
            'title' => 'Create your account',
            'errors' => $errors,
            'old' => $old,
        ], $status);
    }

    public static function login(): void
    {
        if (current_user()) {
            redirect('/dashboard');
        }

        $errors = [];
        $old = [];
        $status = 200;

        if (self::isPost()) {
            $old = ['email' => strtolower(input_text('email'))];
            $password = input_password('password');
            $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

            if (store()->throttled($old['email'], $ip)) {
                $errors['email'] = 'Too many login attempts. Try again in 15 minutes.';
                $status = 429;
            } else {
                $user = strlen($old['email']) <= self::MAX_EMAIL_LENGTH
                    ? store()->userByEmail($old['email'])
                    : null;

                $hashToCheck = $user['password_hash'] ?? self::DUMMY_BCRYPT_HASH;
                $passwordWithinLimit = strlen($password) <= self::MAX_BCRYPT_BYTES;
                $isValid = $passwordWithinLimit && password_verify($password, $hashToCheck);

                if (!$user || !$isValid) {
                    store()->loginFailure($old['email'], $ip);
                    $errors['email'] = 'Email or password is incorrect.';
                    $status = 422;
                } else {
                    store()->clearLoginFailures($old['email']);
                    self::signIn((int) $user['id']);
                    flash('Welcome back. Let’s make a little progress.');
                    redirect('/dashboard');
                }
            }
        }

        render('login', [
            'title' => 'Welcome back',
            'errors' => $errors,
            'old' => $old,
        ], $status);
    }

    public static function logout(): never
    {
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires' => time() - 42000,
                'path' => $params['path'],
                'domain' => $params['domain'],
                'secure' => $params['secure'],
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        }

        session_destroy();
        redirect('/login');
    }

    public static function settings(): void
    {
        $user = require_user();
        $errors = [];
        $old = [];
        $status = 200;

        if (self::isPost()) {
            $action = input_text('action');

            if ($action === 'profile') {
                [$errors, $old] = self::handleProfileUpdate($user);
            } elseif ($action === 'password') {
                $errors = self::handlePasswordUpdate($user);
            } else {
                abort_request(422, 'Choose a valid account action.');
            }

            $status = 422;
        }

        render('settings', [
            'title' => 'Account settings',
            'user' => $user,
            'errors' => $errors,
            'old' => $old,
        ], $status);
    }

    /**
     * @param array<string, mixed> $user
     * @return array{0: array<string, string>, 1: array<string, string>}
     */
    private static function handleProfileUpdate(array $user): array
    {
        $old = [
            'full_name' => input_text('full_name'),
            'email' => strtolower(input_text('email')),
        ];

        $errors = self::validateProfileFields($old['full_name'], $old['email']);

        if (!isset($errors['email'])) {
            $existing = store()->userByEmail($old['email']);
            if ($existing && (int) $existing['id'] !== (int) $user['id']) {
                $errors['email'] = 'This email is already registered.';
            }
        }

        $emailChanged = strcasecmp($old['email'], (string) $user['email']) !== 0;
        if ($emailChanged && !self::verifyCurrentPassword(input_password('current_password'), (string) $user['password_hash'])) {
            $errors['current_password'] = 'Enter your current password to change your email.';
        }

        if (!$errors) {
            try {
                store()->updateProfile((int) $user['id'], $old['full_name'], $old['email']);
                flash('Your profile is up to date.');
                redirect('/settings');
            } catch (PDOException $e) {
                if ((string) $e->getCode() !== '23000') {
                    throw $e;
                }
                $errors['email'] = 'This email is already registered.';
            }
        }

        return [$errors, $old];
    }

    /**
     * @param array<string, mixed> $user
     * @return array<string, string>
     */
    private static function handlePasswordUpdate(array $user): array
    {
        $errors = [];

        if (!self::verifyCurrentPassword(input_password('current_password'), (string) $user['password_hash'])) {
            $errors['current_password'] = 'Your current password is incorrect.';
        }

        $newPassword = input_password('password');
        $confirm = input_password('password_confirmation');
        $errors += self::validateNewPassword($newPassword, $confirm);

        if (!$errors) {
            store()->updatePassword((int) $user['id'], $newPassword);
            self::rotateSession((int) $user['id']);
            flash('Your password has been changed.');
            redirect('/settings');
        }

        return $errors;
    }

    /**
     * @return array<string, string>
     */
    private static function validateProfileFields(string $fullName, string $email): array
    {
        $errors = [];

        if (!valid_text($fullName, 1, self::MAX_NAME_LENGTH)) {
            $errors['full_name'] = 'Enter your name using 1–80 characters.';
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > self::MAX_EMAIL_LENGTH) {
            $errors['email'] = 'Enter a valid email address.';
        }

        return $errors;
    }

    /**
     * @return array<string, string>
     */
    private static function validateNewPassword(string $password, string $confirm): array
    {
        $errors = [];

        if (!valid_text($password, self::MIN_PASSWORD_CHARS, self::MAX_BCRYPT_BYTES) || strlen($password) > self::MAX_BCRYPT_BYTES) {
            $errors['password'] = 'Use at least 10 characters and at most 72 bytes.';
        }

        if ($password !== $confirm) {
            $errors['password_confirmation'] = 'Passwords must match.';
        }

        return $errors;
    }

    private static function verifyCurrentPassword(string $candidate, string $storedHash): bool
    {
        return $candidate !== ''
            && strlen($candidate) <= self::MAX_BCRYPT_BYTES
            && password_verify($candidate, $storedHash);
    }

    private static function signIn(int $userId): void
    {
        self::rotateSession($userId);
    }

    private static function rotateSession(int $userId): void
    {
        session_regenerate_id(true);
        $_SESSION = [
            'user_id' => $userId,
            'csrf_token' => bin2hex(random_bytes(32)),
        ];
    }

    private static function isPost(): bool
    {
        return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
    }
}
