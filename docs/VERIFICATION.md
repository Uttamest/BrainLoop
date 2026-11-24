# Verification

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

The previously documented `scripts/setup.php`, `tests/run.php`, and `tests/http.php` are also absent. The scheduling/deployment/testing guides and screenshots linked elsewhere in README are not present in this checkout. The new workflow provides syntax/controller checks only; it does not replace SQLite/MySQL integration tests, HTTP tests, or root/subdirectory deployment verification.

Next useful work is to restore those project files, run the real installation and core study/search/import journeys with isolated test records, then expand verification from observed results. Do not record those checks as passed until they have actually run.
