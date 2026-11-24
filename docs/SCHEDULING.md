# Review scheduling

This describes `app/services/Scheduler.php` as verified on November 25, 2026. BrainLoop uses a simplified SM-2-inspired policy; it is not an official SM-2 implementation or a promise of learning outcomes.

## What a rating changes

The scheduler takes the card's previous progress, one lowercase rating (`again`, `hard`, `good`, `easy`), and an optional review time. Times are converted to UTC. The returned progress records the rating and review timestamp and increments the scheduled-review count once. The input progress and supplied immutable clock are left unchanged.

| Rating | Scheduling result |
| --- | --- |
| Again | Due in exactly 10 minutes; state becomes `relearning`, successful repetitions reset to zero, and ease decreases by 0.20. |
| Hard | A successful review with a shorter interval; ease decreases by 0.15. |
| Good | A successful review with the normal interval; ease stays the same. |
| Easy | A successful review with a longer interval; ease increases by 0.15. |

Successful reviews set the state to `review` and increment the successful-repetition count. Ease stays between 1.30 and 3.00. An unsupported rating is rejected instead of producing progress.

## Interval rules

The repetition count is read **before** applying the current rating:

| Previous successful repetitions | Hard | Good | Easy |
| --- | --- | --- | --- |
| 0 | 1 day | 1 day | 4 days |
| 1 | At least 3 days | At least 6 days | At least 8 days |
| 2 or more | Previous interval × 1.2 | Previous interval × previous ease | Previous interval × previous ease × 1.3 |

From the second successful review onward, the interval grows by at least one day until the 36,500-day cap is reached. Successful intervals round up to whole days. Multipliers use the ease value **before** this review's adjustment. The cap takes precedence over growth. Negative previous intervals/repetition counts/review counts are treated as zero; missing progress starts with interval zero and ease 2.50.

For example, successive Good ratings at each due time yield intervals of 1, 6 and 15 days. On a mature card with a 10-day previous interval and ease 2.50, Hard/Good/Easy produce 12/25/33 days and ease 2.35/2.50/2.65 respectively. Again resets the next successful step to its first-review interval.

Due dates are calculated from the current review time, not from the previous due date. Because calculations occur in UTC, one successful-review day is 24 hours even across a daylight-saving change. Again can cross midnight, a year boundary or leap day while remaining exactly 600 seconds later.

## Integration and verification limits

`Store::review()` calls this service for scheduled reviews. Practice bypasses the scheduler and keeps the card's existing scheduling fields; verifying practice persistence requires model/database tests. Study sessions select up to 200 eligible cards and show each once; Again schedules a later review instead of immediately inserting another copy into the current queue.

Run the dependency-free service tests with:

```sh
php tests/scheduler.php
```

The tests use fixed synthetic progress and explicit clocks. They do not read learning records or connect to a database. Together with `php tests/controllers.php`, they run in the PHP 8.2/8.3/8.4 CI matrix. These checks do not establish full application behavior while the public entry point/assets and database schemas are missing; see [verification evidence](VERIFICATION.md).
