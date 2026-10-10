# Architecture

Vitaliq is a server-rendered PHP application with a deliberately small, hand-written core. Every HTTP request goes through one kernel method, so the same code path serves the browser, the in-process functional tests and the command-line tools.

## Layers

| Layer | Location | Responsibility |
| --- | --- | --- |
| Front controller | `public/index.php` | Loads config, opens the native session, builds a `Request`, calls `App::handle()` and sends the `Response` |
| Kernel | `src/Core/App.php` | Routing, the CSRF check on every POST, idle timeout, middleware (`guest`, `auth`, `role:…`), exception mapping (404/405/419/503/500), security headers |
| Infrastructure | `src/Core` | `Router`, `Request`/`Response`, `Session`/`NativeSession`, `Csrf`, `Database` (PDO), `Migrator`, `RateLimiter`, `Clock`, `Logger`, `Mailer`, `View`, `Env`/`Config` |
| Validation | `src/Validation` | `Rules` (the one registry of input formats, exported to the browser) and `Validator` (per-form error collection) |
| Authentication | `src/Auth` | `Auth` (session sign-in, rate limits, revocation), `Passwords` (hashing and policy), `PasswordTokens` (reset and invite) |
| Business logic | `src/Services` | One service per area; all SQL lives here |
| HTTP | `src/Controllers` | Thin controllers: read input, call a service, render a view or redirect |
| Presentation | `views/` | PHP templates; every dynamic value goes through `e()` |
| Assets | `public/assets` | CSS, ES modules, self-hosted fonts and libraries, the WHO data snapshot |
| Tooling | `bin/` | Migrate, seed, create a user, QA suite and import, rule export, lint |

Services receive the `App` and use `app->db`, `app->clock` and other services. This service-locator style keeps a small academic codebase easy to follow without a container library.

## Request lifecycle

1. `Request::fromGlobals()` normalises the path, headers and body (JSON bodies are decoded).
2. Flash messages are aged, except on `/api/*`, so polling cannot consume them.
3. `Router::match()` finds the route. Placeholders are constrained: `{id}` matches only `[1-9][0-9]{0,9}` and `{token}` only URL-safe characters.
4. Any POST without a valid synchroniser token is rejected with 419.
5. `Auth::enforceIdleTimeout()` signs out a session idle for more than 30 minutes.
6. Route middleware runs. `auth`/`role` redirect guests to `/login`, or return 401 JSON on the API, and return 403 for the wrong role.
7. The controller runs. Services throw `ValidationFailed` (re-render with field errors, 422) or `WorkflowException` (flash message and redirect).
8. `DatabaseUnavailable`/`PDOException` become a friendly 503, and other exceptions a 500. Both are logged with a request ID and never show stack traces outside debug mode.
9. Security headers and `Cache-Control: no-store` are added to every response.

## Key workflows

### Testing request state machine

```
submitted ──► assigned ──► sample_collected ──► in_progress ──► completed ──► result_available
    │            │                 │
    ├► cancelled ├► cancelled      └► rejected
    └► rejected  └► rejected
```

- `TestingService::TRANSITIONS` is the single definition. `move()` refuses anything not listed there.
- Status changes use a compare-and-set update: `UPDATE … WHERE id = ? AND status = ?`. Two people acting at once cannot both succeed.
- Each change, inside the caller's transaction:
  - writes a `test_status_history` row with the actor and role;
  - writes an audit event;
  - notifies the patient's account where relevant.

| Action | Who | Extra checks |
| --- | --- | --- |
| Submit (guest) | Anyone | Form token (idempotency key); no second active request of the same type for the same person (the reference is not revealed to guests) |
| Submit (account) | Patient | Same; personal details come from the account |
| Assign / reassign | Admin | Active phlebotomist; exactly one current assignment (unique key on `request_id, is_current`) |
| Record collection | Assigned phlebotomist, or admin | Specimen whitelist, barcode format and uniqueness |
| Start processing, record result, release | Admin | Ct value only for RT-PCR; result invisible to the patient until released |
| Cancel | Owning patient, or admin | Only before collection |
| Reject | Admin | Reason of 5–300 characters |

### New patient and tracking

- The guest form issues a one-time token stored in the session.
- Submitting the same token again (double click or refresh) finds the existing request by its hashed idempotency key and shows the same confirmation.
- The confirmation page only opens in the browser session that submitted the request.
- Tracking requires reference + mobile + date of birth together, and is rate-limited (10 lookups per 15 minutes per connection).

### Notifications

- `NotificationService` stores one row per recipient.
- The bell calls `GET /api/notifications` every 30 seconds, and only while the tab is visible. This is **polling, not push**: a hidden tab stops polling and refreshes when it becomes visible again.

### Validation, shared by browser and server

- `Rules::all()` defines each format once: pattern, length limits, normalisation, human description.
- PHP compiles the pattern as `/…/u`. The browser receives `Rules::forClient()` as inert JSON and compiles `new RegExp(…, "u")` in `public/assets/js/validation.js`.
- Patterns avoid `\d`, `\w`, `\s` and `\b`. These classes match Unicode in PHP with `/u` but only ASCII in JavaScript.
- `tests/js/rules.test.mjs` checks that both engines agree on every catalogue input.

### STQA laboratory

- **Catalogue.** `database/qa/*.json` holds the case definitions, including expected results. `bin/qa.php sync` loads them into `qa_test_cases`.
- **Runs.** Each run is a `qa_runs` row with one `qa_run_results` row per case. A run records its real time and environment. There are four kinds:
  - **Regex suite:** each case goes through the same validator the forms use.
  - **Manual:** a QA analyst records an outcome in the UI.
  - **PHPUnit import:** a JUnit XML report.
  - **Coverage:** catalogue cases marked from the JUnit report through `automation-map.json`. A missing test blocks a case rather than passing it.
- **Results only from runs.** The catalogue shows a case as "Not run" until a run records a result for it.

## Security architecture

Covered in [SECURITY_CHECKLIST.md](SECURITY_CHECKLIST.md).

- **Content Security Policy:** `default-src 'self'; script-src 'self'; style-src 'self'; frame-ancestors 'none'; form-action 'self'`.
- **No inline code:** templates contain no inline scripts or `style` attributes. Bar widths are set from `data-pct` through the CSSOM.

## Front end

- **One stylesheet:** `public/assets/css/vitaliq.css` holds the tokens, layout and components.
- **`app.js`:**
  - navigation and popovers;
  - the notification bell;
  - client validation that mirrors the server;
  - the double-submit guard;
  - conditional fields;
  - GSAP motion.
- **Page scripts:** `charts.js` (D3) and `qa-lab.js` (rule tester).
- **`motion-gate.js`:** loads synchronously on public pages only. It hides reveal targets only when motion is allowed. A 2.5-second failsafe reveals everything if GSAP does not start.

## Configuration

- `.env` is read by `Env` (real environment variables take precedence) and shaped by `Config::fromEnv()`.
- `APP_KEY` signs comment form timestamps and keys IP hashes. The application refuses to start without it.
