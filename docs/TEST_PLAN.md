# Test plan

## Purpose and scope

The plan verifies that Vitaliq meets its functional requirements (public information, patient registration and requests, the testing workflow, administration, notifications, comments, the STQA lab) and its security, accessibility and responsiveness requirements.

**In scope**

- All PHP code under `src/`.
- Templates.
- Client JavaScript: validation and behaviour.
- Database schema constraints.
- The development server configuration (`public/router.php`).

**Out of scope**

- Load or performance testing.
- Real email or SMS delivery, which is not implemented.
- Apache/XAMPP runtime behaviour, because Apache was not available.
- Cross-browser testing beyond Chromium.
- Formal penetration testing.

## Test levels

| Level | Tool | Location | What it covers |
| --- | --- | --- | --- |
| Unit | PHPUnit 12 | `tests/Unit` (147 tests) | Validation rules (every catalogue case), the validator, password policy and hashing, CSRF, CSV formula neutralisation, escaping helpers, routing constraints, role middleware on every route, the state-machine table |
| Integration | PHPUnit 12 with MariaDB `vitaliq_test` | `tests/Integration` (67 tests) | Services against a real database: registration, authentication and lockout, reset and invite tokens, the full testing workflow and invalid transitions, concurrency guards, reports (filters, injection, wildcards, CSV), notifications, comments (honeypot, timing, rate limits), phlebotomist lifecycle, user administration, announcements, the QA lab, database outage |
| Functional / system | PHPUnit, driving `App::handle()` in-process with a persistent session | `tests/Functional` (42 tests) | HTTP journeys: public pages and headers, CSP compliance, CSRF, role and ownership enforcement (IDOR), XSS escaping, open-redirect, guest double submission and tracking, registration, release-gated results, admin workflow over HTTP, notification polling, reset links, QA lab API |
| Consistency | Node `node:test` | `tests/js/rules.test.mjs` (3 tests) | Browser and server validation agree on all 115 catalogue inputs and 64 documented rule examples |
| Regex suite | QA lab (`php bin/qa.php run` or the lab UI) | `database/qa/regex-cases.json` | 115 positive, negative, boundary, malformed, whitespace, Unicode and security inputs |
| Manual / exploratory | In-app Chromium and Chrome DevTools | `database/qa/manual-cases.json` | Responsive layout at 375/800/1024/1440 px, keyboard-only completion, reduced motion, Lighthouse accessibility, visual review of every role's screens |
| Mutation spot checks | Temporary code edits | Recorded in the execution report | Proves the security tests fail when the control is removed (CSRF, ownership) |

## Environment

- **Machine:** Windows 11 x64, PHP 8.4.25, MariaDB 11.4.13, Node 22.23.1.
- **Server:** the PHP built-in server on 127.0.0.1:8000.
- **Databases:** `vitaliq` (development, synthetic seed data) and `vitaliq_test` (tests only). The test bootstrap refuses any database name that does not end in `_test`.
- **Browsers:** the desktop app's Chromium pane and Chrome via DevTools MCP (Lighthouse, viewport emulation, keyboard input).

## Test data

- **Unit and integration tests** create their own data per test on empty tables, with the clock frozen at 2026-10-10 10:00. Expiry, rate-limit and timing rules are tested by advancing the clock, not by sleeping.
- **Manual testing** uses the synthetic seed (`bin/seed.php`); no real personal data is used.

## Entry and exit criteria

- **Entry:** the code lints cleanly (`php bin/lint.php`) and migrations apply to an empty database.
- **Exit:** every automated test passes and every catalogue case has a recorded execution. Every defect found is fixed and re-tested, or recorded as open with a reason. Results are written up in [TEST_EXECUTION_REPORT.md](TEST_EXECUTION_REPORT.md) from actual runs only.

## Defect handling

1. Each failure is investigated to its root cause.
2. **Product defects** go into the defect register (`database/seeds/defects.json`, and the lab's Defects page) with steps, expected and actual behaviour, then are fixed and re-tested.
3. **Test-script defects** (a wrong test, not a product fault) are listed separately in [BUG_REPORT.md](BUG_REPORT.md), so the product defect count is not inflated or hidden.

## Risks

| Risk | Mitigation |
| --- | --- |
| Tests pass because they assert nothing meaningful | Mutation spot checks; tests assert database state, not just HTTP status |
| Client and server validation drift apart | Node consistency test over the whole catalogue |
| Time-dependent rules (expiry, limits) are flaky | Injected `Clock`, frozen in tests |
| Destructive tests hit real data | `_test` name guard; separate database and grants |
