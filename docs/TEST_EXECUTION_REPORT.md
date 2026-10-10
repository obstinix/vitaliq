# Test execution report

- **Execution date:** 10 Oct 2026. The final recorded run started at 17:39 IST.
- **Environment:** Windows 11 x64 · PHP 8.4.25 · MariaDB 11.4.13 · Node 22.23.1 · PHPUnit 12.5.38 · PHP built-in server.

Every number below comes from an actual execution. The QA lab database records each run with its start time and environment, and [TEST_CASES.md](TEST_CASES.md) is generated from those records.

## Final results

| Suite | Command | Result |
| --- | --- | --- |
| Syntax check | `php bin/lint.php` | 156 files, no errors |
| PHPUnit (unit, integration, functional) | `vendor/bin/phpunit --log-junit build/junit.xml` | **256 tests, 1023 assertions: 256 passed, 0 failed, 0 skipped** (unit 147, integration 67, functional 42) |
| Client/server validation consistency | `node --test tests/js/rules.test.mjs` | **3 of 3 passed** (115 catalogue inputs + 64 rule examples agree) |
| Regex suite (lab run #12) | `php bin/qa.php run` | **115 of 115 passed** |
| PHPUnit import (lab run #10) | `php bin/qa.php import-junit build/junit.xml` | 256 of 256 recorded as passed |
| Catalogue coverage (lab run #11) | `php bin/qa.php cover build/junit.xml` | **32 of 32** functional and security cases passed via their mapped tests |
| Manual executions (lab runs #6–#9) | Browser, recorded in the lab UI | **4 of 4 passed** (TC-SEC-004, TC-UI-001, TC-UI-002, TC-A11Y-001) |
| **Catalogue total** | `/qa` overview | **151 of 151 cases executed; latest result PASS for all 151; 0 blocked; 0 not run** |

## Run history (QA lab)

| Run | Type | Started | Total | Pass | Fail | Notes |
| --- | --- | --- | --- | --- | --- | --- |
| 1 | Regex suite | 04:40 | 115 | 114 | **1** | TC-CT-004 failed: 45.9 accepted (**DEF-004**) |
| 2 | Regex suite | 04:40 | 115 | 115 | 0 | After the DEF-004 fix |
| 3 | PHPUnit import | 04:55 | 254 | 254 | 0 | Before the coverage tests were added |
| 4 | PHPUnit import | 05:00 | 256 | 256 | 0 | |
| 5 | Coverage | 05:00 | 32 | 32 | 0 | From run 4's report |
| 6–9 | Manual | 05:05 | 4 | 4 | 0 | See manual testing below |
| 10 | PHPUnit import | 17:39 | 256 | 256 | 0 | **Final code** |
| 11 | Coverage | 17:39 | 32 | 32 | 0 | **Final code** |
| 12 | Regex suite | 17:39 | 115 | 115 | 0 | **Final code** |

## Failures encountered during testing

Every failure below was investigated to its root cause and the relevant tests were re-run.

| When | What failed | Root cause | Outcome |
| --- | --- | --- | --- |
| Seeding | 146 of 151 cases loaded | Duplicate `TC-REQ-` codes skipped silently (**DEF-003**) | Fixed; guard added; 151 loaded |
| Regex run #1 | TC-CT-004 | Ct pattern allowed 45.1–45.9 (**DEF-004**) | Fixed; run #2 passed |
| First integration run | 1 of 39 | Test assumed alphabetical ENUM ordering | Test corrected |
| Integration | 2 of 65 | Test clock ordering; page-size clamp | Tests corrected |
| Functional | 16 of 42 | Test regex flagged JSON data blocks as inline scripts | Test corrected; the product had no inline scripts |
| Full suite | 1 of 254 | Clock advanced while the array of inputs was being built | Test corrected |
| Node test | 2 of 3 | Harness skipped the length check before comparing `filter_var` | Harness corrected |

The six test-script defects are listed in [BUG_REPORT.md](BUG_REPORT.md#test-script-defects).

## Mutation spot checks

To show the security tests can fail, each control was temporarily removed, the tests run, and the code restored. A file check confirmed no mutant remained.

| Mutation | Tests run | Result while mutated | After restoring |
| --- | --- | --- | --- |
| `Csrf::verify()` always returns true | `--filter Csrf` | 4 of 4 failed | 4 of 4 passed |
| `PatientController::show` skips the ownership check | `testPatientCannotSeeOrCancelAnotherPatientsRequest` | Failed: 200 instead of 404 | Passed |

## Manual and exploratory testing

These checks ran between 04:00 and 05:10 IST against the code at that time. Later commits changed only documentation, the defect seed file, the QA run-type label and a mobile spacing rule; the automated suites were re-run on the final code (runs 10–12).

| Check | Method | Result |
| --- | --- | --- |
| HTTP smoke test | curl on 27 routes | Public pages 200; `/admin`, `/patient` and `/qa` redirect to sign-in (303); `/api/notifications` 401; `/.env`, `/../.env` and `/router.php` 404 |
| Role walkthroughs | In-app Chromium, signed in as admin, phlebotomist, patient and QA analyst | Assigned VQ-261009-6B1V6 to PHL-0001, recorded its collection as that phlebotomist (an invalid barcode was blocked client-side and focus moved to the field), viewed a released result and the printable report, used the bell, the rule tester and the lab overview. No console errors apart from the intended framing block. |
| Responsive (TC-UI-001) | Document width vs viewport at 375×812 | 14 pages equal to 375 px after DEF-005; 800 and 1440 px reviewed visually |
| Keyboard only (TC-A11Y-001) | Chrome via DevTools key events | Request VQ-261010-K2QQS submitted using only the keyboard. A visible focus ring on each control. The conditional address field appeared as the next tab stop. An empty submit moved focus to the first error. |
| Reduced motion (TC-UI-002) | `matchMedia` overridden before page scripts | No hidden content and counters at their final values. The CSS `@media` rule itself was not exercised by this method. |
| Motion, normal mode | Chrome | All 8 elements in view revealed within 2.5 s; counters ended on the exact values |
| Accessibility audit | Lighthouse (Chrome DevTools) | **100** on `/` (mobile), `/test/new` (mobile), `/statistics`, `/admin`, `/admin/reports`, `/admin/requests/{id}`, `/qa/cases` and `/qa` after fixing **DEF-009** and **DEF-010** (before the fixes: `/admin` 96, `/statistics` with 1 failed audit). Best Practices and SEO 100 on all. |
| External links | curl on every cited URL | All WHO, MoHFW and data URLs 200. The CDC pages answer 403 to curl's user agent but were read successfully with a browser fetch the same day. |
| D3 bundle reproducibility | `npm ci` + esbuild per `tools/d3-bundle/README.md` | Byte-identical (same SHA-256) |

## Not executed or blocked

| Item | Reason |
| --- | --- |
| Apache/XAMPP `.htaccess` behaviour and sub-folder base path | Apache not installed; rules reviewed only |
| HTTPS-only behaviour (Secure cookie, HSTS) | No TLS locally |
| Browsers other than Chromium (Firefox, Safari) | Not available in the environment |
| Screen-reader testing (NVDA, VoiceOver) | Not available; covered partly by Lighthouse and semantic markup |
| Performance and load testing | Out of scope; the built-in server is not representative |
| Email or SMS delivery | Not implemented |

## Known limitations

- Notifications are polled, not pushed, and pause while the tab is hidden.
- Guest tracking relies on knowing three details (reference, mobile, date of birth). That is weaker than an account, so the confirmation page and tracking are rate-limited and session-scoped.
- Internationalised (non-ASCII) email addresses are rejected by design.
- Seeded statistics are synthetic and labelled as such. The WHO dataset is a snapshot from 10 Oct 2026, not a live feed.
- Charts provide data tables but no keyboard-navigable data points.
