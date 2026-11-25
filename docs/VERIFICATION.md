# Verification

## Runnable web source and HTTP checks — November 26, 2026 (Asia/Seoul)

Restored the public front controller/router, existing required CSS/favicon, empty MySQL schema, setup/seed commands and HTTP test harness from the team's supplied source. The router now calls the current instance-based DeckController, including exports. The newer installer controller needed its leading blank byte removed for PHP strict-types parsing. The installation page uses the shared layout's form-level alert; its redundant page-specific alert branch is removed.

Local XAMPP PHP 8.2.12 checks passed 15 controller/view cases and 9 scheduler cases. The new view case uses a synthetic installer failure and verifies the 422 status, exactly one shared accessible error, retained ordinary fields and absence of the submitted administrator password. All 55 PHP files passed syntax checking. `git diff --check` passed.

The real HTTP suites passed 87 checks at hostname root and 90 through `/BrainLoop/` on MariaDB 10.4.32. Testing used a newly initialized temporary data directory, a loopback port separate from the normal XAMPP instance, generated test databases/scoped users, isolated PHP sessions and synthetic accounts at `example.invalid`. Each runner verifies its selected connection and removes only the resources it created.

HTTP coverage includes public pages/assets, registration/login/logout, password/profile updates, owner checks, deck/card changes, search, due review/reveal/rating, duplicate submissions, practice schedule preservation, partial session results, real multipart CSV uploads, formula-safe CSV export, analytics, cascading deletes and idempotent optional sample content. Each mount scanned 23 rendered pages for script elements/event handlers and resolved 34 internal links/assets. The minimum page count reflects the two session/result redirects after password changes rotate the session. Restored assertions follow existing 422 account-validation responses and the current abandoned-session heading.

Chromium with JavaScript disabled passed 72 route/width combinations across 18 public/private routes at 375, 768, 1024 and 1440 px. Native keyboard registration, deck/card creation, search, answer reveal and Good-to-results flow passed with synthetic study records. Thirteen screenshots are local under ignored `tests/artifacts/browser-nov26/`. A fresh clean-copy setup POST using port 0 returned 422 with exactly one visible alert, retained the ordinary fields and created no configuration, storage or database. Its screenshot is `invalid-setup-375.png` at 375 px. This is browser verification, not a formal accessibility audit. The fixture used a clean source copy excluding environment-specific configuration and runtime data.

CI now runs syntax, controller/view, scheduler and both HTTP suites on PHP 8.2, 8.3 and 8.4 with MariaDB 10.11. These are workflow configuration and local results. Check the pull request for the separate CI outcome.

PR #2 was merged into its feature-branch base, and PR #1 was closed without merging into main. This increment therefore includes the earlier controller repair and scheduling tests needed by the restored application. The teammate's newer main changes are retained.

The older verification entries below describe the source available on those dates. The public files and MySQL schema listed there are now restored. The broader model runner, optional Apache-copy/provisioning tools, deployment/testing guides and screenshots remain follow-up work. SQLite has no restored schema or integration verification. A PHP development-server mount check does not establish real Apache deployment behavior.


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

## Remaining full-application verification work

The public entry point/assets, empty MySQL schema and HTTP harness were restored on November 26 as recorded above. The optional `scripts/setup-xampp.php`, `scripts/setup-apache.ps1`, `tests/run.php` and `tests/apache-copy.php` remain absent, along with the deployment/testing guides and historical screenshots linked elsewhere.

Next useful work is to restore and review the remaining model/Apache checks against current APIs, verify local installation and backup/restore end to end in disposable environments, and record only checks actually performed. The optional SQLite backend remains unverified because no schema has been restored.
