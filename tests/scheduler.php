<?php
declare(strict_types=1);

namespace BrainLoop\Tests\Scheduling;

use App\Services\Scheduler;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__ . '/../app/services/Scheduler.php';

function same(mixed $expected, mixed $actual, string $label = ''): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($label . PHP_EOL . 'Expected: ' . var_export($expected, true)
            . PHP_EOL . 'Actual: ' . var_export($actual, true));
    }
}

function fields(array $expected, array $actual): void
{
    foreach ($expected as $name => $value) {
        same($value, $actual[$name] ?? null, $name);
    }
}

function near(float $expected, float $actual): void
{
    if (abs($expected - $actual) > 0.000000000001) {
        throw new RuntimeException('Unexpected fractional interval: ' . $actual);
    }
}

function utc(string $time): DateTimeImmutable
{
    return new DateTimeImmutable($time, new DateTimeZone('UTC'));
}

$passed = 0;
$failed = 0;
function runCase(string $name, \Closure $action): void
{
    global $passed, $failed;
    try {
        $action(new Scheduler());
        ++$passed;
        echo 'PASS ' . $name . PHP_EOL;
    } catch (Throwable $error) {
        ++$failed;
        fwrite(STDERR, 'FAIL ' . $name . ': ' . $error->getMessage() . PHP_EOL);
    }
}

runCase('successive Good reviews expand the wait and a lapse restarts learning', static function (Scheduler $scheduler): void {
    $progress = [];
    $steps = [
        ['2026-01-01 09:00:00', '2026-01-02 09:00:00', 1, 1],
        ['2026-01-02 09:00:00', '2026-01-08 09:00:00', 6, 2],
        ['2026-01-08 09:00:00', '2026-01-23 09:00:00', 15, 3],
    ];
    foreach ($steps as [$reviewed, $due, $interval, $reviews]) {
        $progress = $scheduler->schedule($progress, 'good', utc($reviewed));
        fields([
            'due_at' => $due, 'interval_days' => $interval, 'ease_factor' => 2.5,
            'review_state' => 'review', 'repetition_count' => $reviews,
            'total_reviews' => $reviews, 'last_rating' => 'good', 'last_reviewed_at' => $reviewed,
        ], $progress);
    }
    $lapse = $scheduler->schedule($progress, 'again', utc('2026-01-23 23:55:00'));
    fields([
        'due_at' => '2026-01-24 00:05:00', 'review_state' => 'relearning',
        'repetition_count' => 0, 'total_reviews' => 4, 'ease_factor' => 2.3,
        'last_rating' => 'again', 'last_reviewed_at' => '2026-01-23 23:55:00',
    ], $lapse);
    near(0.006944444444444444, $lapse['interval_days']);
    $recovered = $scheduler->schedule($lapse, 'good', utc('2026-01-24 00:05:00'));
    fields([
        'due_at' => '2026-01-25 00:05:00', 'interval_days' => 1,
        'review_state' => 'review', 'repetition_count' => 1, 'total_reviews' => 5,
        'ease_factor' => 2.3, 'last_rating' => 'good',
    ], $recovered);
});

runCase('early Hard and Easy steps preserve the minimum growth rule', static function (Scheduler $scheduler): void {
    foreach ([
        ['hard', 1, 3, '2026-01-02 12:00:00', '2026-01-05 12:00:00'],
        ['easy', 4, 8, '2026-01-05 12:00:00', '2026-01-13 12:00:00'],
    ] as [$rating, $firstDays, $secondDays, $firstDue, $secondDue]) {
        $first = $scheduler->schedule([], $rating, utc('2026-01-01 12:00:00'));
        fields(['interval_days' => $firstDays, 'due_at' => $firstDue, 'repetition_count' => 1], $first);
        $second = $scheduler->schedule($first, $rating, utc($firstDue));
        fields(['interval_days' => $secondDays, 'due_at' => $secondDue, 'repetition_count' => 2], $second);
    }
    $afterEasy = ['interval_days' => 4, 'ease_factor' => 2.65, 'repetition_count' => 1];
    fields(['interval_days' => 5, 'due_at' => '2026-01-10 12:00:00'],
        $scheduler->schedule($afterEasy, 'hard', utc('2026-01-05 12:00:00')));
});

runCase('mature ratings use pre-review ease before updating it', static function (Scheduler $scheduler): void {
    $progress = ['interval_days' => 10, 'ease_factor' => 2.5, 'repetition_count' => 2, 'total_reviews' => 7];
    foreach ([
        ['hard', 12, 2.35, '2026-06-13 12:00:00'],
        ['good', 25, 2.5, '2026-06-26 12:00:00'],
        ['easy', 33, 2.65, '2026-07-04 12:00:00'],
    ] as [$rating, $interval, $ease, $due]) {
        fields([
            'interval_days' => $interval, 'ease_factor' => $ease, 'due_at' => $due,
            'repetition_count' => 3, 'total_reviews' => 8, 'last_rating' => $rating,
        ], $scheduler->schedule($progress, $rating, utc('2026-06-01 12:00:00')));
    }
});

runCase('the same UTC, Korea and DST-zone instant schedules the same full UTC day', static function (Scheduler $scheduler): void {
    $instants = [
        utc('2026-03-08 06:30:00'),
        new DateTimeImmutable('2026-03-08 15:30:00', new DateTimeZone('Asia/Seoul')),
        new DateTimeImmutable('2026-03-08 01:30:00', new DateTimeZone('America/New_York')),
    ];
    foreach ($instants as $now) {
        $result = $scheduler->schedule([], 'good', $now);
        fields([
            'last_reviewed_at' => '2026-03-08 06:30:00',
            'due_at' => '2026-03-09 06:30:00', 'interval_days' => 1,
        ], $result);
        same(86400, utc($result['due_at'])->getTimestamp() - $now->getTimestamp(), 'Elapsed seconds across DST');
    }
});

runCase('Again crosses leap-day and year boundaries in exactly ten minutes', static function (Scheduler $scheduler): void {
    $progress = ['interval_days' => 300, 'ease_factor' => 2.5, 'repetition_count' => 8, 'total_reviews' => 20];
    foreach ([
        ['2024-02-28 23:55:00', '2024-02-29 00:05:00'],
        ['2026-12-31 23:55:00', '2027-01-01 00:05:00'],
    ] as [$reviewed, $due]) {
        $result = $scheduler->schedule($progress, 'again', utc($reviewed));
        fields([
            'due_at' => $due, 'review_state' => 'relearning', 'repetition_count' => 0,
            'total_reviews' => 21, 'ease_factor' => 2.3,
        ], $result);
        same(600, utc($result['due_at'])->getTimestamp() - utc($reviewed)->getTimestamp(), 'Relearning delay');
        near(0.006944444444444444, $result['interval_days']);
    }
});

runCase('defaults, numeric database values and negative legacy progress normalize predictably', static function (Scheduler $scheduler): void {
    $now = utc('2026-01-01 12:00:00');
    fields([
        'interval_days' => 1, 'ease_factor' => 2.5, 'repetition_count' => 1,
        'total_reviews' => 1, 'due_at' => '2026-01-02 12:00:00',
    ], $scheduler->schedule([], 'good', $now));
    fields([
        'interval_days' => 25, 'ease_factor' => 2.5, 'repetition_count' => 3,
        'total_reviews' => 10, 'due_at' => '2026-01-26 12:00:00',
    ], $scheduler->schedule([
        'interval_days' => '10', 'ease_factor' => '2.5', 'repetition_count' => '2', 'total_reviews' => '9',
    ], 'good', $now));
    fields([
        'interval_days' => 1, 'ease_factor' => 1.3, 'repetition_count' => 1,
        'total_reviews' => 1, 'due_at' => '2026-01-02 12:00:00',
    ], $scheduler->schedule([
        'interval_days' => -10, 'ease_factor' => -1, 'repetition_count' => -7, 'total_reviews' => -4,
    ], 'good', $now));
});

runCase('maximum waits and ease bounds hold when a review would exceed them', static function (Scheduler $scheduler): void {
    $now = utc('2026-01-01 12:00:00');
    $atMaximum = ['interval_days' => 36500, 'ease_factor' => 3.0, 'repetition_count' => 4];
    foreach (['hard', 'good', 'easy'] as $rating) {
        fields([
            'interval_days' => 36500, 'due_at' => '2125-12-08 12:00:00', 'repetition_count' => 5,
        ], $scheduler->schedule($atMaximum, $rating, $now));
    }
    fields(['interval_days' => 36500, 'ease_factor' => 3.0], $scheduler->schedule([
        'interval_days' => 99999, 'ease_factor' => 9, 'repetition_count' => 3,
    ], 'easy', $now));
    foreach (['again', 'hard'] as $rating) {
        fields(['ease_factor' => 1.3], $scheduler->schedule([
            'interval_days' => 10, 'ease_factor' => 1.3, 'repetition_count' => 2,
        ], $rating, $now));
    }
});

runCase('repeated scheduling is deterministic and preserves input progress and clock', static function (Scheduler $scheduler): void {
    $progress = [
        'interval_days' => 10, 'ease_factor' => 2.5, 'repetition_count' => 2,
        'total_reviews' => 7, 'custom' => ['note' => 'Keep the source record'],
    ];
    $before = $progress;
    $now = new DateTimeImmutable('2026-06-01 21:00:00', new DateTimeZone('Asia/Seoul'));
    $clockBefore = $now->format('Y-m-d H:i:s P e');
    $first = $scheduler->schedule($progress, 'easy', $now);
    $second = $scheduler->schedule($progress, 'easy', $now);
    fields(['due_at' => '2026-07-04 12:00:00', 'interval_days' => 33, 'total_reviews' => 8], $first);
    same($first, $second, 'Repeated result');
    same($before, $progress, 'Original progress');
    same($clockBefore, $now->format('Y-m-d H:i:s P e'), 'Original clock');
});

runCase('unknown ratings are rejected without changing progress', static function (Scheduler $scheduler): void {
    $progress = ['interval_days' => 10, 'ease_factor' => 2.5, 'repetition_count' => 2];
    $before = $progress;
    foreach (['', 'GOOD', 'normal', 'again '] as $rating) {
        try {
            $scheduler->schedule($progress, $rating, utc('2026-01-01 12:00:00'));
        } catch (InvalidArgumentException) {
            same($before, $progress, 'Rejected input');
            continue;
        }
        throw new RuntimeException('Accepted unsupported rating: ' . $rating);
    }
});

echo $passed . ' passed, ' . $failed . ' failed.' . PHP_EOL;
exit($failed === 0 ? 0 : 1);
