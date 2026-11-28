# Dashboard and analytics controller checks

## November 28, 2026 (Asia/Seoul)

The team refactored `PageController` in main `2c8485e`. This independent increment adds checks for the dashboard and analytics paths without changing production code or depending on the installer checks in open PR #4.

Run the suite with PHP 8.2 or newer and the DOM extension:

```sh
php -d output_buffering=0 tests/page-controller.php
```

All 13 cases passed locally on XAMPP PHP 8.2.12. They check:

- Both overview modes retain the signed-in owner's statistics, decks, daily counts, recall ratings and activity, with the correct page title. Each mode is tested with each of two signed-in synthetic owners who have different collection/history values.
- A rejection from the authentication collaborator stops both modes before obtaining a store or reading/rendering private data.
- Failures from each of the five data reads propagate unchanged, without rendering partial statistics as a successful overview.
- The real shared renderer, layout and two page views render populated and empty collections. Checks cover escaped owned deck text, absence of the other owner's deck/link, mounted `/BrainLoop/` links, the main/heading structure, zero statistics, empty guidance, no invented activity and no scripts. Empty daily/ratings fixtures preserve the actual store contract of 14 zero-count days and four zero-valued recall ratings.

To verify that these regressions detect owner drift, each of the five controller data reads was temporarily redirected to synthetic owner 29 instead of signed-in owner 17, one at a time. A separate mutation hard-coded the current user to owner 17. Every mutation made the suite fail. The exact original controller bytes were restored after each check, and the final suite passed again. No mutation or production change is committed.

All eight overview/test dependency syntax checks and `git diff --check` passed locally. The `Overview checks` workflow runs the same scoped lints and suite on PHP 8.2, 8.3 and 8.4 with DOM. CI outcomes are recorded separately on the pull request.

## Boundaries of this evidence

The tests execute the real overview controller and presentation files. Authentication, store access and the controller's render boundary are isolated collaborators; the captured result is then passed to the real shared renderer with an explicit synthetic user. No application bootstrap, private configuration, database, session files, browser server or production data is opened. The checks establish the controller's use of its authentication/owner inputs, not the correctness of SQL authorization, authentication middleware, sessions or HTTP routing.

Global search is outside this suite. Current main still calls the unavailable `DeckController::query()` API, and the existing `DeckController.php` syntax failure prevents that source from loading. Main also still lacks the public entry point/assets, database schema and broader `tests/run.php`/`tests/http.php` runners referenced in README. Full application syntax, HTTP/database integration, browser layouts and accessibility therefore remain unverified. Earlier runtime restoration PR #3 remains closed and unmerged; it is not repeated here.
