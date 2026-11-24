# Verification

## Review scheduling checks — November 25, 2026 (Asia/Seoul)

Added the missing [scheduling guide](SCHEDULING.md) and a dependency-free service suite. Nine cases verify Good learning/lapse recovery, early Hard/Easy steps and minimum growth, mature ratings using pre-review ease, UTC/Korea/DST-equivalent clocks, ten-minute relearning across leap/year boundaries, legacy/default numeric progress, interval/ease caps, repeatability/input preservation, and unsupported ratings.

Local results on XAMPP PHP 8.2.12: `php tests/scheduler.php` passed 9 cases; `php tests/controllers.php` passed 14 cases; all 47 PHP files passed syntax checking; `git diff --check` passed. The CI matrix now runs both suites on PHP 8.2, 8.3 and 8.4. CI results are separate from these local results.

The new service tests require no database, bootstrap or server and use fixed clocks and synthetic progress. Scheduler production behavior is unchanged. Practice persistence, SQL/HTTP integration and browser journeys remain unverified; the missing application files listed below still block full-app checks.

This increment branches from the controller repair in PR #1, and its pull request targets that branch until the earlier repair is merged. Its diff contains the scheduling checks/documentation and CI step; it does not repeat the controller repair.

## Controller integration repair — November 24, 2026 (Asia/Seoul)

The starting checkout at `d59c6e3` failed PHP syntax checking in `DeckController.php`: a malformed operator and a CSV parser cut off mid-function prevented the class from loading. The parser tail is restored from the earlier `847d2e3` implementation, adapted to the current instance validator and header variable. The current controller refactor is preserved.

Search now uses the public, shared `DeckController::query()` validator. Deck listing/detail use the same trimmed, UTF-8, 200-character query policy. Study's two deck lookups use the current instance `requireOwnedDeck()` API instead of the removed static method.

Local verification used XAMPP's PHP 8.2.12 CLI on Windows:

- All 46 checked-in PHP files passed `php -l`.
- `php tests/controllers.php`: 14 cases passed, 0 failed.
- `git diff --check` passed.

The controller suite covers search normalization and invalid inputs, Search/Deck controller store arguments, starting/rendering selected-deck and all-decks study sessions, rejection before study lookup for unavailable decks, and hiding answers before reveal. CSV checks cover two/three-column headers, UTF-8 BOM, quoted multiline fields, malformed content, no partial persistence calls, the 500/501-row boundary, and invalid/empty/oversized upload fixtures.

The suite uses the real controllers and global input validators, with synthetic store, render, redirect, session, and upload dependencies. Temporary CSV files are removed when their handles close. It does not connect to an application database or use personal learning records. It does not verify SQL authorization, real multipart uploads, session/CSRF middleware, or browser routing.

`.github/workflows/php-checks.yml` runs syntax and controller checks on PHP 8.2, 8.3, and 8.4, following [setup-php's documented matrix configuration](https://github.com/shivammathur/setup-php#matrix-setup). GitHub CI results are separate from these local results. No visual markup changed; no browser or accessibility audit is claimed.

## Files needed before full application verification

This checkout has no `public/` entry point, router or assets, and no `database/schema.mysql.sql` or `database/schema.sqlite.sql`. Root `index.php` requires the missing `public/index.php`; the database migration requires the missing schemas. These need to be restored from the team's complete project before the documented installation flow can run.

The previously documented `scripts/setup.php`, `tests/run.php`, and `tests/http.php` are also absent. The deployment/testing guides and screenshots linked elsewhere in README remain missing; the scheduling guide was added on November 25. Service/controller checks do not replace SQLite/MySQL integration tests, HTTP tests, or root/subdirectory deployment verification.

Next useful work is to restore those project files, run the real installation and core study/search/import journeys with isolated test records, then expand verification from observed results. Do not record those checks as passed until they have actually run.
