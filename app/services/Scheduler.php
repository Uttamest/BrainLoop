<?php
declare(strict_types=1);

namespace App\Services;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

/** A deterministic, simplified SM-2-inspired scheduler; not the official SM-2 algorithm. */
final class Scheduler
{
    public const RATINGS = ['again', 'hard', 'good', 'easy'];
    public const MAX_INTERVAL_DAYS = 36500;

    public function schedule(array $progress, string $rating, ?DateTimeImmutable $now = null): array
    {
        if (!in_array($rating, self::RATINGS, true)) {
            throw new InvalidArgumentException('Unknown review rating.');
        }
        $now = ($now ?? new DateTimeImmutable('now', new DateTimeZone('UTC')))->setTimezone(new DateTimeZone('UTC'));
        $ease = min(3.0, max(1.3, (float) ($progress['ease_factor'] ?? 2.5)));
        $previous = min(self::MAX_INTERVAL_DAYS, max(0.0, (float) ($progress['interval_days'] ?? 0)));
        $repetitions = max(0, (int) ($progress['repetition_count'] ?? 0));

        if ($rating === 'again') {
            $interval = 10 / 1440;
            $due = $now->modify('+10 minutes');
            $ease = max(1.3, $ease - 0.2);
            $repetitions = 0;
            $state = 'relearning';
        } else {
            // Early successful steps are explicit. Later steps use the PRE-review ease factor.
            $first = ['hard' => 1, 'good' => 1, 'easy' => 4];
            $second = ['hard' => 3, 'good' => 6, 'easy' => 8];
            if ($repetitions === 0) {
                $interval = $first[$rating];
            } elseif ($repetitions === 1) {
                $interval = max($previous + 1, $second[$rating]);
            } else {
                $multiplier = match ($rating) {
                    'hard' => 1.2,
                    'good' => $ease,
                    'easy' => $ease * 1.3,
                };
                $interval = max($previous + 1, ceil($previous * $multiplier));
            }
            $interval = (int) min(self::MAX_INTERVAL_DAYS, max(1, ceil($interval)));
            $due = $now->modify('+' . $interval . ' days');
            $ease = match ($rating) {
                'hard' => max(1.3, $ease - 0.15),
                'easy' => min(3.0, $ease + 0.15),
                default => $ease,
            };
            ++$repetitions;
            $state = 'review';
        }

        return [
            'review_state' => $state,
            'due_at' => $due->format('Y-m-d H:i:s'),
            'interval_days' => $interval,
            'ease_factor' => round($ease, 2),
            'repetition_count' => $repetitions,
            'total_reviews' => max(0, (int) ($progress['total_reviews'] ?? 0)) + 1,
            'last_rating' => $rating,
            'last_reviewed_at' => $now->format('Y-m-d H:i:s'),
        ];
    }
}
