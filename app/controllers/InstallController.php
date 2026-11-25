<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\LocalInstaller;
use RuntimeException;

final class InstallController
{
    /**
     * Supported HTTP error status codes.
     */
    private const ERROR_STATUS_CODES = [403, 409, 422, 503];

    /**
     * Display and process the BrainLoop installation form.
     */
    public static function show(): void
    {
        // Restrict first-time installation to local requests.
        if (!LocalInstaller::isLocalRequest()) {
            abort_request(
                403,
                'For security reasons, first-time setup is only available from this computer.'
            );
            return;
        }

        $errors = [];
        $old = [];
        $status = 200;

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            $old = self::getFormData();

            try {
                LocalInstaller::install(
                    $old + [
                        'admin_password' => input_password('admin_password'),
                    ]
                );

                flash(
                    'BrainLoop has been successfully configured. Create your learning account to get started.'
                );

                redirect('/register');
                return;

            } catch (RuntimeException $exception) {
                $errors['_form'] = $exception->getMessage();

                $status = self::resolveErrorStatus($exception);
            }
        }

        render(
            'install',
            [
                'title'  => 'BrainLoop | Initial Setup',
                'user'   => null,
                'errors' => $errors,
                'old'    => $old,
            ],
            $status
        );
    }

    /**
     * Collect installation form values.
     *
     * Passwords are intentionally excluded from retained form data.
     *
     * @return array<string, string>
     */
    private static function getFormData(): array
    {
        $data = [];

        foreach (['host', 'port', 'name', 'admin_user'] as $field) {
            $data[$field] = input_text($field);
        }

        return $data;
    }

    /**
     * Resolve the appropriate HTTP response status.
     */
    private static function resolveErrorStatus(
        RuntimeException $exception
    ): int {
        $code = $exception->getCode();

        return in_array($code, self::ERROR_STATUS_CODES, true)
            ? $code
            : 422;
    }
}
