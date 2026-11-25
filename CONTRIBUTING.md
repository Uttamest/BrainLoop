# Contributing to BrainLoop

Thank you for helping improve a focused, accessible study tool.

1. Read `README.md`, initialize your own local database, and register your own development account.
2. Create a branch for one clear change. Keep unrelated formatting and private data out of the diff.
3. Preserve the stack: PHP 8.2+, PDO MySQL/MariaDB, HTML5, CSS3, native forms, and server rendering. Do not add JavaScript, frontend frameworks, alternative database backends, external APIs, or a package build step.
4. Keep SQL and ownership checks in the model, scheduling in its service, request handling in controllers, and presentation in reusable views.
5. Use prepared statements, escaped output, server-side validation, CSRF tokens, and owner checks on all private resources. Mutations must use POST.
6. Run `php tests/run.php`, `php tests/http.php`, `php tests/http.php --subdirectory`, and PHP syntax checks before opening a pull request. All database tests use isolated MySQL schemas and scoped test accounts. Add meaningful tests for business rules and security changes. Check keyboard usability and the 375, 768, 1024, and 1440px layouts when changing presentation.

Describe the concrete problem, resulting behavior, and checks actually run in your pull request. Include screenshots for visual changes using sample study material and no private user information. Do not claim unrun checks passed.

Never include database files or dumps, credentials, password hashes, session files, private configuration, or personal review history. Report potential vulnerabilities privately using `SECURITY.md`.

Contributions are licensed under the MIT License. Preserve the exact project team attribution and avoid inventing an institution, member biography, or academic affiliation.
