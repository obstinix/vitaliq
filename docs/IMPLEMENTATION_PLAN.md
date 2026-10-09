# Implementation plan

Written after the [project audit](PROJECT_AUDIT.md). The repository held only a licence, so the whole application is new.

## Architecture

- **Runtime:** PHP 8.4 (8.2+ supported) and MariaDB 11.4 or MySQL 8. Also runnable under XAMPP.
- **Structure:** a lightweight MVC with a front controller.
  - `public/index.php` builds a `Request` and passes it to `App::handle()`, which returns a `Response`.
  - Because every request goes through that path, the PHPUnit functional tests can drive real routes in-process.
- **Layers:**
  - `src/Core`: router, request/response, session, CSRF, view, database, env/config.
  - `src/Validation`: shared regex rules and the validator.
  - `src/Auth`: authentication and password reset.
  - `src/Services`: business logic and SQL.
  - `src/Controllers`: HTTP handlers.
  - `views/`: PHP templates.
  - `database/migrations`: SQL schema.
  - `bin/`: CLI tools for migrate, seed, user creation and QA runs.
- **No runtime Composer packages.** An `spl_autoload` loader in `src/bootstrap.php` handles classes. Composer installs PHPUnit only.
- **Front end:** server-rendered HTML, one CSS file built on design tokens, and plain ES modules. The only libraries are GSAP 3 (motion) and a 78 KB D3 subset (charts), and D3 loads only on pages that draw charts. The reasoning is in [TECH_STACK_DECISIONS.md](TECH_STACK_DECISIONS.md).

## Testing workflow states

| From | Allowed to | Who |
| --- | --- | --- |
| `submitted` | `assigned`, `cancelled`, `rejected` | Admin; the patient may cancel |
| `assigned` | `sample_collected`, `cancelled`, `rejected` | The assigned phlebotomist (collect) or an admin; the patient may cancel |
| `sample_collected` | `in_progress`, `rejected` | Admin (lab) |
| `in_progress` | `completed` | Admin, by recording a result |
| `completed` | `result_available` | Admin, by releasing the result |
| `result_available`, `cancelled`, `rejected` | None (terminal) | |

Reassigning a phlebotomist updates the assignment record without changing the status. Every transition writes a row to `test_status_history` (actor, role, timestamp, note) and to `audit_events` where relevant. The server rejects any transition not listed above.

## Phases

1. Audit, plan and traceability documents.
2. Core framework, environment config, schema migrations, seeders, design tokens and fonts.
3. Authentication: registration, login, logout, profile, password change and reset. Security middleware: CSRF, headers, rate limits.
4. Public site: information pages, announcements, statistics, community comments.
5. Patient workflows: guest (new patient) request, account (existing patient) request, tracking, history, reports, notifications.
6. Admin: dashboard, phlebotomists, assignments, status workflow, results, reports with CSV, comment moderation, users, audit log. Phlebotomist portal.
7. STQA laboratory: rule tester, case catalogue, automated and manual execution runs, defect register, PHPUnit result import.
8. Motion and charts.
9. Verification: PHPUnit (unit, integration, functional), the Node rule-consistency test, browser checks at 375/768/1366/1440 px, security checks.
10. Documentation sync, commit review and a direct push to `main`.
