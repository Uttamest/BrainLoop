<?php
declare(strict_types=1);

// Keep controller ownership and real view checks independent of the application DB.
namespace BrainLoop\Tests {
    final class AuthenticationRequired extends \Exception {}

    final class OverviewStore
    {
        public array $calls = [];
        public ?string $failedRead = null;
        public ?\Throwable $failure = null;
        public array $owners;

        public function __construct()
        {
            $this->owners = [17 => self::collection('Owned <concept> & notes', 117), 29 => self::collection('Other owner private deck', 229)];
        }

        private static function collection(string $title, int $deckId): array
        {
            $reviews = $deckId === 117 ? 2 : 9;
            $daily = [];
            for ($day = 0; $day < 14; ++$day) {
                $date = (new \DateTimeImmutable('2026-11-15', new \DateTimeZone('UTC')))->modify('+' . $day . ' days');
                $daily[] = ['date' => $date->format('Y-m-d'), 'label' => $date->format('D'), 'count' => $day === 13 ? $reviews : 0];
            }
            return [
                'stats' => ['decks' => 1, 'cards' => 3, 'due' => 1, 'reviews' => $reviews, 'today_cards' => 1,
                    'today_scheduled' => 1, 'current_streak' => 1, 'longest_streak' => 2],
                'decks' => [['id' => $deckId, 'title' => $title, 'description' => 'Practice <description>',
                    'subject' => 'Synthetic subject', 'color' => '#6C5CE7', 'card_count' => 3, 'due_count' => 1, 'progress' => 33]],
                'daily' => $daily,
                'ratings' => ['again' => $reviews - 2, 'hard' => 0, 'good' => 1, 'easy' => 1],
                'activity' => [['deck_title' => $title, 'question' => 'Practice <question>', 'rating' => 'good',
                    'is_practice' => false, 'reviewed_at' => '2026-11-28 00:00:00']],
            ];
        }

        private function read(string $name, int $owner): array
        {
            $this->calls[] = [$name, $owner];
            if ($name === $this->failedRead) {
                throw $this->failure;
            }
            if (!isset($this->owners[$owner])) {
                throw new \RuntimeException('Unexpected synthetic owner');
            }
            return $this->owners[$owner][$name];
        }

        public function stats(int $owner): array { return $this->read('stats', $owner); }
        public function decks(int $owner): array { return $this->read('decks', $owner); }
        public function daily(int $owner): array { return $this->read('daily', $owner); }
        public function ratings(int $owner): array { return $this->read('ratings', $owner); }
        public function activity(int $owner): array { return $this->read('activity', $owner); }
    }

    final class OverviewHarness
    {
        public static ?array $user;
        public static OverviewStore $store;
        public static int $storeRequests;
        public static array $renders;

        public static function reset(): void
        {
            self::$user = ['id' => '17', 'full_name' => 'Synthetic Learner'];
            self::$store = new OverviewStore();
            self::$storeRequests = 0;
            self::$renders = [];
            $_SESSION = [];
            $_SERVER['SCRIPT_NAME'] = '/BrainLoop/index.php';
            $_SERVER['REQUEST_URI'] = '/BrainLoop/dashboard';
        }
    }
}

namespace App\Controllers {
    use BrainLoop\Tests\{AuthenticationRequired, OverviewHarness, OverviewStore};

    function require_user(): array
    {
        if (OverviewHarness::$user === null) {
            throw new AuthenticationRequired('Synthetic unauthenticated request');
        }
        return OverviewHarness::$user;
    }

    function store(): OverviewStore
    {
        ++OverviewHarness::$storeRequests;
        return OverviewHarness::$store;
    }

    function render(string $page, array $data): void
    {
        OverviewHarness::$renders[] = compact('page', 'data');
    }
}

namespace {
    use App\Controllers\PageController;
    use BrainLoop\Tests\{AuthenticationRequired, OverviewHarness};

    if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
    require __DIR__ . '/../app/core/helpers.php';
    require __DIR__ . '/../app/core/urls.php';
    require __DIR__ . '/../app/views/partials/icons.php';
    require __DIR__ . '/../app/controllers/PageController.php';

    set_error_handler(static function (int $severity, string $message, string $file, int $line): never {
        throw new ErrorException($message, 0, $severity, $file, $line);
    });

    function same(mixed $expected, mixed $actual, string $reason): void
    {
        if ($expected !== $actual) {
            throw new RuntimeException($reason . PHP_EOL . 'Expected ' . var_export($expected, true)
                . ', received ' . var_export($actual, true));
        }
    }

    function response(): array
    {
        same(1, count(OverviewHarness::$renders), 'Exactly one page must be rendered');
        return OverviewHarness::$renders[0];
    }

    function renderedPage(): array
    {
        $response = response();
        $_SERVER['REQUEST_URI'] = '/BrainLoop/' . $response['page'];
        ob_start();
        try {
            \render($response['page'], $response['data'] + ['user' => OverviewHarness::$user]);
            $html = ob_get_contents();
        } finally {
            ob_end_clean();
        }
        $document = new DOMDocument();
        same(true, $document->loadHTML($html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING), 'Shared page must parse');
        return [$html, new DOMXPath($document)];
    }

    $passed = $failed = 0;
    $report = [];
    function check(string $name, Closure $action): void
    {
        global $passed, $failed, $report;
        OverviewHarness::reset();
        try {
            $action();
            ++$passed;
            $report[] = 'PASS ' . $name;
        } catch (Throwable $error) {
            ++$failed;
            $report[] = 'FAIL ' . $name . ': ' . $error->getMessage();
        }
    }

    foreach (['dashboard' => false, 'analytics' => true] as $page => $analytics) {
        check($page . ' passes only the authenticated owner data to its view', static function () use ($page, $analytics): void {
            foreach ([17, 29] as $owner) {
                OverviewHarness::reset();
                OverviewHarness::$user['id'] = (string) $owner;
                PageController::overview($analytics);
                $response = response();
                same($page, $response['page'], 'The requested overview mode must select its page');
                same($analytics ? 'Your learning, in focus' : 'Dashboard', $response['data']['title'], 'Page title must match its mode');
                $data = $response['data'];
                unset($data['title']);
                same(OverviewHarness::$store->owners[$owner], $data, 'All view data must come from the signed-in owner');
                same(1, OverviewHarness::$storeRequests, 'The overview must use one store instance');
            }
        });

        check($page . ' rejects an unauthenticated request before store access', static function () use ($analytics): void {
            OverviewHarness::$user = null;
            try {
                PageController::overview($analytics);
                throw new RuntimeException('Expected authentication to stop the request');
            } catch (AuthenticationRequired) {}
            same(0, OverviewHarness::$storeRequests, 'No store may be requested before authentication');
            same([], OverviewHarness::$store->calls, 'No private data may be read');
            same([], OverviewHarness::$renders, 'No private page may be rendered');
        });

        check($page . ' renders owned results with escaped text and mounted links', static function () use ($analytics): void {
            PageController::overview($analytics);
            [$html, $xpath] = renderedPage();
            same(1, $xpath->query('//main[@id="main-content"]')->length, 'The shared page must have its main target');
            same(1, $xpath->query('//main//h1')->length, 'The page must keep one primary heading');
            same(true, str_contains($html, 'Owned &lt;concept&gt; &amp; notes'), 'Owned deck titles must be escaped');
            same(false, str_contains($html, 'Other owner private deck'), 'Another owner must not appear in the rendered page');
            same(true, $xpath->query('//main//a[@href="/BrainLoop/decks/117"]')->length > 0, 'Owned deck links must retain the mount path');
            same(0, $xpath->query('//main//a[@href="/BrainLoop/decks/229"]')->length, 'Another owner deck link must not appear');
            same(0, $xpath->query('//script')->length, 'Overview pages must remain server rendered without scripts');
        });

        check($page . ' renders an empty collection without fabricated activity', static function () use ($analytics): void {
            $empty = OverviewHarness::$store->owners[17];
            $empty['stats'] = array_fill_keys(array_keys($empty['stats']), 0);
            foreach (['decks', 'activity'] as $name) { $empty[$name] = []; }
            $empty['daily'] = array_map(static fn(array $day): array => array_replace($day, ['count' => 0]), $empty['daily']);
            $empty['ratings'] = array_fill_keys(array_keys($empty['ratings']), 0);
            OverviewHarness::$store->owners[17] = $empty;
            PageController::overview($analytics);
            [$html, $xpath] = renderedPage();
            same($empty['stats'], response()['data']['stats'], 'Empty statistics must remain zero');
            same(true, str_contains($html, $analytics ? 'Your progress has room to grow' : 'A fresh start for your ideas'), 'An empty collection needs actual next-step guidance');
            same(0, $xpath->query('//ol[contains(@class,"activity-list")]')->length, 'An empty collection must not invent activity');
            same(false, str_contains($html, 'Owned &lt;concept&gt;'), 'Empty collections must not contain seeded deck content');
        });
    }

    foreach (['stats', 'decks', 'daily', 'ratings', 'activity'] as $read) {
        check('a failed ' . $read . ' read propagates without rendering partial statistics', static function () use ($read): void {
            foreach ([false, true] as $analytics) {
                OverviewHarness::reset();
                $failure = new RuntimeException('Synthetic unavailable ' . $read);
                OverviewHarness::$store->failedRead = $read;
                OverviewHarness::$store->failure = $failure;
                try {
                    PageController::overview($analytics);
                    throw new LogicException('Expected the data failure to propagate');
                } catch (RuntimeException $received) {
                    same($failure, $received, 'The original data failure must be preserved');
                }
                same([], OverviewHarness::$renders, 'Incomplete overview data must not be rendered as success');
            }
        });
    }

    // The real renderer sets a status code, so report only after every view check.
    echo implode(PHP_EOL, $report) . PHP_EOL . $passed . ' passed, ' . $failed . ' failed.' . PHP_EOL;
    exit($failed === 0 ? 0 : 1);
}
