<?php
declare(strict_types=1);

// Controller regressions with synthetic dependencies. No bootstrap, database, or HTTP server.
namespace BrainLoop\Tests {
    final class AbortSignal extends \RuntimeException {}
    final class RedirectSignal extends \RuntimeException {}

    final class FakeStore
    {
        public array $calls = [];
        public array $imports = [];
        public array $deck = ['id' => 7, 'user_id' => 17, 'title' => 'Biology', 'subject' => 'Science'];
        public array $card = [
            'id' => 41, 'deck_id' => 7, 'question' => 'What is a cell?',
            'answer' => 'A unit of life.', 'explanation' => 'Practice material.',
            'due_at' => '2000-01-01 00:00:00', 'review_state' => 'new',
        ];

        public function decks(int $userId, string $query = '', string $subject = ''): array
        {
            $this->calls[] = ['decks', $userId, $query, $subject];
            return [$this->deck];
        }

        public function subjects(int $userId): array
        {
            $this->calls[] = ['subjects', $userId];
            return ['Science'];
        }

        public function deck(int $id, int $userId): ?array
        {
            $this->calls[] = ['deck', $id, $userId];
            return $id === 7 && $userId === 17 ? $this->deck : null;
        }

        public function cards(int $deckId, int $userId, string $query = ''): array
        {
            $this->calls[] = ['cards', $deckId, $userId, $query];
            return [$this->card];
        }

        public function searchCards(int $userId, string $query): array
        {
            $this->calls[] = ['searchCards', $userId, $query];
            return $query === '' ? [] : [$this->card];
        }

        public function studyCards(int $userId, ?int $deckId, string $mode): array
        {
            $this->calls[] = ['studyCards', $userId, $deckId, $mode];
            return [41];
        }

        public function card(int $id, int $userId): ?array
        {
            $this->calls[] = ['card', $id, $userId];
            return $id === 41 && $userId === 17 ? $this->card : null;
        }

        public function importCards(int $deckId, int $userId, array $rows): int
        {
            $this->imports[] = ['deck_id' => $deckId, 'user_id' => $userId, 'rows' => $rows];
            return count($rows);
        }
    }

    final class Harness
    {
        public static FakeStore $store;
        public static array $user;
        public static array $renders;
        public static array $flashes;
        public static array $uploads;

        public static function reset(): void
        {
            self::$store = new FakeStore();
            self::$user = ['id' => 17, 'full_name' => 'Practice learner'];
            self::$renders = self::$flashes = self::$uploads = [];
            $_GET = $_POST = $_FILES = $_SESSION = [];
            $_SERVER['REQUEST_METHOD'] = 'GET';
        }
    }
}

namespace App\Controllers {
    use BrainLoop\Tests\{AbortSignal, FakeStore, Harness, RedirectSignal};

    function require_user(): array { return Harness::$user; }
    function store(): FakeStore { return Harness::$store; }
    function config(): array { return ['colors' => ['#6C5CE7' => 'Purple']]; }
    function utc_now(): string { return '2026-11-24 12:00:00'; }
    function render(string $page, array $data = [], int $status = 200): void
    {
        Harness::$renders[] = ['page' => $page, 'data' => $data, 'status' => $status];
    }
    function abort_request(int $status = 404, string $message = ''): never
    {
        throw new AbortSignal($message, $status);
    }
    function redirect(string $path): never { throw new RedirectSignal($path); }
    function flash(string $message, string $type = 'success'): void
    {
        Harness::$flashes[] = ['message' => $message, 'type' => $type];
    }
    function is_uploaded_file(string $filename): bool
    {
        // Only temporary fixtures explicitly registered below count as uploads.
        return isset(Harness::$uploads[$filename]);
    }
}

namespace App\Services {
    // This controller test never provisions a database or writes private settings.
    final class LocalInstaller
    {
        public static function isLocalRequest(): bool { return true; }
        public static function install(array $input): void
        {
            throw new \RuntimeException('Enter a valid MySQL port.', 422);
        }
    }
}

namespace {
    use App\Controllers\{DeckController, InstallController, PageController, StudyController};
    use BrainLoop\Tests\{AbortSignal, Harness, RedirectSignal};

    if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
    require __DIR__ . '/../app/core/helpers.php';
    require __DIR__ . '/../app/controllers/DeckController.php';
    require __DIR__ . '/../app/controllers/PageController.php';
    require __DIR__ . '/../app/controllers/StudyController.php';
    require __DIR__ . '/../app/controllers/InstallController.php';
    require __DIR__ . '/../app/core/urls.php';
    require __DIR__ . '/../app/views/partials/icons.php';

    set_error_handler(static function (int $severity, string $message, string $file, int $line): never {
        throw new ErrorException($message, 0, $severity, $file, $line);
    });

    function same(mixed $expected, mixed $actual, string $message = ''): void
    {
        if ($expected !== $actual) {
            throw new RuntimeException($message . PHP_EOL . 'Expected: ' . var_export($expected, true)
                . PHP_EOL . 'Actual: ' . var_export($actual, true));
        }
    }

    function redirected(string $path, Closure $action): void
    {
        try { $action(); }
        catch (RedirectSignal $signal) { same($path, $signal->getMessage(), 'Redirect destination'); return; }
        throw new RuntimeException('Expected a redirect to ' . $path);
    }

    function aborted(int $status, Closure $action): void
    {
        try { $action(); }
        catch (AbortSignal $signal) { same($status, $signal->getCode(), 'Abort status'); return; }
        throw new RuntimeException('Expected an abort with status ' . $status);
    }

    function rendered(string $page): array
    {
        same(1, count(Harness::$renders), 'One view must be rendered');
        same($page, Harness::$renders[0]['page'], 'Rendered view');
        return Harness::$renders[0]['data'];
    }

    function withUpload(string $csv, Closure $action): void
    {
        $handle = tmpfile();
        if ($handle === false) { throw new RuntimeException('Cannot create synthetic CSV fixture'); }
        $path = stream_get_meta_data($handle)['uri'];
        try {
            fwrite($handle, $csv);
            fflush($handle);
            Harness::$uploads[$path] = true;
            $_SERVER['REQUEST_METHOD'] = 'POST';
            $_FILES['csv'] = ['name' => 'practice.csv', 'tmp_name' => $path, 'error' => UPLOAD_ERR_OK];
            $action();
        } finally {
            unset(Harness::$uploads[$path]);
            fclose($handle);
        }
    }

    $passed = 0;
    $failed = 0;
    function runCase(string $name, Closure $action): void
    {
        global $passed, $failed;
        Harness::reset();
        try {
            $action();
            ++$passed;
            echo 'PASS ' . $name . PHP_EOL;
        } catch (Throwable $error) {
            ++$failed;
            fwrite(STDERR, 'FAIL ' . $name . ': ' . $error->getMessage() . PHP_EOL);
        }
    }

    runCase('public query trims text and accepts the 200-character Unicode boundary', static function (): void {
        foreach (['  cells  ' => 'cells', " \t학습\r\n " => '학습', str_repeat('a', 200) => str_repeat('a', 200),
            str_repeat('학', 200) => str_repeat('학', 200)] as $input => $expected) {
            $_GET = ['q' => $input];
            same($expected, DeckController::query());
        }
        $_GET = [];
        same('', DeckController::query());
        same([], Harness::$store->calls, 'Parsing must not access the store');
    });

    runCase('invalid query shapes, encoding and overlong text return an empty query', static function (): void {
        foreach ([null, 4, false, [], new stdClass(), str_repeat('a', 201), str_repeat('학', 201), "\xC3\x28", "a\0b"] as $input) {
            $_GET = ['q' => $input];
            same('', DeckController::query());
        }
    });

    runCase('Page search passes the normalized query and current owner to the store', static function (): void {
        $_GET = ['q' => '  학습  '];
        PageController::search();
        same([['decks', 17, '학습', ''], ['searchCards', 17, '학습']], Harness::$store->calls);
        $data = rendered('search');
        same('학습', $data['query']);
        same([Harness::$store->deck], $data['deckResults']);
        same([Harness::$store->card], $data['cardResults']);
    });

    runCase('empty Page search does not request all decks', static function (): void {
        $_GET = ['q' => ['unexpected']];
        PageController::search();
        same([['searchCards', 17, '']], Harness::$store->calls);
        $data = rendered('search');
        same([], $data['deckResults']);
        same([], $data['cardResults']);
    });

    runCase('deck listing forwards normalized query and subject filters', static function (): void {
        $_GET = ['q' => '  cells  ', 'subject' => ' Science '];
        (new DeckController())->listing();
        same([['decks', 17, 'cells', 'Science'], ['subjects', 17]], Harness::$store->calls);
        $data = rendered('decks');
        same('cells', $data['query']);
        same('Science', $data['subject']);
        same([Harness::$store->deck], $data['decks']);
    });

    runCase('deck detail checks ownership and forwards its query to card lookup', static function (): void {
        $_GET = ['q' => '  cell  '];
        (new DeckController())->detail(7);
        same([['deck', 7, 17], ['cards', 7, 17, 'cell']], Harness::$store->calls);
        $data = rendered('deck_detail');
        same('cell', $data['query']);
        same(Harness::$store->deck, $data['deck']);
        same([Harness::$store->card], $data['cards']);
    });

    runCase('owned deck study starts due and practice sessions through the current API', static function (): void {
        foreach (['due', 'practice'] as $mode) {
            Harness::reset();
            $_SERVER['REQUEST_METHOD'] = 'POST';
            $_POST = ['deck_id' => '7', 'mode' => $mode];
            $_SESSION['study_result'] = ['previous' => true];
            redirected('/study/session', static fn() => StudyController::start());
            same([['deck', 7, 17], ['studyCards', 17, 7, $mode]], Harness::$store->calls);
            $session = $_SESSION['study'];
            same(17, $session['user_id']);
            same(7, $session['deck_id']);
            same($mode, $session['mode']);
            same([41], $session['queue']);
            same(1, $session['total']);
            same(false, $session['revealed']);
            same(48, strlen($session['nonce']));
            same(false, isset($_SESSION['study_result']));
        }
    });

    runCase('missing and unowned decks abort before fetching study cards or changing session', static function (): void {
        foreach ([[99, 17], [7, 18]] as [$deckId, $userId]) {
            Harness::reset();
            Harness::$user['id'] = $userId;
            $_SERVER['REQUEST_METHOD'] = 'POST';
            $_POST = ['deck_id' => (string) $deckId, 'mode' => 'due'];
            $_SESSION = ['unrelated' => 'keep'];
            aborted(404, static fn() => StudyController::start());
            same([['deck', $deckId, $userId]], Harness::$store->calls);
            same(['unrelated' => 'keep'], $_SESSION);
        }
    });

    runCase('owned deck study renders its card and only reveals the answer when requested', static function (): void {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = ['deck_id' => '7', 'mode' => 'due'];
        redirected('/study/session', static fn() => StudyController::start());
        $_SERVER['REQUEST_METHOD'] = 'GET';
        Harness::$store->calls = [];
        StudyController::show();
        same([['card', 41, 17], ['deck', 7, 17]], Harness::$store->calls);
        $data = rendered('study');
        $hidden = Harness::$store->card;
        unset($hidden['answer'], $hidden['explanation']);
        same($hidden, $data['card']);
        same(Harness::$store->deck, $data['deck']);
        same(false, $data['revealed']);
        same(1, $data['position']);
        Harness::$renders = [];
        $_SESSION['study']['revealed'] = true;
        StudyController::show();
        same(Harness::$store->card, rendered('study')['card']);
    });

    runCase('all-decks sessions start and render without a specific deck lookup', static function (): void {
        foreach (['', '0'] as $deckId) {
            Harness::reset();
            $_SERVER['REQUEST_METHOD'] = 'POST';
            $_POST = ['deck_id' => $deckId, 'mode' => 'practice'];
            redirected('/study/session', static fn() => StudyController::start());
            same([['studyCards', 17, null, 'practice']], Harness::$store->calls);
            same(null, $_SESSION['study']['deck_id']);
            Harness::$store->calls = [];
            $_SERVER['REQUEST_METHOD'] = 'GET';
            StudyController::show();
            same([['card', 41, 17]], Harness::$store->calls);
            same(['title' => 'All decks', 'id' => null], rendered('study')['deck']);
        }
    });

    runCase('CSV import accepts two columns and BOM with quoted multiline three-column data', static function (): void {
        $cases = [
            ["question,answer\r\n  Question  ,  Answer  \r\n\r\n",
                [['question' => 'Question', 'answer' => 'Answer', 'explanation' => '']]],
            ["\xEF\xBB\xBFQuestion,Answer,Explanation\r\n\"What, exactly?\",\"Line one\nLine two\",\"A \"\"quoted\"\" note\"\r\n",
                [['question' => 'What, exactly?', 'answer' => "Line one\nLine two", 'explanation' => 'A "quoted" note']]],
        ];
        foreach ($cases as [$csv, $rows]) {
            Harness::reset();
            withUpload($csv, static function () use ($rows): void {
                redirected('/decks/7', static fn() => (new DeckController())->import(7));
                same([['deck_id' => 7, 'user_id' => 17, 'rows' => $rows]], Harness::$store->imports);
                same('success', Harness::$flashes[0]['type']);
            });
        }
    });

    runCase('CSV content failures never send partial rows to persistence', static function (): void {
        $cases = [
            "front,back\nQuestion,Answer\n",
            "question,answer\nQuestion,Answer,Extra\n",
            "question,answer\nFirst,Valid\nSecond,\n",
            "question,answer\n" . str_repeat('x', 2001) . ",Answer\n",
            "question,answer\nQuestion,\xC3\x28\n",
            "question,answer\nQuestion,Null\0byte\n",
            "question,answer\n",
        ];
        foreach ($cases as $csv) {
            Harness::reset();
            withUpload($csv, static function (): void {
                (new DeckController())->import(7);
                $data = rendered('import');
                same(true, isset($data['errors']['csv']) && $data['errors']['csv'] !== '');
                same([], Harness::$store->imports);
                same([], Harness::$flashes);
            });
        }
    });

    runCase('CSV row boundary allows 500 records and rejects 501 without writes', static function (): void {
        foreach ([500, 501] as $count) {
            Harness::reset();
            $csv = "question,answer\n" . str_repeat("Question,Answer\n", $count);
            withUpload($csv, static function () use ($count): void {
                if ($count === 500) {
                    redirected('/decks/7', static fn() => (new DeckController())->import(7));
                    same(500, count(Harness::$store->imports[0]['rows']));
                } else {
                    (new DeckController())->import(7);
                    same('Import at most 500 flashcards per file.', rendered('import')['errors']['csv']);
                    same([], Harness::$store->imports);
                }
            });
        }
    });

    runCase('upload validation rejects empty, oversized and unregistered temporary files', static function (): void {
        foreach (['', str_repeat(' ', 1_048_577)] as $csv) {
            Harness::reset();
            withUpload($csv, static function (): void {
                (new DeckController())->import(7);
                same(true, isset(rendered('import')['errors']['csv']));
                same([], Harness::$store->imports);
            });
        }
        Harness::reset();
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_FILES['csv'] = ['error' => UPLOAD_ERR_OK, 'tmp_name' => 'unregistered-synthetic-file'];
        (new DeckController())->import(7);
        same(true, isset(rendered('import')['errors']['csv']));
        same([], Harness::$store->imports);
    });

    runCase('setup errors render an accessible alert and retain no administrator password', static function (): void {
        Harness::reset();
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = ['host' => '127.0.0.1', 'port' => '0', 'name' => 'practice', 'admin_user' => 'root', 'admin_password' => 'synthetic-only'];
        InstallController::show();
        $data = rendered('install');
        same(422, Harness::$renders[0]['status']);
        same('Enter a valid MySQL port.', $data['errors']['_form']);
        same(false, array_key_exists('admin_password', $data['old']));
        $GLOBALS['form_old'] = $data['old'];
        $GLOBALS['form_errors'] = $data['errors'];
        $errors = $data['errors'];
        $page = 'install';
        $title = $data['title'];
        $user = null;
        $path = '/setup';
        $flashes = [];
        ob_start();
        try {
            require __DIR__ . '/../app/views/pages/install.php';
            $content = ob_get_contents();
        } finally {
            ob_end_clean();
        }
        ob_start();
        try {
            require __DIR__ . '/../app/views/layouts/main.php';
            $html = ob_get_contents();
        } finally {
            ob_end_clean();
        }
        same(1, substr_count($html, 'role="alert"'));
        same(true, str_contains($html, 'Enter a valid MySQL port.'));
        same(false, str_contains($html, 'synthetic-only'));
    });

    echo $passed . ' passed, ' . $failed . ' failed.' . PHP_EOL;
    exit($failed === 0 ? 0 : 1);
}
