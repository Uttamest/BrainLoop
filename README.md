# BrainLoop

### Learn smarter. Remember longer.

**BrainLoop** is a web-based flashcard learning application built around active recall and spaced repetition. It helps learners organize study materials, review knowledge at the right time, and understand their learning progress through meaningful statistics.

Create private flashcard decks, practice at your own pace, review due cards, and build consistent study habits with a clean, responsive interface.

---

## Overview

BrainLoop combines a simple learning experience with a structured PHP and database architecture. It is designed to be lightweight, accessible, and straightforward to run locally.

**Key highlights**

- **Active recall** — Retrieve information from memory instead of simply rereading notes.
- **Spaced repetition** — Schedule reviews based on previous recall performance.
- **Progress tracking** — Monitor study activity, due cards, review history, and streaks.
- **Private learning spaces** — Keep each user's decks and flashcards isolated.
- **Accessible by design** — Use the application with keyboard navigation and without JavaScript.

## Features

### Flashcard management
- Register, log in, and manage your account.
- Create, edit, search, organize, and delete private decks.
- Add, update, and remove questions and answers.
- Import flashcards from UTF-8 CSV files.
- Export decks with spreadsheet formula protection.

### Learning and review
- Review cards that are due.
- Reveal answers only after submitting the review action.
- Rate recall using **Again**, **Hard**, **Good**, and **Easy**.
- Use practice mode to study cards that are not yet due.
- Keep practice activity separate from the normal review schedule.

### Progress and analytics
- Track total decks and flashcards.
- Monitor cards due for review.
- View review activity and recall ratings.
- Follow study streaks and learning activity.
- Review deck progress based on scheduling state.

### Accessibility and usability
- Responsive layouts for desktop and mobile.
- Server-rendered functionality without requiring JavaScript.
- Keyboard-friendly navigation and forms.
- Clear separation between sample content and personal learning data.

> The homepage illustration uses clearly identified sample data. Authenticated dashboards use the actual records belonging to the logged-in user. Optional sample decks do not contain fabricated review history.

## Technology Stack

| Technology | Purpose |
|---|---|
| PHP 8.2+ | Server-side application logic |
| MySQL / MariaDB | Primary relational database |
| SQLite | Optional lightweight database and testing |
| PDO | Database connectivity and prepared statements |
| HTML5 | Page structure and forms |
| CSS3 | Responsive interface and styling |
| GitHub Actions | Automated testing and continuous integration |

**No JavaScript, frontend frameworks, external APIs, Composer packages, or frontend build tools are required.**

## Requirements

| Component | Requirement |
|---|---|
| PHP | 8.2 or newer |
| PHP extensions | PDO and `pdo_mysql` |
| Database | MySQL 8.0.16+ or MariaDB 10.4+ |
| Database engine | InnoDB with `utf8mb4` |
| Optional database | SQLite with `pdo_sqlite` |
| Local environment | XAMPP or another PHP-compatible environment |
| Browser | Modern browser with CSS Grid and Flexbox support |

## Installation

**Checkout status (November 24, 2026):** this repository is missing the `public/` entry point/assets and `database/` schemas referenced below. A fresh clone cannot run the full application until those files are restored. The controller checks can run independently; see [verification and remaining blockers](docs/VERIFICATION.md).

### Run locally with XAMPP

The recommended local setup uses XAMPP on Windows.

**1. Clone the repository**

Open PowerShell:

```powershell
cd C:\xampp\htdocs
git clone <YOUR_REPOSITORY_URL> BrainLoop
```

Replace `<YOUR_REPOSITORY_URL>` with the HTTPS or SSH URL of your GitHub repository.

The project should have this structure:

```text
C:\xampp\htdocs\BrainLoop\
├── index.php
├── .htaccess
├── app/
└── public/
```

**2. Start the services**

Open the XAMPP Control Panel and start:

- Apache
- MySQL

**3. Open BrainLoop**

Visit:

```text
http://localhost/BrainLoop/
```

**4. Set up your database**

On a fresh installation, follow the database setup screen. Once setup is complete, select **Get Started** and register your account.

That's it! BrainLoop is ready for local use.

### Optional PHP development server

You can also run BrainLoop using PHP's built-in development server.

From the project root:

```powershell
php -S localhost:8000 -t public public/router.php
```

Then open:

```text
http://localhost:8000
```

Keep your database service running. Stop the development server with `Ctrl+C` when finished.

> The PHP built-in server is intended for local development, not production hosting.

### Database configuration

BrainLoop uses MySQL by default. It supports environment variables and an optional private configuration file:

```text
app/config.local.php
```

The local configuration file is ignored by Git and should never be committed.

For manual configuration, create a database and a dedicated application database user. Store the actual credentials in your private configuration or provide them through environment variables.

The optional SQLite backend can be enabled explicitly:

```powershell
$env:BRAINLOOP_DB_DRIVER = "sqlite"
$env:BRAINLOOP_DB_PATH = "C:\private\brainloop\brainloop.sqlite"

php scripts/setup.php
php -S localhost:8000 -t public public/router.php
```

See [`docs/DEPLOYMENT.md`](docs/DEPLOYMENT.md) for database setup, configuration, and deployment instructions.

## How It Works

BrainLoop uses a simplified **SM-2-inspired spaced repetition algorithm** to schedule flashcard reviews. It is not an official implementation of SM-2.

The learning process is straightforward:

1. Create a deck and add your flashcards.
2. Open your due review session.
3. Try to recall the answer before revealing it.
4. Select a recall rating.
5. Let BrainLoop schedule the next review.

| Rating | Action |
|---|---|
| Again | Schedule a short relearning interval |
| Hard | Record a difficult successful recall |
| Good | Record a normal successful recall |
| Easy | Record an easy successful recall |

New cards are immediately due. Due-review sessions exclude future-due cards and select up to 200 cards at a time.

Practice mode allows learners to revisit non-due cards without changing their existing scheduling fields.

For the precise scheduling formulas, edge cases, and examples, see [`docs/SCHEDULING.md`](docs/SCHEDULING.md).

## Project Architecture

BrainLoop follows a structured application architecture that separates request handling, database operations, business logic, and presentation.

```text
BrainLoop/
├── index.php
├── .htaccess
├── app/
│   ├── bootstrap.php
│   ├── config.php
│   ├── core/
│   ├── controllers/
│   ├── models/
│   ├── services/
│   └── views/
├── public/
│   ├── index.php
│   ├── router.php
│   └── assets/
├── database/
├── storage/
├── scripts/
├── tests/
├── docs/
├── .github/
│   └── workflows/
│       └── ci.yml
├── .env.example
├── CONTRIBUTING.md
├── SECURITY.md
└── LICENSE
```

**Architecture principles**

- Controllers handle requests and coordinate application actions.
- Models manage database operations and enforce record ownership.
- Services contain reusable business logic, including review scheduling.
- Views render the user interface independently of database access.
- Database transactions protect related data operations.

The `Store` model applies ownership checks, while the scheduling service operates independently of HTTP and database dependencies.

## Testing and Quality Assurance

Run the checked-in controller regression suite with PHP 8.2 or newer:

```powershell
php tests/controllers.php
```

It checks search normalization, deck lookup during study, and CSV parsing through the real controllers with synthetic store/request doubles. It needs no database, Composer packages, or application server. These are controller-level checks, not full HTTP or database tests.

The `PHP checks` GitHub Actions workflow runs PHP syntax checks and this controller suite on PHP 8.2, 8.3, and 8.4. The previously documented `tests/run.php` and `tests/http.php` runners are not present in this checkout, so SQLite/MySQL, real uploads, and root/subdirectory HTTP coverage remain unverified. See [verification evidence](docs/VERIFICATION.md) for what was actually checked and what still needs restoring.

## Security

Security is an important part of BrainLoop's design.

Implemented safeguards include:

- Password hashing.
- Login throttling.
- Session regeneration.
- HttpOnly and SameSite session cookies.
- CSRF protection.
- PDO prepared statements.
- Output escaping.
- Database ownership checks.
- POST-only mutation requests.
- Duplicate review prevention.

For responsible vulnerability reporting and additional security information, see [`SECURITY.md`](SECURITY.md).

**Important:** The application does not currently simulate email delivery, email verification, or password-reset emails.

## Documentation

Additional project documentation is available in the `docs/` directory.

| Document | Description |
|---|---|
| [Scheduling](docs/SCHEDULING.md) | Review algorithm, intervals, and scheduling behavior |
| [Deployment](docs/DEPLOYMENT.md) | XAMPP, Apache, database, and production deployment |
| [Testing](docs/TESTING.md) | Automated tests and manual quality checks |
| [Verification](docs/VERIFICATION.md) | Recorded verification results |
| [Contributing](CONTRIBUTING.md) | Contribution guidelines |
| [Security](SECURITY.md) | Security practices and vulnerability reporting |

Interface screenshots are available in [`docs/screenshots/`](docs/screenshots/).

## Deployment

BrainLoop requires PHP-capable hosting. **GitHub Pages cannot execute PHP**, so publishing this repository to GitHub does not make the application publicly runnable.

For production deployment:

- Use a maintained PHP security release.
- Enable HTTPS.
- Configure the document root to point to `public/`.
- Keep private application files outside the public web root.
- Use a dedicated database account with limited privileges.
- Disable public PHP error display and configure server-side logging.
- Maintain database backups and apply security updates.

See [`docs/DEPLOYMENT.md`](docs/DEPLOYMENT.md) for the full deployment guide.

## Project Team

| Name | Role |
|---|---|
| **SHRESTHA UTTAM** | Team Leader |
| **THAPA MANISHA** | Team Member |
| **B K SURAJ** | Team Member |
| **KHAND THAKURI SHRADDHA** | Team Member |

**Our mission:** To make studying more structured, engaging, and consistent through accessible learning tools.

## Contributing

Contributions, suggestions, and bug reports are welcome.

Please read [`CONTRIBUTING.md`](CONTRIBUTING.md) before submitting changes. For security-related concerns, follow the responsible reporting guidance in [`SECURITY.md`](SECURITY.md).

## License

BrainLoop is released under the [MIT License](LICENSE).

---

<p align="center">
  <strong>BrainLoop — Learn it. Remember it. Repeat.</strong>
</p>
