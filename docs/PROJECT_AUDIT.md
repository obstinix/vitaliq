# Project audit

Audit performed on 2026-10-10, before any Vitaliq code was written.

## Repository state

| Item | Finding |
| --- | --- |
| Remote | `https://github.com/obstinix/vitaliq` (public), default branch `main` |
| History | One commit, `022aff6` "Initial commit", authored by `Piyush Pandey <139326479+obstinix@users.noreply.github.com>`, committed by GitHub |
| Tracked files | `LICENSE` (MIT, © 2026 Piyush Pandey) |
| README, docs, source, tests, schema, `.gitignore`, `.env.example` | None present |
| Branch protection on `main` | Not inspected through the API at audit time. The authenticated account has `admin` and `push` permission on the repository (GitHub REST `GET /repos/obstinix/vitaliq`). |

The repository was effectively empty, so there was no existing work to preserve beyond `LICENSE`. That file is kept unchanged.

## GitHub identity

- Git Credential Manager already held a credential for `github.com`.
- `GET https://api.github.com/user` with that credential returned login `obstinix`, id `139326479`. The token scopes are `repo`, `workflow` and `gist`.
- The id matches the noreply address on the initial commit, so new commits use `obstinix <139326479+obstinix@users.noreply.github.com>`. This is set in the repository-local Git config only.
- No personal access token was written to any file, remote URL or command line.

## Host environment

| Item | Finding |
| --- | --- |
| OS | Windows 11 Home Single Language, build 10.0.26300, x64 |
| Shells | Windows PowerShell 5.1 and Git Bash |
| Privileges | Standard user, not elevated |
| Free disk | About 245 GB on `C:` |
| Package managers | `winget` present; Chocolatey and Scoop absent |
| PHP, MySQL/MariaDB, Apache, Composer, XAMPP | **Not installed** |
| Node.js / npm | Node 22.23.1, npm 10.9.8 |
| Python | 3.11 |
| Git | Present; GitHub CLI (`gh`) absent |
| Ports 80, 3306, 8000, 8080 | Free |
| Browser tooling | Desktop app's built-in browser pane, plus Chrome DevTools MCP |

## Environment actions taken

Software was installed per user, without elevation:

1. **PHP 8.4.25** (ZTS, x64) via `winget install PHP.PHP.8.4 --scope user`. A `php.ini` was derived from `php.ini-development`, with `curl`, `fileinfo`, `intl`, `mbstring`, `openssl`, `pdo_mysql`, `pdo_sqlite`, `sodium`, `sqlite3` and `zip` enabled.
2. **MariaDB 11.4.13 LTS**, from the official portable zip at `downloads.mariadb.org`. Its SHA-256 matched the value the MariaDB REST API publishes for that file. The MSI package was not used because it needs administrator rights.
   - The data directory is outside the repository and the server is bound to `127.0.0.1` only.
   - The root password was randomly generated and stored outside the repository.
   - `vitaliq` and `vitaliq_test` databases were created, with an application user granted privileges on those two schemas only.
3. **Composer 2.10.3**, installed with the official installer after its SHA-384 matched `composer.github.io/installer.sig`.

Details and exact commands are in [ENVIRONMENT_SETUP.md](ENVIRONMENT_SETUP.md).

## Decisions that follow from the audit

- With no existing code, framework or schema, a small hand-written PHP MVC structure is the simplest maintainable choice. It needs no runtime Composer dependencies, so it also runs on a stock XAMPP install.
- Composer is used only for PHPUnit, as a development dependency.
- Node is already present, so it is used for one consistency test: the client-side regex rules are checked against the shared case catalogue. Node is not needed to run the application.
