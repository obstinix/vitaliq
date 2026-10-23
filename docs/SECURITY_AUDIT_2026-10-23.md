# Security audit, 23 Oct 2026

Scope: the whole repository at `48d35ed`, the local runtime, and dependency manifests. The working tree was clean and identical to `origin/main` before any change.

## 1. PHP runtime: CVE-2026-109618 and CVE-2026-109619

| | |
| --- | --- |
| Advisories | [GHSA-w8hw-w9fx-j79w](https://github.com/php/php-src/security/advisories/GHSA-w8hw-w9fx-j79w) (CVE-2026-109618, RCE via OOM corruption) and [GHSA-6wmf-4rv5-p3rp](https://github.com/php/php-src/security/advisories/GHSA-6wmf-4rv5-p3rp) (CVE-2026-109619, RCE via stale global state). Both are critical, CVSS v4 9.2, and unauthenticated. |
| Affected | Every PHP release before 8.2.35, 8.3.36, 8.4.27 and 8.5.12. Branches 8.1 and older receive no fix. |
| Fixed releases | 8.2.35, 8.3.36, 8.4.27, 8.5.12 (php.net, 21–22 Oct 2026) |
| Found here | The local runtime was **PHP 8.4.25, which is vulnerable**. |

Note on the CVE numbers: php.net's changelog lists these fixes under their GHSA IDs ("Avoid state corruption via bailout"), not under the CVE numbers; the GHSA pages give the CVE mapping.

### Runtime update (server-side, done on this machine)

- Installed the official `php-8.4.27-Win32-vs17-x64.zip` (ZTS, the same build type as before). Its SHA-256 `7902fe89…c64e6b` matched windows.php.net's `sha256sum.txt`.
- Installed to `C:\Users\Piyush\devtools\php-8.4.27`, using the same `php.ini` settings with the new `extension_dir`.
- The user `PATH` now resolves `php` to 8.4.27. 8.4.25 is still on disk but no longer on `PATH`; remove it with `winget uninstall PHP.PHP.8.4` once nothing else needs it.
- Stayed on the 8.4 branch: a patch-level update, with no cross-version upgrade.
- The development server was restarted on 8.4.27.

### Code and configuration changes (in the repository)

| Change | Effect |
| --- | --- |
| `composer.json` `require.php`: `~8.2.35 \|\| ~8.3.36 \|\| ~8.4.27 \|\| ^8.5.12` (was `>=8.2`); `composer.lock` refreshed | `composer install` and `composer check-platform-reqs` now fail on a vulnerable PHP. Verified: 8.4.25 fails, 8.4.27 passes. |
| `src/Support/RuntimeCheck.php`, `bin/check-runtime.php` | `php bin/check-runtime.php` exits 1 on any affected release. Run it with the PHP the web server uses. Verified: 8.4.25 exits 1 with a message, 8.4.27 exits 0. |
| Docs (README, ENVIRONMENT_SETUP) | Minimum versions and the deployment check documented |

### Docker, CI/CD, deployment

The repository contains **no Dockerfile, compose file, CI workflow or deployment manifest**, so there was nothing to change. Vitaliq has never been deployed. Any future environment must use one of the fixed releases; the Composer constraint and `bin/check-runtime.php` enforce that.

## 2. Dependencies

| Manifest | Command | Result |
| --- | --- | --- |
| `composer.json` / `composer.lock` | `composer audit` (8.4.27) | No advisories, before and after the change |
| `tools/d3-bundle/package-lock.json` (build-time only) | `npm audit` | 0 vulnerabilities |
| Vendored front-end files (GSAP 3.15.0, D3 v7 subset) | Reviewed versions | No known advisories; not updated |

- **Runtime dependencies:** none. Composer installs only dev tools.
- **PHPStan:** `phpstan/phpstan` ^2 (2.3.1) was added as a dev dependency for this audit.
- **PHPUnit:** 13 is available, but it is a major version of a dev-only tool with no advisory, so it was left at 12.5.38.

## 3. Application review

| Class | What was checked | Result |
| --- | --- | --- |
| SQL injection | All SQL; interpolation search | All values bound. Interpolated fragments are constant WHERE/ORDER pieces or whitelisted keys. |
| XSS | All template echoes; JS DOM writes | Escaped with `e()`; JS uses `textContent`; JSON blocks use HEX flags; CSP `script-src 'self'` with no inline code |
| CSRF | Every POST route | Kernel-level token check; no state change over GET (route-table test) |
| Authentication and sessions | Hashing, regeneration, revocation, lockout, reset tokens | No issues found |
| Authorisation | Role middleware on every route; object ownership | No issues found |
| File upload / path traversal | Uploads; file APIs | No upload feature. File APIs use constant paths; view names are regex-validated; the dev router serves only `public/assets`. |
| Command injection, unsafe functions | `eval`, `exec`, `system`, `unserialize`, `extract`, `assert` and similar | Only `exec` in `bin/lint.php` (escaped arguments, CLI only) and `extract(EXTR_SKIP)` on internal view data |
| Exposed secrets | Working tree and full Git history | None: demo passwords, DB passwords, `APP_KEY` and token patterns all absent |
| **Abuse and rate limiting** | All anonymous POST endpoints | **Two gaps found and fixed** (below) |

### Findings fixed

| ID | Finding | Severity | Fix | Regression tests |
| --- | --- | --- | --- | --- |
| DEF-012 | `POST /register` had no rate limit. It could be scripted to mass-create accounts and to enumerate which emails and mobile numbers are registered. | Medium | Per-connection limit of 10 attempts per hour, counting failed attempts too (`AuthController::REGISTER_MAX_PER_IP`); responds 429 | `AbuseLimitsTest::testRegistrationIsRateLimitedPerConnection`, `testFailedRegistrationsCountTowardsTheLimit` |
| DEF-013 | `POST /test/new` (anonymous testing requests) had no rate limit, so spam requests could flood the admin queue and notifications. | Medium | Per-connection limit of 10 per hour (`GuestTestController::SUBMIT_MAX_PER_IP`). A double-submitted form still returns its original confirmation. | `AbuseLimitsTest::testGuestRequestsAreRateLimitedButReplaysStillWork` |
| DEF-014 | Runtime allowed vulnerable PHP versions | Critical, runtime | See section 1 | `RuntimeCheckTest` (16 cases, including the interpreter running the tests and that `composer.json` contains every floor) |

**Mutation check.** Disabling both new limiter calls made all three `AbuseLimitsTest` tests fail. With the code restored they pass.

### Static analysis

PHPStan 2.3.1 at level 6 (`phpstan.neon.dist`) initially reported 26 items. None was a vulnerability: they were missing iterable type annotations, the bootstrap-defined `VQ_ROOT` constant, one unreachable comparison in `Request::normalisePath()`, and one array-key type in `Validator::choice()`. All were fixed in code (no baseline, no ignores). Final result: **No errors**.

## 4. Verification on PHP 8.4.27

| Check | Result |
| --- | --- |
| `php bin/check-runtime.php` | OK |
| `php bin/lint.php` | 160 files, no syntax errors |
| `vendor/bin/phpstan analyse` | No errors (level 6) |
| `vendor/bin/phpunit` | **275 tests, 1091 assertions, 0 failures, 0 skips** (unit 163, integration 67, functional 45) |
| `node --test tests/js/rules.test.mjs` | 3 of 3 |
| `php bin/qa.php run` (lab run #13) | 115 of 115 |
| `composer audit` / `npm audit` | Clean |
| HTTP smoke test on the 8.4.27 dev server | Pages 200, protected areas 303; `/.env`, `/composer.json`, `/vendor/…` and `/router.php` 404; CSP, X-Frame-Options and nosniff present; no `X-Powered-By` |

## 5. Remaining risks and required actions

- **Server-side (must be done for any deployment):**
  - Run PHP 8.2.35, 8.3.36, 8.4.27 or 8.5.12 or later under PHP-FPM or Apache.
  - Restart the PHP workers after upgrading (the CVE-2026-109619 bug lives in persistent workers).
  - Run `php bin/check-runtime.php` with that binary.
- **Account-lockout DoS (accepted).** The per-account sign-in limit lets someone lock a known email out for 15 minutes. This is the usual trade-off; a CAPTCHA or proof-of-work would remove it.
- **IP-based limits.** Behind a reverse proxy, every client would share the proxy's IP. Vitaliq deliberately does not trust `X-Forwarded-For`, so a deployment behind a proxy must configure the proxy to pass real client addresses safely before relying on these limits.
- **Not verified locally:**
  - HTTPS (Secure cookie, HSTS) and Apache `.htaccess` rules: no TLS or Apache here.
  - No third-party penetration test.
- **Other:**
  - The `php -S` development server is for local use only.
  - No regulatory compliance assessment has been done.

With these caveats, the code-level review and verification found no remaining known vulnerabilities. This is not a guarantee that the application is secure.
