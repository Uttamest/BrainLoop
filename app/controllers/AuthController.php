<?php
declare(strict_types=1);
namespace App\Controllers;

use PDOException;

final class AuthController
{
    public static function register(): void
    {
        if (current_user()) { redirect('/dashboard'); }
        $errors = []; $old = [];
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $old = ['full_name'=>input_text('full_name'),'email'=>strtolower(input_text('email'))];
            $password = input_password('password');
            if (!valid_text($old['full_name'],1,80)) { $errors['full_name'] = 'Enter your name using 1–80 characters.'; }
            if (!filter_var($old['email'],FILTER_VALIDATE_EMAIL) || strlen($old['email'])>254) { $errors['email'] = 'Enter a valid email address.'; }
            elseif (store()->userByEmail($old['email'])) { $errors['email'] = 'An account already uses this email. Please log in.'; }
            $errors += self::passwordErrors($password,input_password('password_confirmation'));
            if (!$errors) {
                try { $id = store()->createUser($old['full_name'],$old['email'],$password); }
                catch (PDOException $e) { if ((string) $e->getCode() !== '23000') { throw $e; } $errors['email'] = 'An account already uses this email.'; }
                if (isset($id)) { self::signIn($id); flash('Your next chapter starts here. Create your first deck!'); redirect('/dashboard'); }
            }
        }
        render('register',compact('errors','old')+['title'=>'Create your account']);
    }
    public static function login(): void
    {
        if (current_user()) { redirect('/dashboard'); }
        $errors = []; $old = [];
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $old = ['email'=>strtolower(input_text('email'))];
            $password = input_password('password'); $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
            if (store()->throttled($old['email'],$ip)) { $errors['email'] = 'Too many login attempts. Try again in 15 minutes.'; http_response_code(429); }
            else {
                $user = strlen($old['email'])<=254 ? store()->userByEmail($old['email']) : null;
                $valid = password_verify($password, $user['password_hash'] ?? '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.');
                if (!$user || !$valid || strlen($password)>72) { store()->loginFailure($old['email'],$ip); $errors['email'] = 'Email or password is incorrect.'; }
                else { store()->clearLoginFailures($old['email']); self::signIn((int) $user['id']); flash('Welcome back. Let’s make a little progress.'); redirect('/dashboard'); }
            }
        }
        render('login',compact('errors','old')+['title'=>'Welcome back'],isset($ip) && str_contains($errors['email'] ?? '', 'Too many') ? 429 : 200);
    }
    private static function signIn(int $id): void { session_regenerate_id(true); $_SESSION = ['user_id'=>$id,'csrf_token'=>bin2hex(random_bytes(32))]; }
    public static function logout(): never
    {
        $_SESSION = [];
        $params = session_get_cookie_params();
        setcookie(session_name(),'', ['expires'=>time()-42000,'path'=>$params['path'],'secure'=>$params['secure'],'httponly'=>true,'samesite'=>'Lax']);
        session_destroy(); redirect('/login');
    }
    private static function passwordErrors(string $password, string $confirm): array
    {
        $errors = [];
        if (!valid_text($password,10,72) || strlen($password)>72) { $errors['password'] = 'Use at least 10 characters and at most 72 bytes.'; }
        if ($password !== $confirm) { $errors['password_confirmation'] = 'Passwords must match.'; }
        return $errors;
    }
    public static function settings(): void
    {
        $user = require_user(); $errors = []; $old = [];
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $action = input_text('action');
            if ($action === 'profile') {
                $old = ['full_name'=>input_text('full_name'),'email'=>strtolower(input_text('email'))];
                if (!valid_text($old['full_name'],1,80)) { $errors['full_name'] = 'Use 1–80 characters for your name.'; }
                if (!filter_var($old['email'],FILTER_VALIDATE_EMAIL) || strlen($old['email'])>254) { $errors['email'] = 'Enter a valid email address.'; }
                $other = store()->userByEmail($old['email']);
                if ($other && (int) $other['id'] !== (int) $user['id']) { $errors['email'] = 'This email is already registered.'; }
                if (strcasecmp($old['email'],$user['email']) !== 0 && !password_verify(input_password('current_password'),$user['password_hash'])) { $errors['current_password'] = 'Enter your current password to change your email.'; }
                if (!$errors) {
                    try { store()->updateProfile((int) $user['id'],$old['full_name'],$old['email']); flash('Your profile is up to date.'); redirect('/settings'); }
                    catch (PDOException $e) { if ((string) $e->getCode() !== '23000') { throw $e; } $errors['email'] = 'This email is already registered.'; }
                }
            } elseif ($action === 'password') {
                if (!password_verify(input_password('current_password'),$user['password_hash'])) { $errors['current_password'] = 'Your current password is incorrect.'; }
                $errors += self::passwordErrors(input_password('password'),input_password('password_confirmation'));
                if (!$errors) { store()->updatePassword((int) $user['id'],input_password('password')); session_regenerate_id(true); unset($_SESSION['study']); $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); flash('Your password has been changed.'); redirect('/settings'); }
            } else { abort_request(422,'Choose a valid account action.'); }
        }
        render('settings',compact('user','errors','old')+['title'=>'Account settings']);
    }
}
