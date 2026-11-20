# Security Policy — BrainLoop

This document defines the vulnerability disclosure guidelines, built-in application safeguards, operational responsibilities, and deployment hardening requirements for the **BrainLoop** web application.

---

## 1. Reporting a Vulnerability

### Disclosure Rules & Private Channels
To protect users and active deployments, **never** disclose security vulnerabilities in public GitHub issues, pull requests, discussions, or public forums. Specifically, do not publish:
* User account details, email addresses, or password hashes
* Raw database files (`.sqlite`, `.sql` dumps) or configuration secrets
* Active session identifiers or CSRF tokens
* Weaponized or copy-pasteable working exploit payloads

Instead, report vulnerabilities privately:
1. **GitHub Private Vulnerability Reporting:** If enabled on the repository, navigate to the repository's **Security** tab and select **Report a vulnerability** to open a confidential advisory.
2. **Maintainer Contact:** If GitHub private reporting is unavailable, contact the repository maintainers directly through the private channel designated by the repository owner. *(Note: This project does not publish or invent a generic security email address.)*

### Required Report Contents
To help maintainers reproduce, triage, and patch the issue quickly, include the following in your report:
* **Affected Surface:** The exact HTTP route, endpoint, controller, or component involved (e.g., `/login`, deck import handler, review submission endpoint).
* **Environment & Version:** The relevant Git commit hash, release tag, PHP runtime version, database engine (MySQL/MariaDB or SQLite), and hosting setup (dedicated vhost vs. XAMPP subdirectory).
* **Minimal Reproduction Steps:** Step-by-step instructions using **only test accounts and data that you own**.
* **Expected vs. Observed Behavior:** A clear description of what the application should do versus what actually occurs, including relevant HTTP status codes, headers, or error messages.

### Coordinated Disclosure
Please allow maintainers a reasonable window to investigate, verify, and release a fix before any public discussion or disclosure.

---

## 2. Built-In Application Safeguards

BrainLoop implements defense-in-depth controls at the application layer across authentication, data access, request handling, and browser security:

### Authentication & Session Management
* **Password Storage:** All passwords are hashed and verified exclusively using PHP's native `password_hash()` and `password_verify()` APIs (using strong, modern hashing algorithms and automatic salting).
* **Session Hardening:** Session IDs are regenerated upon successful login (`session_regenerate_id(true)`) to prevent session fixation. Session cookies are configured with `HttpOnly` (blocking JavaScript access), `SameSite` attributes (mitigating cross-site request forgery), and the `Secure` flag whenever served over HTTPS.
* **Brute-Force Protection:** Login attempts are rate-limited and throttled using a composite key of the normalized email address and the client source IP address.

### Data Access & Integrity
* **SQL Injection Prevention:** All database queries use parameterized, prepared PDO statements—never raw string concatenation.
* **Authorization & Ownership Enforcement:** Server-side ownership checks verify that the authenticated user owns or has explicit permission to access or modify private flashcard decks and cards before executing any read or write operation.
* **Idempotent Review Credit:** Study review submissions are wrapped in database transactions and guarded by unique submission keys to prevent race conditions and duplicate progress/credit awards.

### Request, Input & Output Security
* **CSRF & Redirect Safety:** State-changing actions require `POST` requests validated against per-session CSRF tokens. Post-action redirects are strictly validated to allow safe, local paths only (preventing open-redirect attacks).
* **Input Validation & Sanitization:** Incoming form inputs are enforced for valid UTF-8 encoding and bounded maximum lengths. CSV deck/card imports undergo strict file size and row count validation before parsing.
* **XSS Mitigation:** Dynamic content rendered into HTML views is contextually escaped, paired with a strict **Content Security Policy (CSP)** header that disallows inline and unauthorized scripts, alongside standard defensive HTTP response headers.

### Database Privilege Separation
* **Scoped Database Identity:** In MySQL/MariaDB deployments, the runtime application connects using a dedicated, least-privilege database user restricted strictly to BrainLoop's own schema.
* **Isolated SQLite Option:** When configured to use SQLite, the database file is stored outside the web-accessible `public/` directory.

### Scope & Limitations
Application-level controls do not replace infrastructure security. Operators remain responsible for:
* Keeping the underlying OS, web server, and PHP runtime on actively maintained, patched versions
* Enforcing HTTPS/TLS in production
* Securing server access controls, firewalls, and encrypted backups

*(Note: BrainLoop makes no claim of having undergone a third-party penetration test or a formal WCAG accessibility audit.)*

---

## 3. Data Protection & Deployment Hardening

### Sensitive Files & Repository Hygiene
Never commit sensitive data or runtime artifacts to Git, and ensure none are reachable over HTTP:
* `storage/` directory (SQLite databases, session files, application logs, temporary uploads)
* `.env` files and local configuration overrides (`app/config.local.php`)
* Database credentials, `.sql` dumps, and backup archives
* Local development/provisioning scripts and tooling

### Hosting Architecture & Web Server Rules
* **Production / Dedicated Hosting (Recommended):** Configure your web server's `DocumentRoot` (Apache) or `root` (Nginx) to point directly to the **`public/`** directory so that `app/`, `storage/`, and configuration files sit entirely outside the web root.
* **XAMPP / Subdirectory Drop-In (`/BrainLoop/`):** If deploying inside a subdirectory where `public/` is not the web root, you **must** retain the supplied root `.htaccess` file. Never remove its access-denial rules or enable Apache directory listings (`Options +Indexes`), as doing so exposes private application code and storage files.
* **Development Server Warning:** PHP's built-in web server (`php -S`) is strictly for local development and must never be exposed to untrusted networks or used in production.
* **Runtime Requirement:** BrainLoop requires a PHP-capable web host with PDO support; static hosting platforms such as **GitHub Pages** cannot execute PHP and are unsupported.

### Provisioning, Setup & Account Boundaries
* **Database Least Privilege:** Restrict the normal application database user solely to BrainLoop's database (`SELECT`, `INSERT`, `UPDATE`, `DELETE`, and schema migrations as needed). If using the local XAMPP provisioning helper, note that elevated administrator credentials are used **only** during initial database creation and must not be configured as the application's runtime credentials.
* **First-Run Browser Setup:** The browser-based setup wizard is restricted to local access (`localhost` / `127.0.0.1`). Never modify or proxy it to act as an open remote administration endpoint.
* **No Outbound Email Features:** BrainLoop neither implements nor simulates email delivery, email address verification, or email-based password recovery. Operators should not configure mock mailers expecting automated user password resets.
* **No Default Credentials:** The application ships with **zero** default user accounts, administrative backdoors, or hardcoded sample passwords. All accounts must be explicitly created during setup or normal registration.
