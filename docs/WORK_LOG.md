# Work log

All work was done on 10 Oct 2026 (IST).

| Step | Work | Verification |
| --- | --- | --- |
| 1 | Audited the repository (licence only) and the machine (no PHP, database or Composer). Confirmed the GitHub credential belongs to `obstinix` (id 139326479) with push rights. | [PROJECT_AUDIT.md](PROJECT_AUDIT.md) |
| 2 | Installed PHP 8.4.25 (winget, user scope), MariaDB 11.4.13 (official zip, SHA-256 checked) and Composer 2.10.3 (installer SHA-384 checked). Created the `vitaliq` and `vitaliq_test` databases and a least-privilege user. | Versions recorded in [ENVIRONMENT_SETUP.md](ENVIRONMENT_SETUP.md) |
| 3 | Chose fonts from the Awwwards free-fonts collection by licence; subset Junicode with fontTools. Built a D3 subset with esbuild. Downloaded and aggregated the WHO dataset. | Monthly totals equal WHO's cumulative figure; the D3 rebuild is byte-identical |
| 4 | Core kernel, schema (3 migrations), validation registry, authentication, services, controllers, views, CSS and JS | `php bin/lint.php` clean; migrations idempotent |
| 5 | Demo seeder and QA catalogue (115 regex and 36 manual cases). The first seed loaded 146 of 151 cases. | Found **DEF-003** (duplicate codes), fixed, reseeded: 151 |
| 6 | First regex suite run: 114 of 115 | Found **DEF-004** (Ct 45.9 accepted), fixed; run #2: 115 of 115 |
| 7 | Started the dev server and smoke-tested 27 routes with curl (public 200, protected 303, API 401, `.env` 404) | — |
| 8 | Browser walkthrough as admin, phlebotomist, patient and QA analyst: assign, collect (with client validation), result and report screens, bell | Found **DEF-006**, **DEF-007**, **DEF-008** and **DEF-011**; fixed |
| 9 | PHPUnit suites (unit, integration, functional) and the Node consistency test | Fixed six test-script mistakes ([BUG_REPORT.md](BUG_REPORT.md)); 256 of 256 and 3 of 3 pass |
| 10 | Mutation spot checks: disabling CSRF verification and the patient ownership check | Both caught (4 failures; 200 instead of 404); reverted |
| 11 | Committed in focused commits under the verified noreply identity | `git log` shows the author and committer |
| 12 | Measured at 375 px | Found **DEF-005** (pages 453 and 383 px wide); fixed; 14 pages re-measured at exactly 375 |
| 13 | Lighthouse (Chrome DevTools) on 8 pages | Found **DEF-009** (contrast) and **DEF-010** (accessible name); fixed; all 100 |
| 14 | Keyboard-only request submission; reduced-motion check (simulated `matchMedia`); manual executions recorded in the lab | TC-A11Y-001, TC-UI-001, TC-UI-002 and TC-SEC-004 pass |
| 15 | Catalogue coverage from the JUnit report (`bin/qa.php cover`); added the `coverage` run type (migration 0004) | 32 of 32 mapped cases pass |
| 16 | Documentation, screenshots, final test run and recording, push to `main` | [TEST_EXECUTION_REPORT.md](TEST_EXECUTION_REPORT.md) |

## Notes

- The demo account passwords were generated at seed time and kept in a private file outside the repository. They are not in Git or the documentation.
- The personal access token supplied in the request was **not used**. Git Credential Manager already held a working credential for the same account.
