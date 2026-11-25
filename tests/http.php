<?php
declare(strict_types=1);

/** End-to-end HTTP checks using only the PHP CLI, native streams, and an isolated database. */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
if (!function_exists('proc_open') || !filter_var(ini_get('allow_url_fopen'), FILTER_VALIDATE_BOOLEAN) || !class_exists(DOMDocument::class)) {
    fwrite(STDERR, "HTTP tests need proc_open, allow_url_fopen, and the DOM extension enabled in CLI PHP.\n");
    exit(1);
}
$root = dirname(__DIR__);
require __DIR__ . '/DatabaseSandbox.php';
$sandbox = new DatabaseSandbox($argv);
$sandbox->apply();
$sandbox->assertConfiguration(require $root . '/app/config.php');
$tempBase = sys_get_temp_dir() . '/brainloop-http-' . bin2hex(random_bytes(8));
$serverLog = $tempBase . '.log';
$sessionPath = $tempBase . '-sessions';
if (!mkdir($sessionPath, 0700)) { throw new RuntimeException('Unable to create private test sessions.'); }
$process = null;
$pdo = null;
register_shutdown_function(static function () use (&$process, &$pdo, $sandbox, $serverLog, $sessionPath): void {
    if (is_resource($process)) { proc_terminate($process); proc_close($process); }
    $pdo = null;
    $sandbox->cleanup();
    if (is_file($serverLog)) { @unlink($serverLog); }
    foreach (glob($sessionPath . '/sess_*') ?: [] as $sessionFile) { unlink($sessionFile); }
    rmdir($sessionPath);
});
$socket = stream_socket_server('tcp://127.0.0.1:0', $socketError, $socketMessage);
if (!$socket) { throw new RuntimeException('Cannot allocate a local test port: ' . $socketMessage); }
$port = (int) substr(strrchr(stream_socket_get_name($socket, false), ':'), 1);
fclose($socket);
$environment = array_replace(getenv(), $sandbox->environment);
$mount = in_array('--subdirectory', $argv, true) ? '/BrainLoop' : '';
$serverCommand = $mount === '' ? [PHP_BINARY, '-d', 'session.save_path=' . $sessionPath, '-S', '127.0.0.1:' . $port, '-t', 'public', 'public/router.php'] : [PHP_BINARY, '-d', 'session.save_path=' . $sessionPath, '-S', '127.0.0.1:' . $port, '-t', '.', 'tests/fixtures/mount-router.php'];
$process = proc_open($serverCommand, [0 => ['pipe', 'r'], 1 => ['file', $serverLog, 'a'], 2 => ['file', $serverLog, 'a']], $pipes, $root, $environment, ['bypass_shell' => true]);
if (!is_resource($process)) { throw new RuntimeException('Unable to start isolated PHP test server.'); }
fclose($pipes[0]);

final class HttpClient
{
    public array $cookies = [];
    public array $unsafeRedirects = [];
    public function __construct(private string $baseUrl, public readonly string $mount = '') {}
    public function request(string $method, string $path, array $form = [], ?string $raw = null, ?string $contentType = null): array
    {
        $headers = ['Connection: close'];
        if ($this->cookies) { $headers[] = 'Cookie: ' . implode('; ', array_map(static fn ($key, $value) => $key . '=' . $value, array_keys($this->cookies), $this->cookies)); }
        $body = $raw ?? ($method === 'POST' ? http_build_query($form) : '');
        if ($method === 'POST') { $headers[] = 'Content-Type: ' . ($contentType ?? 'application/x-www-form-urlencoded'); }
        $context = stream_context_create(['http' => ['method' => $method, 'header' => implode("\r\n", $headers), 'content' => $body, 'ignore_errors' => true, 'follow_location' => 0, 'timeout' => 10]]);
        $target = $this->mount !== '' && $path !== $this->mount && !str_starts_with($path, $this->mount . '/') ? $this->mount . $path : $path;
        $body = @file_get_contents($this->baseUrl . $target, false, $context);
        $responseHeaders = $http_response_header ?? [];
        preg_match('~^HTTP/\S+\s+(\d+)~', $responseHeaders[0] ?? '', $statusMatch);
        $location = '';
        foreach ($responseHeaders as $header) {
            if (stripos($header, 'Set-Cookie: ') === 0) { $pair = explode(';', substr($header, 12), 2)[0]; [$key, $value] = array_pad(explode('=', $pair, 2), 2, ''); $this->cookies[$key] = $value; }
            if (stripos($header, 'Location: ') === 0) { $location = substr($header, 10); }
        }
        $rawLocation = $location;
        if ($location !== '' && $this->mount !== '') {
            if ($location === $this->mount || str_starts_with($location, $this->mount . '/')) { $location = substr($location, strlen($this->mount)) ?: '/'; }
            else { $this->unsafeRedirects[] = $location; }
        }
        return ['status' => (int) ($statusMatch[1] ?? 0), 'body' => $body === false ? '' : $body, 'headers' => $responseHeaders, 'location' => $location, 'raw_location' => $rawLocation];
    }
    public function token(string $path = '/dashboard'): string { return hidden($this->request('GET', $path)['body'], 'csrf_token'); }
    public function post(string $path, array $fields = [], string $tokenPage = '/dashboard'): array { return $this->request('POST', $path, ['csrf_token' => $this->token($tokenPage)] + $fields); }
    public function upload(string $path, string $content, string $token): array
    {
        $boundary = 'BrainLoopTest' . bin2hex(random_bytes(10));
        $raw = '--' . $boundary . "\r\nContent-Disposition: form-data; name=\"csrf_token\"\r\n\r\n" . $token . "\r\n";
        $raw .= '--' . $boundary . "\r\nContent-Disposition: form-data; name=\"csv\"; filename=\"cards.csv\"\r\nContent-Type: text/csv\r\n\r\n" . $content . "\r\n--" . $boundary . "--\r\n";
        return $this->request('POST', $path, [], $raw, 'multipart/form-data; boundary=' . $boundary);
    }
}
function hidden(string $html, string $name): string { preg_match('~name="' . preg_quote($name, '~') . '"\s+value="([^"]*)"~', $html, $matches); return html_entity_decode($matches[1] ?? '', ENT_QUOTES, 'UTF-8'); }
$passed = 0;
$failed = 0;
function verify(string $label, bool $condition): void { global $passed, $failed; if ($condition) { ++$passed; echo "PASS {$label}\n"; } else { ++$failed; echo "FAIL {$label}\n"; } }
function scalar(PDO $pdo, string $sql, array $parameters = []): mixed { $statement = $pdo->prepare($sql); $statement->execute($parameters); return $statement->fetchColumn(); }

$url = 'http://127.0.0.1:' . $port;
$a = new HttpClient($url, $mount);
$b = new HttpClient($url, $mount);
$anonymous = new HttpClient($url, $mount);
for ($attempt = 0; $attempt < 50; ++$attempt) { $ready = $a->request('GET', '/'); if ($ready['status'] !== 0) { break; } usleep(100000); }
if ($ready['status'] === 0) { throw new RuntimeException('Server did not start. ' . @file_get_contents($serverLog)); }

try {
    foreach (['/' => 'home', '/features' => 'features', '/how-it-works' => 'how it works', '/about' => 'about', '/privacy' => 'privacy', '/terms' => 'terms', '/login' => 'login', '/register' => 'registration'] as $path => $label) {
        $response = $a->request('GET', $path);
        verify('Public ' . $label . ' page renders', $response['status'] === 200 && str_contains($response['body'], 'BrainLoop'));
    }
    verify('Stylesheet serves successfully', $a->request('GET', '/assets/css/styles.css')['status'] === 200);
    verify('Responsive stylesheet serves successfully', $a->request('GET', '/assets/css/responsive.css')['status'] === 200);
    verify('Unknown route returns custom 404', $a->request('GET', '/no-such-brainloop-page')['status'] === 404);
    verify('Protected dashboard redirects anonymous visitors', $anonymous->request('GET', '/dashboard')['location'] === '/login');
    verify('Private storage path cannot be downloaded', $a->request('GET', '/storage/private-backup.sql')['status'] === 404);
    verify('Application source cannot be downloaded', $a->request('GET', '/app/config.php')['status'] === 404);
    verify('Unsupported HTTP method returns 405', $a->request('PUT', '/dashboard')['status'] === 405);
    verify('State-changing request without CSRF is rejected', $a->request('POST', '/register', ['email' => 'missing@example.invalid'])['status'] === 403);
    $invalid = $a->post('/register', ['full_name' => '', 'email' => 'not-an-email', 'password' => 'short', 'password_confirmation' => 'different'], '/register');
    verify('Registration validates required fields and password', $invalid['status'] === 422 && str_contains($invalid['body'], 'valid email'));
    $password = 'HTTP-test-password-2026';
    $name = 'HTTP Student';
    $emailA = 'http-a@example.invalid';
    $emailB = 'http-b@example.invalid';
    $beforeSession = $a->cookies['brainloop_session'] ?? '';
    $registered = $a->post('/register', ['full_name' => $name, 'email' => $emailA, 'password' => $password, 'password_confirmation' => $password], '/register');
    verify('Registration creates an account and signs in', $registered['status'] === 303 && $registered['location'] === '/dashboard');
    verify('Authentication regenerates the session ID', $beforeSession !== '' && $beforeSession !== ($a->cookies['brainloop_session'] ?? ''));
    $pdo = $sandbox->connection();
    $userA = (int) scalar($pdo, 'SELECT id FROM users WHERE email=?', [$emailA]);
    $hash = scalar($pdo, 'SELECT password_hash FROM users WHERE id=?', [$userA]);
    verify('Registered password is stored only as a hash', is_string($hash) && $hash !== $password && password_verify($password, $hash));
    $dashboard = $a->request('GET', '/dashboard');
    verify('New dashboard is a real empty state', $dashboard['status'] === 200 && (int) scalar($pdo, 'SELECT COUNT(*) FROM decks') === 0);
    verify('CSP explicitly disables script execution', str_contains(implode("\n", $dashboard['headers']), "script-src 'none'"));
    $duplicate = $b->post('/register', ['full_name' => 'Duplicate', 'email' => strtoupper($emailA), 'password' => $password, 'password_confirmation' => $password], '/register');
    verify('Duplicate email registration is rejected case-insensitively', $duplicate['status'] === 422 && str_contains($duplicate['body'], 'already uses'));
    $createdB = $b->post('/register', ['full_name' => 'Second Student', 'email' => $emailB, 'password' => $password, 'password_confirmation' => $password], '/register');
    verify('Second independent account can register', $createdB['location'] === '/dashboard');
    $logout = $a->post('/logout');
    verify('Logout succeeds through POST', $logout['status'] === 303 && $logout['location'] === '/login');
    verify('Logout removes access to private routes', $a->request('GET', '/decks')['location'] === '/login');
    $badLogin = $a->post('/login', ['email' => $emailA, 'password' => 'incorrect-password'], '/login');
    verify('Incorrect login is rejected', $badLogin['status'] === 422 && str_contains($badLogin['body'], 'incorrect'));
    $login = $a->post('/login', ['email' => $emailA, 'password' => $password], '/login');
    verify('Valid login succeeds', $login['location'] === '/dashboard');

    $deckTitle = 'HTTP <b>Biology</b> 100%';
    $deckResponse = $a->post('/decks/create', ['title' => $deckTitle, 'description' => 'A private test collection.', 'subject' => 'Biology', 'color' => '#6C5CE7']);
    preg_match('~^/decks/(\d+)$~', $deckResponse['location'], $deckMatch);
    $deckId = (int) ($deckMatch[1] ?? 0);
    if ($deckId === 0) { throw new RuntimeException('Deck creation failed; cannot continue resource checks.'); }
    verify('Deck creation persists and redirects to detail', $deckResponse['status'] === 303 && scalar($pdo, 'SELECT title FROM decks WHERE id=?', [$deckId]) === $deckTitle);
    $detail = $a->request('GET', '/decks/' . $deckId);
    verify('Deck title is escaped on output', $detail['status'] === 200 && str_contains($detail['body'], '&lt;b&gt;Biology&lt;/b&gt;') && !str_contains($detail['body'], '<b>Biology</b>'));
    verify('Deck is readable only by its owner', $b->request('GET', '/decks/' . $deckId)['status'] === 404);
    verify('Deck edit form enforces ownership', $b->request('GET', '/decks/' . $deckId . '/edit')['status'] === 404);
    verify('Deck export enforces ownership', $b->request('GET', '/decks/' . $deckId . '/export')['status'] === 404);
    verify('Deck import enforces ownership', $b->request('GET', '/decks/' . $deckId . '/import')['status'] === 404);
    verify('Deck deletion by another user is rejected', $b->post('/decks/' . $deckId . '/delete', ['confirm' => 'yes'])['status'] === 404);
    $invalidDeck = $a->post('/decks/create', ['title' => '', 'description' => '', 'subject' => '', 'color' => '#000000']);
    verify('Deck fields and color selection are validated', $invalidDeck['status'] === 200 && str_contains($invalidDeck['body'], 'title'));
    $updated = $a->post('/decks/' . $deckId . '/edit', ['title' => $deckTitle, 'description' => 'Updated description', 'subject' => 'Science', 'color' => '#24B8A6']);
    verify('Owner can update deck metadata', $updated['status'] === 303 && scalar($pdo, 'SELECT subject FROM decks WHERE id=?', [$deckId]) === 'Science');
    $answer = 'UniqueSecretAnswer-' . bin2hex(random_bytes(6));
    $question = "What is the basic unit of life?\nRecall it first.";
    $cardResponse = $a->post('/decks/' . $deckId . '/cards/create', ['question' => $question, 'answer' => $answer, 'explanation' => 'A multiline flashcard.']);
    $cardId = (int) scalar($pdo, 'SELECT id FROM flashcards WHERE deck_id=?', [$deckId]);
    verify('Card creation persists a real due record', $cardResponse['status'] === 303 && $cardId > 0 && scalar($pdo, 'SELECT review_state FROM card_progress WHERE flashcard_id=?', [$cardId]) === 'new');
    verify('Other users cannot edit cards', $b->post('/cards/' . $cardId . '/edit', ['question' => 'Hijacked', 'answer' => 'A', 'explanation' => ''])['status'] === 404);
    verify('Other users cannot delete cards', $b->post('/cards/' . $cardId . '/delete', ['confirm' => 'yes'])['status'] === 404);
    verify('Other users cannot add cards to a private deck', $b->post('/decks/' . $deckId . '/cards/create', ['question' => 'Hijacked', 'answer' => 'A', 'explanation' => ''])['status'] === 404);
    $emptyCard = $a->post('/decks/' . $deckId . '/cards/create', ['question' => '', 'answer' => '', 'explanation' => '']);
    verify('Empty cards are rejected without creating rows', $emptyCard['status'] === 200 && (int) scalar($pdo, 'SELECT COUNT(*) FROM flashcards WHERE deck_id=?', [$deckId]) === 1);
    $search = $a->request('GET', '/search?q=' . urlencode($answer));
    verify('Server search finds card answers', $search['status'] === 200 && str_contains($search['body'], 'basic unit'));
    verify('Search never exposes another users card', !str_contains($b->request('GET', '/search?q=' . urlencode($answer))['body'], 'basic unit'));
    verify('SQL-like search input remains safe data', $a->request('GET', '/search?q=' . urlencode("' OR 1=1 --"))['status'] === 200);
    verify('Search query HTML is escaped', !str_contains($a->request('GET', '/search?q=' . urlencode('<b>query</b>'))['body'], '<b>query</b>'));
    verify('Invalid resource IDs return 404', $a->request('GET', '/decks/0')['status'] === 404 && $a->request('GET', '/cards/-1/edit')['status'] === 404);
    $confirmation = $a->request('GET', '/decks/' . $deckId . '/delete');
    verify('GET deletion shows confirmation without deleting', $confirmation['status'] === 200 && (int) scalar($pdo, 'SELECT COUNT(*) FROM decks WHERE id=?', [$deckId]) === 1);
    verify('Deletion requires explicit confirmation', $a->post('/decks/' . $deckId . '/delete')['status'] === 422);

    $start = $a->post('/study/start', ['deck_id' => (string) $deckId, 'mode' => 'due']);
    verify('A due-only study session starts', $start['status'] === 303 && $start['location'] === '/study/session');
    $initial = $a->request('GET', '/study/session');
    $nonce = hidden($initial['body'], 'nonce');
    verify('Initial study HTML never contains the answer', $initial['status'] === 200 && !str_contains($initial['body'], $answer) && str_contains($initial['body'], 'basic unit'));
    verify('Rating before answer reveal is rejected', $a->post('/study/rate', ['nonce' => $nonce, 'rating' => 'good'])['status'] === 409);
    $reveal = $a->post('/study/reveal', ['nonce' => $nonce]);
    verify('Reveal uses POST then redirect', $reveal['status'] === 303 && $reveal['location'] === '/study/session');
    $revealed = $a->request('GET', '/study/session');
    verify('Answer is present after explicit reveal', str_contains($revealed['body'], $answer));
    $rated = $a->post('/study/rate', ['nonce' => $nonce, 'rating' => 'good']);
    verify('Rating completes the session and opens results', $rated['status'] === 303 && $rated['location'] === '/study/results');
    verify('Rating persists scheduling and review history', (int) scalar($pdo, 'SELECT total_reviews FROM card_progress WHERE flashcard_id=?', [$cardId]) === 1 && (int) scalar($pdo, 'SELECT COUNT(*) FROM review_history WHERE flashcard_id=?', [$cardId]) === 1 && scalar($pdo, 'SELECT due_at FROM card_progress WHERE flashcard_id=?', [$cardId]) > gmdate('Y-m-d H:i:s'));
    $retry = $a->post('/study/rate', ['nonce' => $nonce, 'rating' => 'good']);
    verify('Duplicate rating cannot double count after completion', $retry['status'] === 303 && (int) scalar($pdo, 'SELECT COUNT(*) FROM review_history WHERE flashcard_id=?', [$cardId]) === 1);
    verify('Completion screen renders saved session results', $a->request('GET', '/study/results')['status'] === 200);
    $futureStart = $a->post('/study/start', ['deck_id' => (string) $deckId, 'mode' => 'due']);
    verify('Future-due cards are not placed into due sessions', $futureStart['location'] === '/decks/' . $deckId);
    $beforePractice = $pdo->query('SELECT * FROM card_progress WHERE flashcard_id=' . $cardId)->fetch();
    $practiceStart = $a->post('/study/start', ['deck_id' => (string) $deckId, 'mode' => 'practice']);
    $practicePage = $a->request('GET', '/study/session');
    $practiceNonce = hidden($practicePage['body'], 'nonce');
    $a->post('/study/reveal', ['nonce' => $practiceNonce]);
    $a->post('/study/rate', ['nonce' => $practiceNonce, 'rating' => 'easy']);
    $afterPractice = $pdo->query('SELECT * FROM card_progress WHERE flashcard_id=' . $cardId)->fetch();
    verify('Practice includes future cards without changing scheduling', $practiceStart['location'] === '/study/session' && $beforePractice === $afterPractice && (int) scalar($pdo, 'SELECT COUNT(*) FROM review_history WHERE flashcard_id=? AND is_practice=1', [$cardId]) === 1);
    verify('Analytics renders real persisted review data', $a->request('GET', '/analytics')['status'] === 200);
    $privateSession = $b->post('/study/start', ['deck_id' => (string) $deckId, 'mode' => 'practice']);
    verify('Study session creation enforces deck ownership', $privateSession['status'] === 404);
    verify('Study controls reject GET mutation', $a->request('GET', '/study/start')['status'] === 405);

    $importPath = '/decks/' . $deckId . '/import';
    $invalidCsv = $a->upload($importPath, "bad,header\nQ,A\n", $a->token($importPath));
    verify('CSV import rejects invalid headers', $invalidCsv['status'] === 200 && str_contains($invalidCsv['body'], 'header must'));
    $invalidUtf8 = $a->upload($importPath, "question,answer\nInvalid\xFF,Answer\n", $a->token($importPath));
    verify('CSV import rejects invalid UTF-8 safely', $invalidUtf8['status'] === 200 && str_contains($invalidUtf8['body'], 'UTF-8'));
    $oversized = $a->upload($importPath, "question,answer\n" . str_repeat('x', 1024 * 1024) . ',A', $a->token($importPath));
    verify('CSV upload enforces the 1 MB limit', $oversized['status'] === 200 && (str_contains($oversized['body'], '1 MB') || str_contains($oversized['body'], 'at most')));
    $tooMany = $a->upload($importPath, "question,answer\n" . str_repeat("Q,A\n", 501), $a->token($importPath));
    verify('CSV import enforces the 500-card limit atomically', $tooMany['status'] === 200 && str_contains($tooMany['body'], '500') && (int) scalar($pdo, 'SELECT COUNT(*) FROM flashcards WHERE deck_id=?', [$deckId]) === 1);
    $goodCsv = $a->upload($importPath, "\xEF\xBB\xBFquestion,answer,explanation\n=1+1,Formula-leading question,Test\nUTF-8 세포,Cell,Valid Unicode\n", $a->token($importPath));
    verify('UTF-8 CSV import creates valid cards', $goodCsv['status'] === 303 && (int) scalar($pdo, 'SELECT COUNT(*) FROM flashcards WHERE deck_id=?', [$deckId]) === 3);
    $export = $a->request('GET', '/decks/' . $deckId . '/export');
    verify('CSV export neutralizes formula-leading cells', $export['status'] === 200 && str_contains($export['body'], "'=1+1") && str_contains($export['body'], '세포'));

    $a->post('/study/start', ['deck_id' => (string) $deckId, 'mode' => 'practice']);
    $multiPage = $a->request('GET', '/study/session');
    $multiNonce = hidden($multiPage['body'], 'nonce');
    $a->post('/study/reveal', ['nonce' => $multiNonce]);
    $historyBefore = (int) scalar($pdo, 'SELECT COUNT(*) FROM review_history WHERE user_id=?', [$userA]);
    $advance = $a->post('/study/rate', ['nonce' => $multiNonce, 'rating' => 'hard']);
    verify('Multi-card study advances after a rating', $advance['location'] === '/study/session' && (int) scalar($pdo, 'SELECT COUNT(*) FROM review_history WHERE user_id=?', [$userA]) === $historyBefore + 1);
    $a->post('/study/rate', ['nonce' => $multiNonce, 'rating' => 'hard']);
    verify('A stale nonce cannot rate the next card', (int) scalar($pdo, 'SELECT COUNT(*) FROM review_history WHERE user_id=?', [$userA]) === $historyBefore + 1);
    $nextPage = $a->request('GET', '/study/session');
    $nextNonce = hidden($nextPage['body'], 'nonce');
    $a->post('/study/reveal', ['nonce' => $nextNonce]);
    verify('Invalid recall ratings are rejected', $a->post('/study/rate', ['nonce' => $nextNonce, 'rating' => 'perfect'])['status'] === 422);
    $exited = $a->post('/study/exit');
    $exitResult = $a->request('GET', '/study/results');
    verify('Exiting preserves partial progress and displays honest results', $exited['location'] === '/study/results' && $exitResult['status'] === 200 && str_contains($exitResult['body'], '<h1>Every little bit counts.</h1>') && str_contains($exitResult['body'], '<strong>1</strong><span>Cards reviewed</span>') && (int) scalar($pdo, 'SELECT COUNT(*) FROM review_history WHERE user_id=?', [$userA]) === $historyBefore + 1);

    $badEmailChange = $a->post('/settings', ['action' => 'profile', 'full_name' => 'Updated HTTP Student', 'email' => 'updated-http@example.invalid', 'current_password' => 'wrong']);
    verify('Email changes require the current password', $badEmailChange['status'] === 422 && scalar($pdo, 'SELECT email FROM users WHERE id=?', [$userA]) === $emailA);
    $updatedEmail = 'updated-http@example.invalid';
    $profile = $a->post('/settings', ['action' => 'profile', 'full_name' => 'Updated HTTP Student', 'email' => $updatedEmail, 'current_password' => $password]);
    verify('Profile updates persist verified name and email', $profile['status'] === 303 && scalar($pdo, 'SELECT email FROM users WHERE id=?', [$userA]) === $updatedEmail);
    $newPassword = 'replacement-HTTP-password';
    $badPasswordChange = $a->post('/settings', ['action' => 'password', 'current_password' => 'wrong', 'password' => $newPassword, 'password_confirmation' => $newPassword]);
    verify('Password changes reject incorrect current credentials', $badPasswordChange['status'] === 422 && password_verify($password, scalar($pdo, 'SELECT password_hash FROM users WHERE id=?', [$userA])));
    $change = $a->post('/settings', ['action' => 'password', 'current_password' => $password, 'password' => $newPassword, 'password_confirmation' => $newPassword]);
    verify('Password change persists a new hash', $change['status'] === 303 && password_verify($newPassword, scalar($pdo, 'SELECT password_hash FROM users WHERE id=?', [$userA])));
    $edit = $a->post('/cards/' . $cardId . '/edit', ['question' => 'Updated biology question', 'answer' => $answer, 'explanation' => 'Edited']);
    verify('Owner can edit a card', $edit['status'] === 303 && scalar($pdo, 'SELECT question FROM flashcards WHERE id=?', [$cardId]) === 'Updated biology question');

    // Inspect actual rendered links while every test resource still exists. Only GET requests are used.
    $crawlGroups = [
        [$anonymous, ['/', '/features', '/how-it-works', '/about', '/privacy', '/terms', '/login', '/register']],
        [$a, ['/dashboard', '/decks', '/decks/create', '/decks/' . $deckId, '/decks/' . $deckId . '/edit', '/decks/' . $deckId . '/delete', '/decks/' . $deckId . '/import', '/decks/' . $deckId . '/cards/create', '/cards/' . $cardId . '/edit', '/cards/' . $cardId . '/delete', '/study', '/study/session', '/study/results', '/analytics', '/search?q=biology', '/settings', '/logout']],
    ];
    $linkTargets = [];
    $scriptFree = true;
    $mountedLinks = true;
    $crawlFailures = [];
    $renderedPages = 0;
    foreach ($crawlGroups as [$client, $pagePaths]) {
        foreach ($pagePaths as $pagePath) {
            $rendered = $client->request('GET', $pagePath);
            if (!in_array($rendered['status'], [200, 303], true)) { $crawlFailures[] = $pagePath . ': HTTP ' . $rendered['status']; continue; }
            if ($rendered['status'] === 303) { continue; }
            ++$renderedPages;
            $document = new DOMDocument();
            $previousLibxml = libxml_use_internal_errors(true);
            $document->loadHTML($rendered['body'], LIBXML_NONET);
            libxml_clear_errors();
            libxml_use_internal_errors($previousLibxml);
            if ($document->getElementsByTagName('script')->length > 0) { $scriptFree = false; }
            foreach ($document->getElementsByTagName('*') as $element) {
                foreach ($element->attributes as $attribute) {
                    if (str_starts_with(strtolower($attribute->name), 'on') || (in_array(strtolower($attribute->name), ['href', 'src'], true) && preg_match('/^\s*javascript:/i', $attribute->value))) { $scriptFree = false; }
                }
            }
            foreach (['a', 'link'] as $tag) {
                foreach ($document->getElementsByTagName($tag) as $node) {
                    $href = trim(explode('#', $node->getAttribute('href'), 2)[0]);
                    if ($href === '' || !str_starts_with($href, '/') || str_starts_with($href, '//')) { continue; }
                    if ($mount !== '' && $href !== $mount && !str_starts_with($href, $mount . '/')) { $mountedLinks = false; }
                    $linkTargets[spl_object_id($client) . ':' . $href] = [$client, $href];
                }
            }
        }
    }
    foreach ($linkTargets as [$client, $target]) {
        $response = $client->request('GET', $target);
        $redirects = 0;
        while ($response['status'] === 303 && ++$redirects <= 5) {
            $location = $response['location'];
            if (!str_starts_with($location, '/') || str_starts_with($location, '//') || preg_match('/[\r\n]/', $location)) { break; }
            $response = $client->request('GET', $location);
        }
        if ($response['status'] !== 200) { $crawlFailures[] = $target . ': HTTP ' . $response['status']; }
    }
    // Two completed-session routes redirect after the password rotates the session.
    // The current crawl therefore renders at least 23 HTML pages.
    verify('Rendered public and private pages contain no scripts or event handlers (' . $renderedPages . ' pages)', $scriptFree && $renderedPages >= 23);
    verify('Internal anchors and linked assets resolve through safe GET requests (' . count($linkTargets) . ' targets)', $crawlFailures === []);
    if ($crawlFailures) { echo 'Broken link details: ' . implode('; ', array_unique($crawlFailures)) . "\n"; }
    verify('Original BrainLoop favicon serves successfully', $a->request('GET', '/assets/icons/favicon.svg')['status'] === 200);
    if ($mount !== '') {
        verify('Rendered anchors and assets retain the application subdirectory', $mountedLinks);
        verify('Authentication and study redirects retain the application subdirectory', $a->unsafeRedirects === [] && $b->unsafeRedirects === [] && $anonymous->unsafeRedirects === []);
        verify('Root index.php opens the mounted application', $a->request('GET', '/index.php')['status'] === 200);
    }

    verify('Card GET deletion also preserves data', $a->request('GET', '/cards/' . $cardId . '/delete')['status'] === 200 && (int) scalar($pdo, 'SELECT COUNT(*) FROM flashcards WHERE id=?', [$cardId]) === 1);
    $deleteCard = $a->post('/cards/' . $cardId . '/delete', ['confirm' => 'yes']);
    verify('Card deletion cascades its review history', $deleteCard['status'] === 303 && (int) scalar($pdo, 'SELECT COUNT(*) FROM review_history WHERE flashcard_id=?', [$cardId]) === 0);
    $deleteDeck = $a->post('/decks/' . $deckId . '/delete', ['confirm' => 'yes']);
    verify('Deck deletion cascades all remaining cards and schedules', $deleteDeck['status'] === 303 && (int) scalar($pdo, 'SELECT COUNT(*) FROM flashcards WHERE deck_id=?', [$deckId]) === 0 && (int) scalar($pdo, 'SELECT COUNT(*) FROM card_progress WHERE user_id=?', [$userA]) === 0);
    $a->post('/logout');
    $newLogin = $a->post('/login', ['email' => $updatedEmail, 'password' => $newPassword], '/login');
    verify('Updated account credentials can log in', $newLogin['location'] === '/dashboard');
    if ($newLogin['location'] !== '/dashboard') { echo 'Login diagnostic: HTTP ' . $newLogin['status'] . ', redirect=' . $newLogin['location'] . "; user exists=" . ((int) scalar($pdo, 'SELECT COUNT(*) FROM users WHERE email=?', [$updatedEmail])) . "\n"; }
    $seed = proc_open([PHP_BINARY, 'scripts/seed-demo.php', $updatedEmail], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $seedPipes, $root, $environment, ['bypass_shell' => true]);
    if (!is_resource($seed)) { throw new RuntimeException('Cannot run sample-content check.'); }
    fclose($seedPipes[0]); $seedOutput = stream_get_contents($seedPipes[1]); $seedError = stream_get_contents($seedPipes[2]); fclose($seedPipes[1]); fclose($seedPipes[2]); $seedExit = proc_close($seed);
    verify('Optional sample script adds five decks without invented reviews', $seedExit === 0 && str_contains($seedOutput, '5 sample decks and 15 cards') && (int) scalar($pdo, 'SELECT COUNT(*) FROM review_history WHERE user_id=?', [$userA]) === 0);
    if ($seedExit !== 0) { echo 'Sample script diagnostic: ' . $seedError . "\n"; }
    $seed = proc_open([PHP_BINARY, 'scripts/seed-demo.php', $updatedEmail], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $seedPipes, $root, $environment, ['bypass_shell' => true]);
    if (!is_resource($seed)) { throw new RuntimeException('Cannot repeat sample-content check.'); }
    fclose($seedPipes[0]); $seedOutput = stream_get_contents($seedPipes[1]); $seedError = stream_get_contents($seedPipes[2]); fclose($seedPipes[1]); fclose($seedPipes[2]); $seedExit = proc_close($seed);
    verify('Sample script is idempotent for existing sample decks', $seedExit === 0 && str_contains($seedOutput, '0 sample decks and 0 cards') && (int) scalar($pdo, 'SELECT COUNT(*) FROM decks WHERE user_id=?', [$userA]) === 5);
} catch (Throwable $error) {
    ++$failed;
    echo 'FAIL HTTP suite could not continue: ' . $error->getMessage() . "\n";
    if (is_file($serverLog)) { echo substr((string) file_get_contents($serverLog), -6000); }
}
echo "\n{$passed} HTTP checks passed; {$failed} failed. Server used " . $sandbox->description() . ($mount !== '' ? ' mounted at ' . $mount . '/' : '') . ".\n";
exit($failed === 0 ? 0 : 1);
