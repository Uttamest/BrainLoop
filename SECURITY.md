# Security policy

## Reporting a vulnerability

Do not publish account data, password hashes, database files, session tokens, CSRF tokens, or working exploit details in a public issue. Contact the repository maintainers through a private channel provided by the actual repository owner. If GitHub private vulnerability reporting is enabled for the eventual repository, use its **Security → Report a vulnerability** action. This project does not invent a security email address.

Include the affected route/component, a minimal reproduction using accounts and data you own, expected behavior, observed behavior, and the relevant commit/runtime version. Give maintainers a reasonable opportunity to investigate before public disclosure.

## Safeguards

- Password hashing and verification through PHP's password API.
- Prepared PDO statements and server-side ownership checks for private decks/cards.
- CSRF validation on state-changing requests, POST mutation, and safe local redirects.
- Session ID regeneration on login; HttpOnly, SameSite cookies; Secure cookies with HTTPS.
- Login throttling keyed to normalized email and source IP.
- Output escaping, UTF-8 validation, bounded form fields, and CSV size/row validation.
- Database transactions and unique review submission keys to avoid duplicate credit.
- A scoped MySQL/MariaDB database identity; optional SQLite storage outside `public/`; response security headers including a script-disallowing Content Security Policy.

These safeguards do not replace host hardening, security patching, protected backups, access controls, or deployment review. Use a maintained PHP release and HTTPS. No independent penetration test or formal WCAG audit is claimed.

## Data and deployment

Keep `storage/`, `.env`, ignored `app/config.local.php`, database credentials, SQL dumps, session files, logs, local tooling, and backup databases out of Git and public HTTP access. A dedicated host should point its document root to `public/`. The XAMPP drop-in `/BrainLoop/` layout instead requires the supplied root `.htaccess` to block private paths; never remove those rules or enable directory listings. Restrict the normal database identity to the application's own database; the local XAMPP provisioning helper uses an administrator only during setup. First-run browser setup is local-only and must not be exposed as an open remote administration endpoint. The built-in PHP web server is for development. A running deployment requires PHP-capable hosting; GitHub Pages does not execute PHP.

Email delivery, email verification, and emailed password recovery are not implemented or simulated. There are no default accounts or fixed sample passwords.
