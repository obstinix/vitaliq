# Bug report

Defects found while building and testing Vitaliq on 10 Oct 2026. Every entry was observed in an actual run or browser session. Product defects are also loaded into the QA lab's defect register (`database/seeds/defects.json`), where DEF-001 and DEF-002 are clearly labelled **demonstration** records and are not counted here.

## Summary

| ID | Title | Module | Severity | How found | Status |
| --- | --- | --- | --- | --- | --- |
| DEF-003 | Catalogue sync silently skipped 5 test cases with duplicate codes | STQA lab | Major | Seeder reported 146 cases instead of 151 | Resolved |
| DEF-004 | Ct value rule accepted 45.1–45.9 although the limit is 45 | Results | Minor | Regex suite run #1: TC-CT-004 failed | Resolved |
| DEF-005 | Pages widened beyond the viewport at 375 px | Interface | Major | Browser check: `/community` was 453 px and `/patient` 383 px wide | Resolved |
| DEF-006 | Stacked table cells split multi-line content into the label column | Interface | Minor | Visual review of `/staff` at 800 px | Resolved |
| DEF-007 | Mobile menu button shown on desktop and navigation crowded at 1024 px | Interface | Minor | Visual review at 1024 px; computed style showed `display:flex` on the toggle | Resolved |
| DEF-008 | Phlebotomist "Recently collected" listed the oldest collections first | Phlebotomist portal | Minor | Recording a collection in the browser | Resolved |
| DEF-009 | Text contrast below WCAG AA for badges and sidebar labels | Accessibility | Major | Lighthouse on `/admin`: accessibility 96, colour-contrast failures | Resolved |
| DEF-010 | Wordmark link name did not contain its visible text (WCAG 2.5.3) | Accessibility | Minor | Lighthouse on `/statistics`: label-content-name-mismatch | Resolved |
| DEF-011 | Platform figures note broke into columns | Interface | Trivial | Visual review of the homepage | Resolved |
| DEF-012 | Registration had no rate limit (account spam, email/mobile enumeration) | Security | Medium | Security audit, 23 Oct 2026 | Resolved |
| DEF-013 | Anonymous test requests had no rate limit (queue spam) | Security | Medium | Security audit, 23 Oct 2026 | Resolved |
| DEF-014 | Runtime PHP 8.4.25 vulnerable to CVE-2026-109618/109619 | Runtime | Critical | Security audit, 23 Oct 2026 | Resolved (local runtime upgraded; deployments must use a fixed release) |

| DEF-015 | Lab order rejected when no priority was sent | Laboratory | Minor | `AdministrationTest`, 2 Nov 2026 | Resolved: priority defaults to routine |
| DEF-016 | Wide tables and screen-reader labels widened phone pages (up to 702 px) | Interface | Major | Browser measurement at 375 px, 2 Nov 2026 | Resolved: panels contain and scroll their tables |
| DEF-017 | Trend chart's 7-day average dipped on the last day | Surveillance | Minor | Visual review, 1 Nov 2026 | Resolved: series and fit end yesterday, because today is still filling |
| DEF-018 | Migration 0007 failed on MariaDB: CHECK on a column with an ON DELETE SET NULL foreign key | Database | Major | First migration run, 1 Nov 2026 | Resolved: the two-person rule is enforced in `LaboratoryService::verifyItem()`; the partly created empty tables were dropped before re-running |

There are no open product defects.

## Details

### DEF-003: duplicate test case codes were dropped silently

- **Steps.** Seed an empty database; compare the reported case count with the JSON catalogue.
- **Expected.** 151 cases, or an error.
- **Actual.** 146 cases. The required-field regex cases and the manual testing-request cases both used the `TC-REQ-` prefix, and `syncCatalogue()` skipped codes that already existed.
- **Fix.** The regex cases were renamed `TC-RQD-nnn`, and `syncCatalogue()` now throws on a repeated code.
- **Re-test.** `QaLabTest::testDuplicateCaseCodeInCatalogueIsAnError` and `testCatalogueSyncLoadsEveryCaseOnceAndHasNoResults` pass; a fresh seed loads 151 cases.

### DEF-004: Ct value 45.9 accepted

- **Steps.** `php bin/qa.php run`; inspect TC-CT-004 (input `45.9`, expected Rejected).
- **Expected.** Rejected.
- **Actual.** Run #1 recorded "Accepted by the validator". The pattern `4[0-5](\.[0-9])?` allows 45.1–45.9, but `test_results` has `CHECK (ct_value <= 45)`, so saving such a value would have produced a database error instead of a field message.
- **Fix.** The pattern is now `^(?:(?:[0-9]|[1-3][0-9]|4[0-4])(?:\.[0-9])?|45(?:\.0)?)$`.
- **Re-test.** Regex suite run #2 passed 115 of 115; `RulesTest::testCtValueUpperBoundMatchesDatabaseConstraint` covers 45, 45.0, 45.1 and 45.9.

### DEF-005: horizontal overflow on phones

- **Steps.** Open `/community` in a 375×812 mobile viewport and compare `scrollWidth` with `innerWidth`.
- **Expected.** 375 = 375.
- **Actual.** The page was 453 px wide. Grid items default to `min-width: auto`, so the non-wrapping topic tabs, and on `/patient` long reference numbers, forced columns wider than the screen.
- **Fix.** Added `min-width: 0` on grid children; on phones, stacked table labels sit above their values.
- **Re-test.** Fourteen public and patient pages measured exactly 375 px wide (TC-UI-001).

### DEF-006: stacked table layout broke multi-line cells

- **Actual.** Each stacked `<td>` was a two-column grid, so `<br>` and the text after it became separate grid items. The patient's mobile number and address appeared under the label column, and status pills stretched to full width.
- **Fix.** Block layout with an absolutely positioned label.
- **Re-test.** Visual check of `/staff`, `/patient` and `/admin/requests` at 800 and 375 px.

### DEF-007: navigation toggle visible on desktop

- **Actual.** `.btn { display: inline-flex }` appears later in the stylesheet than `.nav-toggle { display: none }` with equal specificity, so both the Menu button and the full navigation were visible, and link text wrapped at 1024 px.
- **Fix.** The rule is scoped to `.masthead .nav-toggle`, links are set to `nowrap`, and the collapse breakpoint is now 75 rem.
- **Re-test.** Screenshots at 800 and 1440 px.

### DEF-008: wrong order of completed collections

- **Actual.** The completed list reused the open-task query and its preferred-date ascending order, so the sample just collected appeared last, and reassigned requests could appear twice.
- **Fix.** A dedicated query on `sample_collections`, ordered by `collected_at DESC`.
- **Re-test.** In the browser, as Ramesh Kumar.

### DEF-009: insufficient contrast

- **Actual.** Lighthouse reported white on coral at 4.2:1 (notification badge) and `#6a7672` on `#f1f3ee` at 4.22:1 (sidebar section labels). Calculation also showed teal status text on its wash at 4.12:1.
- **Fix.** The badge uses `#a3412f` (6.26:1), `--ink-3` is now `#5f6b67` (at least 4.64:1 on every surface), and a new `--teal-text` `#2f6a66` (5.29:1) is used for status text.
- **Re-test.** Lighthouse accessibility 100 on `/admin`, `/admin/reports`, `/admin/requests/{id}`, `/qa` and `/qa/cases`.

### DEF-010: accessible name mismatch

- **Actual.** `aria-label="Vitaliq home"` hid the visible tagline from the accessible name.
- **Fix.** The `aria-label` was removed.
- **Re-test.** Lighthouse on `/statistics`: 0 failed audits.

### DEF-011: figures note layout

- **Actual.** `display: flex` turned each text node and the link into flex items, so the note rendered as narrow columns.
- **Fix.** Block layout with a positioned marker.
- **Re-test.** Visual check.

### DEF-012 to DEF-014: security audit of 23 Oct 2026

See [SECURITY_AUDIT_2026-10-23.md](SECURITY_AUDIT_2026-10-23.md) for steps, fixes and regression tests (`AbuseLimitsTest`, `RuntimeCheckTest`).

## Test-script defects

These were faults in the tests themselves, found when a test failed and the investigation showed the product was correct. They are recorded so the failures are accounted for, and are not counted as product defects.

| Test | Problem | Correction |
| --- | --- | --- |
| `TestingWorkflowTest` | A helper named `status()` overrode a final PHPUnit method (fatal error) | Renamed to `requestStatus()` |
| `TestingWorkflowTest::testGuestRequestCreatesAllRecordsInOneTransaction` | `ORDER BY` on an ENUM column sorts by declaration order, not alphabetically | Sort in PHP |
| `CommunityAndAdminTest::testTooFastForgedAndStaleStampsAreRefused` (twice) | The helper advances the clock 10 s per call, so the "too fast" stamp was actually 10–20 s old | Build and submit the fast case first |
| `ReportsAndNotificationsTest::testPhlebotomistFilterAndPaginationBounds` | Assumed a page size of 2, but the service clamps the minimum to 5, by design | Assert the clamp |
| `PublicSiteTest::testPublicPageRendersWithSecurityHeaders` | `\b` after `"application/json"` never matches, so JSON data blocks were flagged as inline scripts | Corrected the regex |
| `bin/export-rules.php` (Node consistency test harness) | Marked a 260-character email as "server-only rejection" without checking length first | Apply length bounds before the `filter_var` comparison |
