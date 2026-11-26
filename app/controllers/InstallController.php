<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\LocalInstaller;
use RuntimeException;

/**
 * Handles first-run local environment provisioning and database setup.
 */
final class InstallController
{
    private const HTTP_OK                    = 200;
    private const HTTP_FORBIDDEN             = 403;
    private const HTTP_CONFLICT              = 409;
    private const HTTP_UNPROCESSABLE_ENTITY  = 422;
    private const HTTP_SERVICE_UNAVAILABLE   = 503;

    /** @var list<int> */
    private const ALLOWED_ERROR_STATUS_CODES = [
        self::HTTP_FORBIDDEN,
        self::HTTP_CONFLICT,
        self::HTTP_UNPROCESSABLE_ENTITY,
        self::HTTP_SERVICE_UNAVAILABLE,
    ];

    /** @var list<string> */
    private const RETAINED_FORM_FIELDS = [
        'host',
        'port',
        'name',
        'admin_user',
    ];

    /**
     * Render and process the first-time installation wizard.
     */
    public static function show(): void
    {
        self::assertLocalEnvironment();

        $errors = [];
        $old    = [];
        $status = self::HTTP_OK;

        if (self::isPostRequest()) {
            $old = self::extractRetainedInput();

            try {
                LocalInstaller::install(self::buildInstallationPayload($old));

                flash('BrainLoop has been successfully configured. Create your learning account to get started.');
                redirect('/register');
            } catch (RuntimeException $exception) {
                $errors['_form'] = $exception->getMessage();
                $status          = self::resolveHttpErrorStatus($exception);
            }
        }

        render('install', [
            'title'  => 'BrainLoop | Initial Setup',
            'user'   => null,
            'errors' => $errors,
            'old'    => $old,
        ], $status);
    }

    /**
     * Ensure setup requests originate strictly from the local machine.
     */
    private static function assertLocalEnvironment(): void
    {
        if (!LocalInstaller::isLocalRequest()) {
            abort_request(
                self::HTTP_FORBIDDEN,
                'For security reasons, first-time setup is only available from this computer.'
            );
        }
    }

    private static function isPostRequest(): bool
    {
        return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
    }

    /**
     * Collect non-sensitive form fields to re-populate inputs on validation errors.
     *
     * @return array<string, string>
     */
    private static function extractRetainedInput(): array
    {
        $input = [];

        foreach (self::RETAINED_FORM_FIELDS as $field) {
            $input[$field] = input_text($field);
        }

        return $input;
    }

    /**
     * Merge retained text fields with sensitive credentials without persisting passwords in view state.
     *
     * @param array<string, string> $retainedInput
     * @return array<string, string>
     */
    private static function buildInstallationPayload(array $retainedInput): array
    {
        return $retainedInput + [
            'admin_password' => input_password('admin_password'),
        ];
    }

    /**
     * Map installer exception codes to safe, supported HTTP error status codes.
     */
    private static function resolveHttpErrorStatus(RuntimeException $exception): int
    {
        $code = (int) $exception->getCode();

        return in_array($code, self::ALLOWED_ERROR_STATUS_CODES, true)
            ? $code
            : self::HTTP_UNPROCESSABLE_ENTITY;
    }
}
