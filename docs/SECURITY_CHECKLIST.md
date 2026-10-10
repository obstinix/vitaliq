# Security checklist

What is implemented, and how each control was actually verified. "PHPUnit" means an automated test that passed in the recorded run (see [TEST_EXECUTION_REPORT.md](TEST_EXECUTION_REPORT.md)). "Mutation" means the control was temporarily removed and the test was confirmed to fail.

## Authentication and sessions

| Control | Implementation | Verified by |
| --- | --- | --- |
| Password hashing | `password_hash` with Argon2id (bcrypt fallback); rehash on sign-in when needed | PHPUnit `SecurityPrimitivesTest::testPasswordsUseArgon2idAndVerify`, `RegistrationAndAuthTest::testRegistrationPersistsUserAndPatientWithHashedPassword` |
| Password policy | 10–128 characters, a letter and a digit, a common-password blocklist, must not contain the email name | PHPUnit `testPasswordPolicy`; regex suite TC-PWD-001…012 |
| Session fixation | `session_regenerate_id(true)` at sign-in and password change; strict mode; cookies only | PHPUnit `testValidLoginRegeneratesSessionAndRecordsLogin`, `SecurityTest::testLoginRegeneratesSessionAndLogoutClearsIt` |
| Cookie flags | HttpOnly, SameSite=Lax, Secure automatically over HTTPS | Code review of `NativeSession::start` (HTTPS not available locally) |
| Revocation | `users.session_version` checked on every request; incremented on password change, reset and deactivation | PHPUnit `testSessionEndsWhenSessionVersionChangesOrUserDeactivated`, `testChangePasswordRequiresCurrentPasswordAndKeepsThisSession` |
| Idle timeout | 30 minutes (configurable) | PHPUnit `testIdleTimeoutSignsOut` |
| Brute force | 5 failures per account per 15 min, 30 per IP; generic error; dummy Argon2id verify for unknown emails | PHPUnit `testAccountIsLockedAfterFiveFailures`, `testWrongPasswordAndUnknownEmailGiveTheSameMessage` |
| Logout | POST only, CSRF-protected, session destroyed | PHPUnit `RouterAndWorkflowRulesTest::testMethodNotAllowed` (GET /logout is 405), `SecurityTest::testLoginRegeneratesSessionAndLogoutClearsIt` |

## Authorisation

| Control | Implementation | Verified by |
| --- | --- | --- |
| Role checks on the server | `role:` middleware on every admin, QA, patient and staff route | PHPUnit `testEveryAdminAndQaRouteRequiresARole` (checks the whole route table) and `SecurityTest::testRolesAreEnforcedOnTheServer` (403, including a direct POST to a release endpoint) |
| Object-level ownership | Patient queries join on `patients.user_id = session user`; other people's records return 404 | PHPUnit `testPatientCannotSeeOrCancelAnotherPatientsRequest`, `testDetailForPatientIsScopedToTheOwner`; **mutation**: removing the ownership check made the test fail (200 instead of 404) |
| Phlebotomist scope | Only the current assignee can see or record a collection | PHPUnit `testPhlebotomistCannotActOnAnotherPhlebotomistsCollection`, `testOnlyTheAssignedPhlebotomistCanRecordCollection` |
| Workflow integrity | Server-side transition table; compare-and-set UPDATE; actor role checked in each service method | PHPUnit `testInvalidTransitionsAreRefusedAndStatusIsUnchanged`, `testPatientsAndGuestsCannotPerformStaffActions`, `testAdminWorkflowThroughHttp` |
| Results released only by authorisation | Patient pages show a result only in `result_available` | PHPUnit `testResultIsVisibleToPatientOnlyAfterRelease` |
| Guest data | Confirmation page limited to the submitting session; tracking needs reference + mobile + date of birth; duplicate message does not reveal the existing reference | PHPUnit `testNewPatientRequestsTracksAndCannotDoubleSubmit`, `testGuestTrackingNeedsAllThreeDetails`, `testSecondActiveRequestOfSameTypeIsRefusedWithoutLeakingReference` |
| Admin safety | Cannot deactivate yourself or the last admin; deactivation revokes sessions | PHPUnit `testUserAdministrationGuards` |

## Injection and output handling

| Control | Implementation | Verified by |
| --- | --- | --- |
| SQL injection | PDO with `ATTR_EMULATE_PREPARES=false` and bound parameters everywhere; sort keys and filters whitelisted; `LIKE` wildcards escaped | PHPUnit `testInjectionAttemptsAreInertAndWildcardsAreLiteral`, `SecurityTest::testReportSearchReflectsInputSafely`; regex suite security cases. A code search for interpolation in SQL strings found only WHERE and ORDER fragments assembled from constant strings and whitelisted keys (AuditLog, CommentService, NotificationService, PhlebotomistService, QaService, UserAdminService, ReportService, DefectService); every value is a bound parameter. |
| XSS | `e()` (htmlspecialchars, ENT_QUOTES, ENT_SUBSTITUTE) on all output; JS uses `textContent` only; JSON data in inert `<script type="application/json">` with HEX flags | PHPUnit `testCommentHtmlIsEscapedWhenPublished`, `testJsonScriptCannotBreakOutOfItsElement`, `testHtmlEscaping`, `testInvalidGuestSubmissionReDisplaysErrorsWithoutSaving` |
| Content Security Policy | `default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self' data:; frame-ancestors 'none'; form-action 'self'; base-uri 'self'; object-src 'none'` | PHPUnit `testPublicPageRendersWithSecurityHeaders` (also asserts no inline scripts or style attributes on 16 pages); browser console showed the framing block |
| CSRF | Per-session token on every POST (form field or `X-CSRF-Token`), rotated at sign-in and sign-out; constant-time comparison | PHPUnit `testCsrfTokenVerification`, `testPostWithoutOrWithWrongCsrfTokenIs419`, `testCsrfTokenFromAnotherSessionIsRejected`; **mutation**: disabling the check made 4 tests fail |
| Open redirect | Post-sign-in redirect accepts only an internal path within the role's area | PHPUnit `testOpenRedirectIsNotPossibleAfterLogin` |
| CSV formula injection | Cells starting with `= + - @ \t \r` are prefixed with `'` | PHPUnit `testCsvCellsAreNeutralised`, `testCsvExportHasHeaderAndNeutralisedCells` |
| Regex safety | Users choose a predefined rule and never supply a pattern; input capped at 500 characters | PHPUnit `testEvaluateRefusesOversizedInput`; code review |

## Account recovery

| Control | Implementation | Verified by |
| --- | --- | --- |
| Token generation | 32 random bytes, base64url | Code review |
| Storage | SHA-256 hash only | PHPUnit `testOnlyTheHashIsStored` |
| Expiry and single use | Reset 30 min, invite 48 h; consumed in a `FOR UPDATE` transaction; earlier tokens superseded | PHPUnit `testTokenExpiresAfterThirtyMinutes`, `testTokenResetsPasswordOnceAndSignsOutSessions`, `testNewTokenSupersedesTheOldOne`, `testResetLinkWorksOnceThroughHttp` |
| No account enumeration | Identical response for known and unknown emails, and when rate-limited | PHPUnit `testForgotPasswordGivesTheSameAnswerForUnknownEmails` |
| Referrer leakage | `Referrer-Policy: no-referrer` on reset pages | PHPUnit (header asserted in `testResetLinkWorksOnceThroughHttp`) |
| Email delivery | **Not configured.** The local outbox writes `.eml` files; the UI never claims an email was sent | Code review; documented limitation |

## Abuse controls and privacy

| Control | Implementation | Verified by |
| --- | --- | --- |
| Rate limits | Database-backed fixed windows: sign-in, reset, tracking (10/15 min), linking, comments (3/10 min), QA runs | PHPUnit `testTrackingIsRateLimited`, `testCommentRateLimitAndLinkLimit`, lockout test |
| Comment spam | Honeypot, HMAC-signed form time (3 s to 2 h), link cap, pre-moderation | PHPUnit `testHoneypotSubmissionIsDiscardedSilently`, `testTooFastForgedAndStaleStampsAreRefused` |
| Double submission | One-time form token and unique idempotency key; client button disabled | PHPUnit `testNewPatientRequestsTracksAndCannotDoubleSubmit`, `testSameIdempotencyKeyFindsTheExistingRequest` |
| IP privacy | Only `HMAC-SHA256(ip, APP_KEY)` is stored | Code review of `App::ipHash` and the schema (`ip_hash` columns only) |
| Small-cell suppression | Public positive counts of 1–4 are shown as "<5" | Visual check of `/statistics` |
| Data minimisation | No ID, insurance or unrelated medical-history fields | Review of the forms against the requirements |
| Audit trail | `audit_events` records sign-ins (including failures), registration, workflow changes, exports, user and phlebotomist administration, moderation and QA runs; details never include secrets | PHPUnit assertions on `audit_events` rows; browser review of `/admin/audit` |

## Configuration and deployment hygiene

| Control | Implementation | Verified by |
| --- | --- | --- |
| Secrets out of Git | `.env` ignored; `.env.example` holds placeholders; demo passwords are random and printed once | `git check-ignore .env`; a pattern scan before every commit found no passwords, keys or tokens in tracked files |
| Private files not served | Web root is `public/`; `router.php` serves only `public/assets`; `.htaccess` rules deny dotfiles and private folders | curl: `/.env`, `/../.env` and `/router.php` returned 404 (TC-SEC-004). Apache rules reviewed but not executed. |
| Safe errors | 404, 419, 503 and 500 pages without stack traces (unless `APP_DEBUG` in a non-production environment); errors logged with a request ID and no secrets | PHPUnit `DatabaseFailureTest` (checks no SQLSTATE, password or exception class in the output); log reviewed |
| Security headers | CSP, X-Content-Type-Options, X-Frame-Options DENY, Referrer-Policy, Permissions-Policy, COOP; HSTS over HTTPS; `Cache-Control: no-store` | PHPUnit header assertions; `testAuthenticatedPagesAreNotCached` |
| Least-privilege database user | App user has rights on `vitaliq` and `vitaliq_test` only, without GRANT or SUPER | Verified when the user was created (see ENVIRONMENT_SETUP.md) |
| File uploads | None implemented, so there is no upload surface | Not applicable |

## Not verified, or out of scope

- Behaviour under real HTTPS (Secure cookies, HSTS): no TLS in the local environment.
- Apache `.htaccess` rules: Apache was not installed.
- Third-party penetration test, dependency CVE monitoring and production hardening (PHP-FPM, WAF, backups).
- Regulatory compliance (HIPAA, GDPR, India's DPDP Act): no assessment was performed and no compliance is claimed.
