# BrainLoop

**Learn smarter. Remember longer.**

BrainLoop is a digital flashcard learning application designed to make active recall and spaced repetition simple, organized, and effective. Students create private decks, write questions and answers, review cards when they become due, and follow their progress through real review history.

Built with **HTML5, CSS3, PHP 8.2+, and MySQL/MariaDB through PDO**, ready for local XAMPP hosting. The application and automated tests use MySQL. No JavaScript, frontend frameworks, external APIs, Composer packages, or build step are required.

## What you can do

- Register, log in, log out, edit your profile, and change your password.
- Create, organize, search, edit, and delete private decks and flashcards.
- Review due cards with server-rendered answer reveal and four recall ratings.
- Use intentional practice mode without changing your due schedule.
- Follow real deck totals, due cards, review activity, recall ratings, and study streaks.
- Import UTF-8 CSV flashcards and export your own decks with spreadsheet formula protection.
- Use the application with JavaScript disabled, on desktop or mobile, and with keyboard navigation.
- Read complete public Features, How It Works, About, Privacy, and Terms pages.

The homepage product illustration uses **clearly labeled sample data**. Your authenticated dashboard only uses your own database records. Optional sample decks contain no fabricated review history.

## Requirements

| Component | Requirement |
| --- | --- |
| PHP | 8.2 or newer; use a maintained security release for deployment |
| Extensions | PDO and `pdo_mysql`; DOM for the automated rendered-link tests |
| Database | MySQL 8.0.16+ or MariaDB 10.4+; InnoDB with utf8mb4 |
| Browser | Modern browser with CSS Grid and Flexbox; JavaScript unnecessary |
| Server | PHP development server locally; PHP-capable Apache/Nginx hosting in production |

No institution, course, instructor, or academic affiliation is implied.

## Windows quick start

For a fresh computer, extract the supplied `BrainLoop-XAMPP.zip` and use the **BrainLoop** folder inside it. The clean package excludes private database credentials and runtime data, so setup can create settings for that computer. An already configured folder reuses its existing `app/config.local.php`; copying that folder alone does not copy the MySQL database. Use the backup/restore instructions below when transferring existing study data.

1. Copy the complete **BrainLoop** folder directly into `C:\xampp\htdocs\BrainLoop`. Its root must contain `index.php`, `.htaccess`, `app`, and `public`; do not create a second nested BrainLoop folder.
2. Start **Apache** and **MySQL** in the XAMPP Control Panel. XAMPP's MySQL button may run MariaDB, which is supported.
3. Open [http://localhost/BrainLoop/](http://localhost/BrainLoop/) or [http://localhost/BrainLoop/index.php](http://localhost/BrainLoop/index.php).
4. On a fresh installation, open the **Set up BrainLoop** screen. Leave the host as `127.0.0.1`, use port `3306` unless you changed XAMPP's MySQL port, and choose a new database name such as `brainloop`.
5. Enter your local MySQL administrator username. An unchanged local XAMPP installation commonly uses `root` with an empty password; leave the password blank only if that is the actual setting on your computer. If you changed it, enter that password.
6. Submit setup. BrainLoop creates its six tables and a dedicated database account with a random password. Only that application's credentials are saved privately; the administrator password is not saved.
7. Select **Get Started**, register your own BrainLoop account, create a deck, add flashcards, and begin a due review.

The root `index.php` is the application entry point for this XAMPP layout. PHP must run through Apache; opening `index.php` as a file in Explorer does not execute the application. Navigation, forms, assets, redirects, and session cookies automatically retain the `/BrainLoop` application path.

Private database connection settings are kept in the ignored `app/config.local.php`. If an existing folder or private configuration is already present, preserve it when updating source files; do not replace user credentials or database data. Changing the source location does not copy or clear MySQL records.

### Optional command-line setup

The browser setup is available only for a fresh local installation. The equivalent CLI setup creates the `brainloop` database and a dedicated database user with a random password, then writes private configuration without printing the password. It does not create a default BrainLoop login account. In PowerShell:

```powershell
cd "C:\xampp\htdocs\BrainLoop"
$env:Path = "C:\xampp\php;" + $env:Path
php -v
php --ini
php -m
php scripts/setup-xampp.php
php scripts/setup.php
```

Keep Apache/MySQL running while using [http://localhost/BrainLoop/](http://localhost/BrainLoop/). No virtual host, hosts-file edit, move into `public/`, or separate development server is required for this layout.

On the original development machine, PHP is available at `C:\xampp\php\php.exe`. If `php` is not recognized, this sets PATH for the current PowerShell window:

```powershell
$env:Path = "C:\xampp\php;" + $env:Path
php -v
```

Alternatively, invoke the executable directly:

```powershell
& "C:\xampp\php\php.exe" scripts/setup.php
```

For the optional PHP development-server alternative, run from either the original Desktop source folder or the copied XAMPP folder, leave MySQL running, and use:

```powershell
& "C:\xampp\php\php.exe" -S localhost:8000 -t public public/router.php
```

Open [http://localhost:8000](http://localhost:8000) for that alternative and stop it with **Ctrl+C**. The PHP built-in server is for development, not production. Stop Apache/MySQL through the XAMPP Control Panel when they are no longer needed. See [docs/DEPLOYMENT.md](docs/DEPLOYMENT.md) for hosting details.

`scripts/setup-xampp.php` uses the local XAMPP administrator connection (`root` with an empty password by default). If your local database administrator has different credentials, supply `BRAINLOOP_ADMIN_DB_USER` and `BRAINLOOP_ADMIN_DB_PASSWORD` privately before running it. It preserves an existing local configuration instead of replacing credentials. The normal application uses only the newly scoped database identity, not the administrator.

The optional `scripts/setup-apache.ps1` is only for a separate [http://brainloop.localhost](http://brainloop.localhost) virtual host pointing to `public/`; the normal `/BrainLoop/` installation does not need it. It defaults to `C:\xampp` (override with `-XamppPath`), backs up the virtual-host configuration, validates changes, and rolls back if validation fails. See [docs/DEPLOYMENT.md](docs/DEPLOYMENT.md).

### MySQL connection settings

The base configuration uses MySQL at `127.0.0.1:3306` and database `brainloop`. The provisioning script supplies a dedicated generated user in private configuration. For manual setup instead, create your own scoped database user in phpMyAdmin and store its actual password in an ignored `app/config.local.php` that returns an array:

```php
<?php
return [
    'database' => [
        'driver' => 'mysql',
        'host' => '127.0.0.1',
        'port' => 3306,
        'name' => 'brainloop',
        'user' => 'brainloop',
        'password' => 'YOUR_LOCAL_DATABASE_PASSWORD',
    ],
];
```

Replace the documented password value with your own credential; no fixed database password is committed. Alternatively, supply the `BRAINLOOP_DB_*` environment variables listed below. Environment variables override local configuration, which keeps tests isolated from normal application data. If the database has not been created yet, an identity with permission to create it can initialize it using `php scripts/setup.php --create-database`; use the scoped application identity for normal operation.

### Manual phpMyAdmin setup

Use this alternative when you want to create the database/account yourself:

1. Start Apache/MySQL and open [phpMyAdmin](http://localhost/phpmyadmin/).
2. Create a new database named `brainloop` with collation `utf8mb4_unicode_ci`. If that name already holds data, preserve it and choose a new database name rather than replacing tables.
3. Select that database, open **Import**, choose this project's `database/schema.mysql.sql`, and run the import. The file creates `users`, `decks`, `flashcards`, `card_progress`, `review_history`, and `login_attempts`.
4. Create a separate database account, for example `brainloop_app` at host `127.0.0.1`, with a strong password. Give it database-specific privileges on this database only: `SELECT`, `INSERT`, `UPDATE`, `DELETE`, `CREATE`, `INDEX`, and `REFERENCES`. Do not use the administrator as the application's account.
5. Create `app/config.local.php` using the example above with your actual database name, account, and password. This file is ignored by Git. Do not overwrite an existing private configuration without checking which data it uses.
6. Run `php scripts/diagnose.php`, then open [http://localhost/BrainLoop/](http://localhost/BrainLoop/) and register your learning account. If your installation uses another folder name, use that name in the URL.

Both setup methods use the same schema. Neither creates a default learning account or fabricated review history.

### Enabling PHP database extensions on Windows

Find the loaded CLI configuration with `php --ini`. If no `php.ini` exists, copy the PHP distribution's `php.ini-development` to `php.ini` next to `php.exe`. Set `extension_dir` to the distribution's `ext` folder, then enable these lines without a leading semicolon:

```ini
extension_dir = "ext"
extension=pdo_mysql
```

The PDO extension is normally compiled into PHP. Confirm the MySQL driver:

```powershell
php -r "var_export(PDO::getAvailableDrivers());"
php scripts/setup.php
```

The driver list must include `mysql`. Restart Apache and any running PHP development server after editing `php.ini`. XAMPP Apache and the CLI can load different configuration files, so check the configuration used by each if only one fails. Do not place `php.ini`, downloaded runtimes, or private configuration in `public/`.

## Troubleshooting local setup

Run the read-only diagnostic from the project folder:

```powershell
php scripts/diagnose.php
```

It reports PHP/PDO availability, database server version, UTC/utf8mb4 connection settings, and presence of the six required InnoDB tables. It does not print passwords, learning material, or user records, and does not change the server.

| Symptom | What to check |
| --- | --- |
| MySQL is stopped or the connection fails | Start **MySQL** in XAMPP. Check its log if it immediately stops. Confirm the configured host, database account/password, and port. |
| `could not find driver` or missing PDO MySQL | Enable `extension=pdo_mysql` in the PHP configuration used by Apache, restart Apache, and verify the CLI with `php --ini` / `php -m`. |
| Apache cannot start or port 80 is occupied | Check XAMPP's Apache log and the service using that port. If you intentionally configure Apache on 8080, open `http://localhost:8080/BrainLoop/`. Do not stop unrelated services without checking them. |
| MySQL uses a different port | Use that port in first-run setup or `app/config.local.php`; Apache's web port and MySQL's database port are separate. |
| HTTP 403 | Keep the project root `.htaccess` intact and enable Apache `mod_rewrite` / overrides. Open `/BrainLoop/`, not a private path such as `/BrainLoop/app/` or `/BrainLoop/public/`. Private paths should remain forbidden. |
| HTTP 404 or broken links/assets | Confirm `C:\xampp\htdocs\BrainLoop\index.php` exists and there is no extra nested folder. Use the actual folder name in the URL. The application detects its folder prefix automatically. |
| HTTP 500 | Read XAMPP Apache/PHP logs privately and run `scripts/diagnose.php`. Verify MySQL is running, credentials are valid, the six tables exist, and the supported database version is installed. Do not publish credentials or full private logs. |
| Setup appears again after copying | The installed copy needs its own existing private configuration. Preserve `app/config.local.php` when updating the same installation; a fresh source archive intentionally contains no real password. |

If MySQL logs report storage-engine/checksum corruption, preserve a backup and ask the database administrator to investigate. Application setup does not repair MySQL system tables, and deleting the MySQL data directory can destroy existing databases.

## Backup, restore, and update

Back up the MySQL database and the private connection configuration before updating an installed copy. Keep backups outside `htdocs`, the public web root, and Git. A source-folder copy is not a database backup.

For a local transactional dump, use XAMPP's installed dump tool and let `-p` prompt for the database password:

```powershell
$backupFolder = Join-Path $env:USERPROFILE "BrainLoopBackups"
New-Item -ItemType Directory -Path $backupFolder -Force | Out-Null
$backupFile = Join-Path $backupFolder ("brainloop-" + (Get-Date -Format "yyyyMMdd-HHmmss") + ".sql")
& "C:\xampp\mysql\bin\mysqldump.exe" -h 127.0.0.1 -P 3306 -u root -p --single-transaction --default-character-set=utf8mb4 --skip-triggers brainloop "--result-file=$backupFile"
if ($LASTEXITCODE -ne 0) { throw "Database backup failed; keep existing backups and inspect the server log." }
```

Use your actual database name, port, and authorized backup account. Do not put the password after `-p` or in shell history. The application has no triggers or stored procedures, so this command backs up its tables and data without adding unrelated server objects.

Test a restore by creating a **new** database such as `brainloop_restore` in phpMyAdmin and importing the dump into it. Point a separate test copy's private configuration at that database with a scoped account, then check login, deck/card ownership, review history, due dates, and analytics. Keep the current installation unchanged until the restore is verified.

When updating source, preserve the installed `app/config.local.php` and private storage files. Do not replace someone else's data with demo content. For production, use HTTPS, restrict the application account to its database, keep private paths inaccessible, and follow [docs/DEPLOYMENT.md](docs/DEPLOYMENT.md).

## Optional sample flashcards

Register through the website first, then run the following with the same database configuration and the email of that existing account:

```powershell
php scripts/seed-demo.php your-existing-account@example.com
```

This adds five clearly labeled **Sample** decks: Computer Science, Mathematics, Biology, English Vocabulary, and General Knowledge. It creates 15 factual flashcards and their immediately due schedules. It never creates an account, password, or invented reviews. Running it again skips sample decks with the same titles. You can edit or delete the sample decks normally.

## Project layout

```text
BrainLoop/
├── index.php                # XAMPP /BrainLoop/ entry point
├── .htaccess                # Subdirectory routing and private-path protection
├── public/                  # Assets and optional dedicated document root
│   ├── index.php            # Front controller and route dispatch
│   ├── router.php           # PHP development server router
│   ├── .htaccess            # Apache front-controller rewrites
│   └── assets/              # Local CSS and original SVG assets
├── app/
│   ├── bootstrap.php        # Autoloading, sessions, response safeguards
│   ├── config.php           # Environment-backed configuration
│   ├── core/               # Database connection and shared helpers
│   ├── controllers/        # Request validation and page actions
│   ├── models/             # Prepared SQL, ownership checks, analytics
│   ├── services/           # Deterministic review scheduling
│   └── views/              # Shared layout, partials, page templates
├── storage/                # Private local files; ignored by Git
├── database/               # MySQL schema definition
├── scripts/                # Setup and optional sample content
├── tests/                  # Dependency-free PHP test runners
├── docs/                   # Scheduling, deployment, manual QA
├── .github/workflows/ci.yml
├── .env.example            # Environment variable reference, not autoloaded
├── CONTRIBUTING.md
├── SECURITY.md
└── LICENSE
```

Data access is kept out of view templates. The `Store` model applies ownership predicates and database transactions; the scheduling service has no HTTP or database dependencies.

## How reviewing works

New cards are due immediately. Start a **due review**, think of the answer, select **Show Answer**, and rate your recall as **Again**, **Hard**, **Good**, or **Easy**. The answer is absent from the initial study HTML. PHP reveals it only after your form submission.

BrainLoop uses a **simplified SM-2-inspired algorithm**, not the official SM-2 implementation. Again schedules a ten-minute relearning step; successful ratings use explicit early steps and then grow the interval based on prior performance. The exact formulas, edge cases, and examples are in [docs/SCHEDULING.md](docs/SCHEDULING.md).

Due-only sessions exclude future-due cards and select at most 200 cards at a time. Each selected card is reviewed once per session. An Again card becomes eligible again after ten minutes in a later due session. Practice deliberately includes non-due cards and logs practice reviews while preserving every scheduling field.

All stored timestamps and displayed analytics use **UTC**. A study streak counts distinct UTC dates with saved review history, including deliberate practice. Multiple reviews on one day count as one streak day. The current streak can continue from yesterday until today ends; a missed whole day breaks it. Cards reviewed today counts distinct cards, while review totals count review events.

The dashboard's current review snapshot compares cards scheduled into the future by a scheduled review today with those plus cards still due now. These sets do not overlap; practice does not add to the scheduled count, and progress changes when more cards become due.

Deck progress is an operational indicator: a card is counted as established when it is in the `review` state with an interval of at least 21 days. It does not measure or guarantee a learner's memory. Deleting a card or deck also deletes its associated review history, so analytics recalculate from the remaining records.

## Tests and verification

Run the dependency-free scheduler, database, model, analytics, and security-helper tests:

```powershell
php tests/run.php
php tests/http.php
```

Tests use MySQL/MariaDB and require the server to be running. Supply a test administrator identity allowed to create/drop isolated databases and temporary accounts:

```powershell
$env:BRAINLOOP_TEST_DB_HOST = "127.0.0.1"
$env:BRAINLOOP_TEST_DB_PORT = "3306"
$env:BRAINLOOP_TEST_DB_USER = "root"
# Supply BRAINLOOP_TEST_DB_PASSWORD if that test identity has a password.
php tests/run.php
php tests/http.php
php tests/http.php --subdirectory
```

MySQL tests create randomly named `brainloop_test_...` databases and random scoped test users, then drop only the databases/users they created. They never use the configured `brainloop` application database. Test administrator credentials need permission to create/drop those databases and users; the normal application account does not. The HTTP suite launches/stops its own localhost PHP server and requires CLI `proc_open`, `allow_url_fopen`, and DOM. `--subdirectory` runs the same forms and study workflow through the root entry point mounted at `/BrainLoop/`, including checks that links and redirects retain that path. A nonzero exit code indicates failure.

To run only deterministic scheduling, input/output, and URL unit checks without connecting to a database:

```powershell
php tests/run.php --unit-only
```

This explicitly reports database integration as not requested. A passing unit-only run does not mean the database/HTTP suites passed. Current dated results and environment limitations are recorded in [docs/VERIFICATION.md](docs/VERIFICATION.md).

For an optional check against **real XAMPP Apache and MySQL**, start those services first and run:

```powershell
php tests/apache-copy.php
```

This creates a disposable fresh project copy in XAMPP `htdocs`, exercises browser-based database setup and the full study flow over HTTP, and removes its own copy/database/generated user afterward. It preserves existing installed data. The default XAMPP path is `C:\xampp`; override it with `BRAINLOOP_TEST_XAMPP_PATH` if needed. Local database administrator settings use `BRAINLOOP_TEST_DB_USER`, `BRAINLOOP_TEST_DB_PASSWORD`, and `BRAINLOOP_TEST_DB_PORT`. This optional test requires an already running local Apache/MySQL installation and is not part of GitHub CI.

Check all PHP source files with PowerShell:

```powershell
php -l index.php
if ($LASTEXITCODE -ne 0) { throw "Root entry point syntax check failed." }
Get-ChildItem app, public, scripts, tests -Recurse -Filter *.php | ForEach-Object {
    php -l $_.FullName
    if ($LASTEXITCODE -ne 0) { throw "PHP syntax check failed: $($_.FullName)" }
}
```

GitHub Actions verifies extensions, PHP syntax, and MySQL/MariaDB tests at both hostname root and a mounted `/BrainLoop/` path on PHP 8.2, 8.3, and 8.4 using an isolated MariaDB service. See [docs/TESTING.md](docs/TESTING.md) for functional and responsive QA and screenshot instructions. Only checks actually executed should be reported as passed.

Local results and their database/runtime scope are recorded in [docs/VERIFICATION.md](docs/VERIFICATION.md). Screenshots of the interface are available locally in `docs/screenshots/`.

## Configuration

BrainLoop reads actual process environment variables and optional ignored `app/config.local.php`. Environment values override local file values. `.env.example` is a reference template; a `.env` file is not automatically loaded.

| Variable | Purpose | Default |
| --- | --- | --- |
| `BRAINLOOP_DB_DRIVER` | Database backend; must be `mysql` | `mysql` |
| `BRAINLOOP_DB_HOST` | MySQL/MariaDB hostname | `127.0.0.1` |
| `BRAINLOOP_DB_PORT` | MySQL/MariaDB port | `3306` |
| `BRAINLOOP_DB_NAME` | Application database name | `brainloop` |
| `BRAINLOOP_DB_USER` | Database account | `brainloop` |
| `BRAINLOOP_DB_PASSWORD` | Database password | Empty unless supplied privately |
| `BRAINLOOP_ENV` | Environment identifier | `production` |
| `BRAINLOOP_HTTPS` | Set to `1` to require Secure session cookies behind trusted HTTPS termination | Detected from the server HTTPS setting |

Leave `BRAINLOOP_HTTPS` unset during ordinary local HTTP development. A Secure cookie cannot be used over plain HTTP. Do not trust unvalidated forwarded request headers to determine HTTPS.

MySQL data is managed by the database server rather than stored in the source folder. Legacy local data files from earlier project versions are preserved, but this version does not open them or automatically transfer them into MySQL.

## Publish source to GitHub

Create an empty repository in your own GitHub account first. Review the files to be committed; database files, credentials, logs, sessions, local tools, and environment files must stay excluded.

```powershell
cd "C:\Users\acer\OneDrive\Desktop\BrainLoop"
git init
git add .
git status
git commit -m "Build BrainLoop PHP flashcard application"
git branch -M main
```

Copy the actual remote URL from the repository you created, then use it in place of `YOUR_REPOSITORY_URL`:

```powershell
git remote add origin YOUR_REPOSITORY_URL
git push -u origin main
```

No repository URL is assumed, and no remote or push is created automatically. GitHub hosts the source and collaboration workflow. **GitHub Pages cannot execute PHP.** Running BrainLoop requires PHP-capable hosting; there is no claimed public deployment URL.

## Deployment and data protection

For XAMPP's drop-in `/BrainLoop/` layout, keep the supplied root `.htaccess` enabled: it routes application requests and blocks private application/configuration/storage paths. For a dedicated production host, prefer setting the document root directly to `public/` so private source is outside that web root. Grant the application database account access only to its own database. Enable HTTPS, set production PHP error logging without public error display, and apply current PHP security updates.

Use Apache with `mod_rewrite` and the supplied `public/.htaccess`, or configure Nginx to send application routes to `public/index.php`. See [docs/DEPLOYMENT.md](docs/DEPLOYMENT.md) for XAMPP Apache configuration, database privileges, backups, HTTPS termination, and portability limits.

Security safeguards include hashed passwords, login throttling, session regeneration, HttpOnly/SameSite cookies, CSRF tokens, prepared SQL, output escaping, ownership checks, POST-only mutation, and duplicate review prevention. See [SECURITY.md](SECURITY.md) for responsible reporting. The project does not simulate email delivery, email verification, or password-reset emails.

## Project documentation and class delivery

The planned project timeline runs from **15 September to 8 December 2026**, with class presentation and submission on **8 December**. These dates are a planned baseline, not a completed activity log.

- [Detailed project report (PDF)](docs/BrainLoop_Project_Report.pdf) and [editable report source](docs/PROJECT_REPORT.md): requirements, use cases, architecture, MySQL data dictionary, workflow models, security, testing, traceability, risks, installation, and presentation plan.
- [Planned timeline and milestones](docs/PROJECT_PLAN.md), including the final rehearsal week and presentation date.
- [Team contributions](docs/TEAM_CONTRIBUTIONS.md), based on the contribution areas confirmed by the team.
- [Lecture mapping and page references](docs/LECTURE_MAPPING.md), connecting the implementation and plan to all nine supplied lecture PDFs.
- [Dated verification record](docs/VERIFICATION.md) and [local MySQL recovery note](docs/MYSQL_RECOVERY_NOTE.md).
- [Dashboard and analytics controller checks](docs/PAGE_CONTROLLER_CHECKS.md): available scoped command, November 28 evidence and current full-application verification limits.

The PDF and Markdown files are opened from the project folder. Apache deliberately blocks `/docs/` requests to keep internal project documents outside the public website.

## Project team

| Name | Role | Student ID | Confirmed area |
| --- | --- | --- | --- |
| **SHRESTHA UTTAM** | Team Leader | **20244110** | Leadership across the project, backend integration, GitHub, and documentation |
| **THAPA MANISHA** | Team Member | **20244114** | CSS and visual design |
| **B K SURAJ** | Team Member | **20244108** | Frontend implementation |
| **KHAND THAKURI SHRADDHA** | Team Member | **20244122** | Testing |

Our mission: **To make studying more structured, engaging, and consistent through accessible learning tools.**

## License

Released under the [MIT License](LICENSE). Contributions are welcome under [CONTRIBUTING.md](CONTRIBUTING.md).
