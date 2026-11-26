<?php
declare(strict_types=1);

// Exercise the real controller and views without bootstrapping application storage.
namespace BrainLoop\Tests {
    final class AbortSignal extends \Exception {}
    final class RedirectSignal extends \Exception {}

    final class InstallationHarness
    {
        public static bool $local = true;
        public static ?\Throwable $failure = null;
        public static array $installations = [];
        public static array $renders = [];
        public static array $flashes = [];

        public static function reset(): void
        {
            self::$local = true;
            self::$failure = null;
            self::$installations = self::$renders = self::$flashes = [];
            $_GET = $_POST = $_SESSION = [];
            $_SERVER['REQUEST_METHOD'] = 'GET';
            $_SERVER['REQUEST_URI'] = '/setup';
            $GLOBALS['form_old'] = $GLOBALS['form_errors'] = [];
        }
    }
}

namespace App\Services {
    use BrainLoop\Tests\InstallationHarness;

    // Provisioning is the only service double. It never loads configuration or opens a DB.
    final class LocalInstaller
    {
        public static function isLocalRequest(): bool { return InstallationHarness::$local; }

        public static function install(array $input): void
        {
            InstallationHarness::$installations[] = $input;
            if (InstallationHarness::$failure !== null) {
                throw InstallationHarness::$failure;
            }
        }
    }
}

namespace App\Controllers {
    use BrainLoop\Tests\{AbortSignal, InstallationHarness, RedirectSignal};

    function abort_request(int $status, string $message): never
    {
        throw new AbortSignal($message, $status);
    }

    function redirect(string $path): never
    {
        // A real redirect exits. Its test signal must bypass the installer exception handler.
        throw new RedirectSignal($path);
    }

    function flash(string $message): void
    {
        InstallationHarness::$flashes[] = $message;
    }

    function render(string $page, array $data, int $status): void
    {
        InstallationHarness::$renders[] = compact('page', 'data', 'status');
    }
}

namespace {
    use App\Controllers\InstallController;
    use BrainLoop\Tests\{AbortSignal, InstallationHarness, RedirectSignal};

    if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
    require __DIR__ . '/../app/core/helpers.php';
    require __DIR__ . '/../app/core/urls.php';
    require __DIR__ . '/../app/views/partials/icons.php';
    require __DIR__ . '/../app/controllers/InstallController.php';

    set_error_handler(static function (int $severity, string $message, string $file, int $line): never {
        throw new ErrorException($message, 0, $severity, $file, $line);
    });

    function same(mixed $expected, mixed $actual, string $message = ''): void
    {
        if ($expected !== $actual) {
            throw new RuntimeException($message . PHP_EOL . 'Expected ' . var_export($expected, true)
                . ', received ' . var_export($actual, true));
        }
    }

    function response(): array
    {
        same(1, count(InstallationHarness::$renders), 'One response must be rendered');
        same('install', InstallationHarness::$renders[0]['page']);
        return InstallationHarness::$renders[0];
    }

    $passed = $failed = 0;
    $report = [];
    function check(string $name, Closure $action): void
    {
        global $passed, $failed, $report;
        InstallationHarness::reset();
        try {
            $action();
            ++$passed;
            $report[] = 'PASS ' . $name;
        } catch (Throwable $error) {
            ++$failed;
            $report[] = 'FAIL ' . $name . ': ' . $error->getMessage();
        }
    }

    check('local GET displays a fresh form without provisioning', static function (): void {
        InstallController::show();
        $response = response();
        same(200, $response['status']);
        same(null, $response['data']['user']);
        same([], $response['data']['old']);
        same([], $response['data']['errors']);
        same([], InstallationHarness::$installations);
        same([], InstallationHarness::$flashes);
    });

    check('an absent request method displays the form without warnings', static function (): void {
        unset($_SERVER['REQUEST_METHOD']);
        InstallController::show();
        same(200, response()['status']);
        same([], InstallationHarness::$installations);
    });

    check('nonlocal GET and POST stop before provisioning or rendering', static function (): void {
        foreach (['GET', 'POST'] as $method) {
            InstallationHarness::reset();
            InstallationHarness::$local = false;
            $_SERVER['REQUEST_METHOD'] = $method;
            try {
                InstallController::show();
                throw new RuntimeException('Expected a local-only rejection');
            } catch (AbortSignal $signal) {
                same(403, $signal->getCode());
            }
            same([], InstallationHarness::$installations);
            same([], InstallationHarness::$renders);
            same([], InstallationHarness::$flashes);
        }
    });

    check('POST retains normalized form text and forwards unchanged password bytes', static function (): void {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = [
            'host' => '  localhost  ', 'port' => '  3307 ', 'name' => ' practice ',
            'admin_user' => ' learner ', 'admin_password' => ' synthetic password ',
            'extra' => 'unrelated field',
        ];
        $submitted = $_POST;
        InstallationHarness::$failure = new RuntimeException('Try the local settings again.', 422);
        InstallController::show();
        $retained = ['host' => 'localhost', 'port' => '3307', 'name' => 'practice', 'admin_user' => 'learner'];
        same([$retained + ['admin_password' => ' synthetic password ']], InstallationHarness::$installations);
        same($retained, response()['data']['old']);
        same($submitted, $_POST, 'Request input must be preserved');
        same([], InstallationHarness::$flashes);
    });

    check('nonstring form values are normalized through the shared input helpers', static function (): void {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = ['host' => [], 'port' => 3306, 'name' => null, 'admin_user' => false, 'admin_password' => []];
        InstallationHarness::$failure = new RuntimeException('Use ordinary text.', 422);
        InstallController::show();
        $empty = ['host' => '', 'port' => '', 'name' => '', 'admin_user' => ''];
        same([$empty + ['admin_password' => '']], InstallationHarness::$installations);
        same($empty, response()['data']['old']);
    });

    check('missing POST fields reach the installer as empty text', static function (): void {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        InstallationHarness::$failure = new RuntimeException('Complete the form.', 422);
        InstallController::show();
        same([['host' => '', 'port' => '', 'name' => '', 'admin_user' => '', 'admin_password' => '']], InstallationHarness::$installations);
        same(422, response()['status']);
    });

    check('supported installer failures preserve their response status and message', static function (): void {
        foreach ([403, 409, 422, 503] as $status) {
            InstallationHarness::reset();
            $_SERVER['REQUEST_METHOD'] = 'POST';
            InstallationHarness::$failure = new RuntimeException('Synthetic installer failure.', $status);
            InstallController::show();
            same($status, response()['status']);
            same(['_form' => 'Synthetic installer failure.'], response()['data']['errors']);
            same(1, count(InstallationHarness::$installations));
            same([], InstallationHarness::$flashes);
        }
    });

    check('unrecognized installer status codes return a form validation response', static function (): void {
        foreach ([0, -1, 404, 500] as $status) {
            InstallationHarness::reset();
            $_SERVER['REQUEST_METHOD'] = 'POST';
            InstallationHarness::$failure = new RuntimeException('Synthetic fallback.', $status);
            InstallController::show();
            same(422, response()['status']);
            same(['_form' => 'Synthetic fallback.'], response()['data']['errors']);
        }
    });

    check('successful installation redirects once to registration without rendering', static function (): void {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        try {
            InstallController::show();
            throw new RuntimeException('Expected a registration redirect');
        } catch (RedirectSignal $signal) {
            same('/register', $signal->getMessage());
        }
        same(1, count(InstallationHarness::$installations));
        same(1, count(InstallationHarness::$flashes));
        same([], InstallationHarness::$renders);
    });

    check('unexpected service errors propagate without a misleading form response', static function (): void {
        foreach ([new LogicException('Synthetic internal failure.'), new TypeError('Synthetic type failure.')] as $failure) {
            InstallationHarness::reset();
            $_SERVER['REQUEST_METHOD'] = 'POST';
            InstallationHarness::$failure = $failure;
            try {
                InstallController::show();
                throw new RuntimeException('Expected the original error');
            } catch (LogicException | TypeError $received) {
                same($failure, $received);
            }
            same([], InstallationHarness::$renders);
            same([], InstallationHarness::$flashes);
        }
    });

    check('the full setup page shows one escaped alert and an empty password field', static function (): void {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = ['host' => 'localhost', 'port' => ' 3307 ', 'name' => ' practice ', 'admin_user' => ' learner ', 'admin_password' => 'synthetic-only'];
        InstallationHarness::$failure = new RuntimeException('Check <local> & retry.', 422);
        InstallController::show();
        $response = response();
        ob_start();
        try {
            \render($response['page'], $response['data'], $response['status']);
            $html = ob_get_contents();
        } finally {
            ob_end_clean();
        }
        same(422, http_response_code());
        same(1, substr_count($html, 'role="alert"'));
        same(true, str_contains($html, 'Check &lt;local&gt; &amp; retry.'));
        same(true, str_contains($html, 'value="3307"'));
        same(true, str_contains($html, 'value="practice"'));
        same(false, str_contains($html, 'synthetic-only'));
        same(1, preg_match('~<input\b[^>]*\bid="setup-admin-password"[^>]*>~', $html, $password));
        same(0, preg_match('~\bvalue\s*=~i', $password[0]));
        same(0, preg_match('~<script\b~i', $html));
    });

    // Emit the report after rendering so PHP can set response headers in every supported version.
    echo implode(PHP_EOL, $report) . PHP_EOL;
    echo $passed . ' passed, ' . $failed . ' failed.' . PHP_EOL;
    exit($failed === 0 ? 0 : 1);
}
