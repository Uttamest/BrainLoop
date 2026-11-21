<?php
declare(strict_types=1);
namespace App\Controllers;

use App\Services\LocalInstaller;
use RuntimeException;

final class InstallController
{
    public static function show(): void
    {
        if (!LocalInstaller::isLocalRequest()) { abort_request(403, 'First-time setup is available only from this computer.'); }
        $errors = []; $old = []; $status = 200;
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            foreach (['host','port','name','admin_user'] as $key) { $old[$key] = input_text($key); }
            try {
                LocalInstaller::install($old + ['admin_password' => input_password('admin_password')]);
                flash('BrainLoop is ready. Create your learning account to get started.');
                redirect('/register');
            } catch (RuntimeException $error) {
                $errors['_form'] = $error->getMessage();
                $status = in_array($error->getCode(), [403,409,422,503], true) ? $error->getCode() : 422;
            }
        }
        render('install', compact('errors','old') + ['title' => 'Set up BrainLoop', 'user' => null], $status);
    }
}
