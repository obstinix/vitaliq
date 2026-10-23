# Environment setup

## Environment verified for this project

| Item | Version or detail | How it was obtained |
| --- | --- | --- |
| OS | Windows 11 Home Single Language 10.0.26300, x64, standard (non-admin) user | Already present |
| PHP | 8.4.27 ZTS x64 (upgraded from 8.4.25 on 23 Oct 2026 for CVE-2026-109618/109619) | Official `php-8.4.27-Win32-vs17-x64.zip` from windows.php.net, SHA-256 verified, unzipped to `C:\Users\Piyush\devtools\php-8.4.27` and put first on the user `PATH`. 8.4.25 was originally installed with `winget --scope user`. |
| PHP extensions enabled | curl, fileinfo, intl, mbstring, openssl, pdo_mysql, pdo_sqlite, sodium, sqlite3, zip (plus built-ins: json, ctype, filter, session) | `php.ini` derived from `php.ini-development`. Required by the app: pdo_mysql, mbstring, openssl. |
| Database | MariaDB 11.4.13 LTS | Official portable zip from downloads.mariadb.org; SHA-256 matched the value published by the MariaDB REST API. Initialised with `mariadb-install-db` and bound to 127.0.0.1. |
| Composer | 2.10.3 | Official installer, SHA-384 verified against composer.github.io/installer.sig |
| PHPUnit | 12.5.38 | `composer require --dev phpunit/phpunit:^12` (pinned in `composer.lock`) |
| Node.js / npm | 22.23.1 / 10.9.8 | Already present; used only for `tests/js` and to rebuild the D3 subset |
| Git | Present; Git Credential Manager for GitHub | Already present |
| Not installed | Apache/XAMPP, GitHub CLI | Not needed; the PHP built-in server was used for development |

## Install on a new machine

### Windows, no administrator rights

Install a **patched** release: 8.2.35, 8.3.36, 8.4.27 or 8.5.12 or later. Older releases are vulnerable to CVE-2026-109618/109619. Use winget if it offers a patched version (`winget install --id PHP.PHP.8.4 --scope user`), otherwise the official zip from <https://windows.php.net/download/>, checking it against `sha256sum.txt`. Afterwards confirm with:

```bash
php bin/check-runtime.php
```

1. Copy `php.ini-development` to `php.ini` in the PHP folder.
2. Set `extension_dir` to the absolute path of its `ext` folder.
3. Uncomment `extension=pdo_mysql`, `extension=mbstring`, `extension=openssl` and `extension=intl`.
4. Download the MariaDB "ZIP file" for Windows x64 from <https://mariadb.org/download/>, check its SHA-256, and unzip it.
5. Initialise a data directory with a root password of your choice, entered at the prompt or from a password manager:

```powershell
mariadb-install-db.exe --datadir=C:\path\to\mariadb-data --password=<root-password>
```

6. Add `bind-address=127.0.0.1` under `[mysqld]` in the generated `my.ini`.
7. Start the server:

```powershell
mariadbd.exe --defaults-file=C:\path\to\mariadb-data\my.ini --console
```

### XAMPP

- XAMPP already provides PHP, Apache and MariaDB. Point an Apache alias or virtual host at `vitaliq/public`.
- Alternatively, place the repository under `htdocs/vitaliq`. The root `.htaccess` sends requests into `public/` and denies `src/`, `.env` and the other private folders. Set `APP_URL=http://localhost/vitaliq` so links include the base path.

> **Not verified here.** Apache/XAMPP was not installed in the verification environment, so the `.htaccess` rules and the sub-folder base path have been reviewed but not executed. Everything else in this document was run on the machine described above.

### Linux and macOS

Install PHP 8.2+ with pdo_mysql and mbstring, plus MariaDB or MySQL, from the system package manager. Everything else is the same.

## Configure

1. Copy the example configuration and generate a key:

```bash
cp .env.example .env
```

```bash
php bin/generate-key.php
```

2. In `.env`, set `APP_KEY` and the `DB_*` values. `APP_DEBUG=true` is fine locally, never in production.

3. Create the databases and a least-privilege user. Run this as the database root user:

```sql
CREATE DATABASE vitaliq CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE vitaliq_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'vitaliq'@'127.0.0.1' IDENTIFIED BY '<app-password>';
CREATE USER 'vitaliq'@'localhost' IDENTIFIED BY '<app-password>';
GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, ALTER, DROP, INDEX, REFERENCES, CREATE TEMPORARY TABLES, LOCK TABLES
  ON vitaliq.* TO 'vitaliq'@'127.0.0.1', 'vitaliq'@'localhost';
GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, ALTER, DROP, INDEX, REFERENCES, CREATE TEMPORARY TABLES, LOCK TABLES
  ON vitaliq_test.* TO 'vitaliq'@'127.0.0.1', 'vitaliq'@'localhost';
```

`.env` is ignored by Git. Keep the root password out of the project, for example in a client option file used with `--defaults-extra-file`.

## Migrate, seed, run

```bash
php bin/migrate.php
```

```bash
php bin/seed.php
```

```bash
php -S 127.0.0.1:8000 -t public public/router.php
```

- `bin/seed.php` prints random demo sign-ins once; store them privately. To use your own account instead, run `php bin/create-user.php --role=admin --name="…" --email=…`.
- The built-in server binds to localhost only. `public/router.php` serves files under `public/assets` and sends everything else to `index.php`.

## Tests

```bash
composer install
```

```bash
vendor/bin/phpunit
```

```bash
node --test tests/js/rules.test.mjs
```

```bash
php bin/lint.php
```

```bash
php bin/qa.php run
```

- `vendor/bin/phpunit` uses the `vitaliq_test` database and resets its tables.
- The Node test needs `php` on `PATH`, or `PHP_BINARY` set to the PHP executable.

## Troubleshooting

| Symptom | Cause and fix |
| --- | --- |
| "Vitaliq is not configured: set APP_KEY" | `APP_KEY` is missing or shorter than 32 characters. Run `php bin/generate-key.php`. |
| Every page that uses data shows "temporarily unavailable" (503) | The database is not reachable or the credentials are wrong. Check the server is running and the `DB_*` values; `storage/logs/app-*.log` records the request ID without the password. |
| `could not find driver` | `pdo_mysql` is not enabled in the `php.ini` the CLI uses. Check with `php --ini` and `php -m`. |
| Extensions fail to load on Windows | `extension_dir` is relative. Use an absolute path with forward slashes. |
| PHPUnit exits with "TEST_DB_NAME must end in _test" | This is a safety guard. Point `TEST_DB_NAME` at a dedicated test database. |
| Integration tests are skipped | The test database is unreachable. Check the user's grants on `vitaliq_test`. |
| Styles or scripts blocked | The CSP allows only same-origin files. Serve from `public/` as the web root and do not add inline scripts. |
| Password-reset email never arrives | No mail transport is configured. With `MAIL_DRIVER=outbox` the message is saved in `storage/mail/`. |

## Outstanding external prerequisites

- An SMTP or SMS provider, if real message delivery is wanted. No transport is implemented.
- HTTPS and a production web server for any deployment. Session cookies become `Secure` automatically over HTTPS.
