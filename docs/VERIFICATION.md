# Verification

## Installer controller checks — November 27, 2026 (Asia/Seoul)

The installer controller was refactored in `f8f4ac1`. This increment adds independent regressions on top of main `211569d`, preserving that controller and the updated team license. PR #3 was closed without merging, so its runtime restoration and other checks are absent from this branch.

Run the available suite with PHP 8.2 or newer.

```sh
php tests/install-controller.php
```

Local XAMPP PHP 8.2.12 passed all 11 cases. They cover a fresh local form, an absent request method, local-only request rejection, normalized retained fields, unchanged password bytes, malformed/missing inputs, supported and fallback error statuses, successful registration redirect, unexpected-error propagation, and the complete shared layout's single escaped alert and empty password field.

The tests execute the real `InstallController`, shared input helpers, installation view and layout. A synthetic `LocalInstaller` records calls and returns configured outcomes. Render/redirect/abort signals are captured so no provisioning service, application bootstrap, private configuration, database, session files or browser server is opened. The full-view case uses the actual shared renderer with an explicit anonymous user and synthetic in-memory session data.

All seven installer/test dependency syntax checks passed, and `git diff --check` passed. The `Installer checks` workflow runs these same scoped checks on PHP 8.2, 8.3 and 8.4. CI outcomes are recorded separately on the pull request. Production PHP and presentation files are unchanged by this increment.

The [first CI matrix](https://github.com/Uttamest/BrainLoop/actions/runs/45855225114) passed PHP 8.2 and exposed a runner-output issue on PHP 8.3/8.4. Earlier PASS messages had already sent output before the real renderer set its response code. The runner now prints its report after all cases and also asserts the rendered 422 status. All 11 cases pass locally with output buffering disabled. The follow-up commit receives a fresh matrix run.

## Remaining application checks

A separate syntax scan of 44 existing public-source PHP files found one existing failure in `app/controllers/DeckController.php`. The current main source also lacks the `public/` entry point/assets, database schema and broader `tests/run.php`/`tests/http.php` runners referenced in README. Full application syntax, HTTP/database integration and browser flows therefore remain blocked. The controller suite does not establish actual database provisioning, request middleware, sessions, routing or accessibility conformance.

The earlier runtime restoration remains available in closed PR #3 for maintainer review. A future runtime change can restore a runnable main and verify those flows with disposable data. This increment records the available installer evidence without repeating that earlier proposal.
