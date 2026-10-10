# Requirements traceability

Every requirement from the original brief, mapped to its implementation and to the evidence that it works. Test names refer to PHPUnit classes under `tests/`; `TC-…` codes are catalogue cases with recorded executions ([TEST_CASES.md](TEST_CASES.md)). Status reflects what was verified, not just what was written.

## 5.1 Public COVID-19 information website

| Requirement | Implementation | Evidence | Status |
| --- | --- | --- | --- |
| Home page | `PublicController::home`, `views/public/home.php` | `PublicSiteTest` (16 pages: 200 and headers); screenshot `docs/screenshots/home.png`; Lighthouse 100 | Done |
| COVID-19 overview, symptoms and warning signs, prevention, testing information and instructions, FAQ | `views/public/overview.php`, `symptoms.php`, `prevention.php`, `testing.php`, `faq.php` | `PublicSiteTest::testInformationPagesCiteSources`; content checked against WHO (27 Nov 2025) and CDC (10 Mar 2025) pages on 10 Oct 2026 | Done |
| Public-health announcements | `AnnouncementService`, `/announcements`, admin `/admin/announcements` | `CommunityAndAdminTest::testPublicHealthAnnouncementsMustCiteASource`; three WHO milestones verified against who.int | Done |
| Regional information and testing statistics | `StatsService`, `/statistics`: state/UT table, platform charts, WHO India monthly data | `ReportsAndNotificationsTest::testDashboardMetricsComeFromTheDatabase`, `PublicSiteTest::testStatisticsAreLabelledAndComputedFromData`; the WHO monthly sum is checked against WHO's cumulative total | Done |
| Public comments and feedback | `/community` | See 5.11 | Done |
| Contact and help | `/contact` (112 emergency number; MoHFW link instead of possibly stale helpline numbers) | Page renders; links checked | Done |
| Registration and login entry points | Masthead, hero, footer, forms | Functional journeys | Done |
| Sources and update times; no seeded figures presented as real | `views/partials/sources.php` with publisher and last-updated date; "Not official statistics" notice; `app_settings.demo_data_seeded_at` shown | `testStatisticsAreLabelledAndComputedFromData` | Done |

## 5.2 Patient registration and authentication

| Requirement | Implementation | Evidence | Status |
| --- | --- | --- | --- |
| Registration, persisted | `PatientService::register` (user and patient rows in one transaction) | `RegistrationAndAuthTest::testRegistrationPersistsUserAndPatientWithHashedPassword`; TC-AUTH-001 | Done |
| Login, logout, sessions | `Auth`, `AuthController` | `testValidLoginRegeneratesSessionAndRecordsLogin`, `SecurityTest::testLoginRegeneratesSessionAndLogoutClearsIt`; TC-AUTH-004 and 007 | Done |
| Profile view and update | `AccountController`, `PatientService::updateProfile` | `testProfileUpdateKeepsUserAndPatientInSync` | Done |
| Password change | `PatientService::changePassword` | `testChangePasswordRequiresCurrentPasswordAndKeepsThisSession` | Done |
| Recovery with expiring single-use tokens | `PasswordTokens`, `/forgot-password`, `/reset-password/{token}` | `PasswordTokensTest` (6 tests), `testResetLinkWorksOnceThroughHttp`; TC-AUTH-008 and 009 | Done (email delivery not configured; see limitations) |
| Validation errors and feedback | `Validator`, error summary, inline errors, flash messages | `testInvalidRegistrationSavesNothingAndReportsEachField`, `testInvalidGuestSubmissionReDisplaysErrorsWithoutSaving`; keyboard check TC-A11Y-001 | Done |
| Duplicate prevention | Unique email and mobile, checked first and enforced by unique keys | `testDuplicateEmailIsRejectedCaseInsensitively`, `testDuplicateMobileIsRejected`; TC-AUTH-002 and 003 | Done |
| Secure password storage | Argon2id | `testPasswordsUseArgon2idAndVerify` | Done |
| Server-side validation | Every service validates its own input | Functional test posts invalid data without JavaScript (`testAdminWorkflowThroughHttp` gets 422) | Done |

## 5.3 New-patient testing workflow

| Requirement | Implementation | Evidence | Status |
| --- | --- | --- | --- |
| Personal and contact details, test information, submission | `/test/new`, `TestingService::submitGuest` | `testGuestRequestCreatesAllRecordsInOneTransaction`; TC-REQ-001 | Done |
| Unique reference number | `VQ-YYMMDD-XXXXX` (Crockford base-32, unique key, retried on collision) | Reference format asserted; regex cases TC-REF-* | Done |
| View the submission result | `/test/confirmation/{ref}`, limited to the submitting session | `testNewPatientRequestsTracksAndCannotDoubleSubmit` | Done |
| Track status | `/track` (reference + mobile + date of birth), status rail and history | Same test; `testGuestTrackingNeedsAllThreeDetails` | Done |
| Saved to MySQL/MariaDB in a transaction | `Database::transaction` across patient, request, symptoms, history and notifications | No orphan rows on validation failure (`testInvalidTestDetailsAreRejected`) | Done |
| Duplicate submission prevention | Form token, idempotency key, client button lock | TC-REQ-003; `testSameIdempotencyKeyFindsTheExistingRequest` | Done |
| Minimal data | No ID or insurance fields; optional sex and email | Privacy page; form review | Done |

## 5.4 Existing-patient workflow

| Requirement | Implementation | Evidence | Status |
| --- | --- | --- | --- |
| Use saved details, request without re-entry | `/patient/requests/new`, `submitForUser` | `testAccountRequestUsesProfileAndShowsOwnReferenceOnDuplicate`, `testRegistrationSignsInAndExistingPatientRequestsWithSavedDetails` | Done |
| Review details, history and statuses | `/patient/requests`, `/patient/requests/{id}` | Browser check; functional test | Done |
| Authorised results and report | Result panel and printable report shown only after release | `testResultIsVisibleToPatientOnlyAfterRelease` | Done |
| Notifications | Dashboard and bell | `testFullWorkflowWithHistoryAndPatientNotifications` | Done |
| Ownership from the session, never a browser-supplied patient ID | `detailForPatient(userId, id)` | `testPatientCannotSeeOrCancelAnotherPatientsRequest`; mutation check | Done |

## 5.5 Administrative dashboard

| Requirement | Implementation | Evidence | Status |
| --- | --- | --- | --- |
| Metrics: patients, requests, new, assigned, collected, in progress, completed, results available, cancelled or rejected | `StatsService::dashboard`, pipeline and KPIs | `testDashboardMetricsComeFromTheDatabase` (zero on an empty database, counts after inserts); screenshot | Done |
| Recent activity and operational summaries | `recentActivity`, `workload`, "Needs attention", 30-day chart | Browser check | Done |
| Empty states | Shown when there is no data | `testStatisticsAreLabelledAndComputedFromData` (empty state); dashboard empty-state markup | Done |

## 5.6 Phlebotomist management

| Requirement | Implementation | Evidence | Status |
| --- | --- | --- | --- |
| Add, view, update, activate and deactivate | `PhlebotomistService`, `/admin/phlebotomists` | `CommunityAndAdminTest::testPhlebotomistLifecycle` | Done |
| Delete only when safe; soft delete when referenced | `delete()` returns `deleted` or `archived` | Same test | Done |
| Server-side authorisation | `role:admin` routes | Route-table test | Done |

## 5.7 Testing management and assignments

| Requirement | Implementation | Evidence | Status |
| --- | --- | --- | --- |
| View and review requests | `/admin/requests` (tabs by status), `/admin/requests/{id}` | Functional and browser checks | Done |
| Assign and update assignments | `assign()`, one current assignment | `testReassignmentKeepsExactlyOneCurrentAssignment`, `testInactivePhlebotomistCannotBeAssigned`; TC-WF-004 | Done |
| Sample collection, progress, completion, results | `recordCollection`, `startProcessing`, `recordResult`, `releaseResult` | `testFullWorkflowWithHistoryAndPatientNotifications`; TC-WF-001 | Done |
| Status history with actor and time | `test_status_history` | Same test asserts every row has an actor | Done |
| Documented transitions, invalid ones rejected on the server | `TestingService::TRANSITIONS`, compare-and-set update | `testInvalidTransitionsAreRefusedAndStatusIsUnchanged`, `testAllowedAndForbiddenTransitions`; TC-WF-002 | Done |
| Users cannot change their own status | Patients may only cancel, and only their own request before collection | `testPatientsAndGuestsCannotPerformStaffActions`, `testPatientCanCancelOnlyOwnRequestBeforeCollection` | Done |

## 5.8 Testing reports

| Requirement | Implementation | Evidence | Status |
| --- | --- | --- | --- |
| Search by reference, name, mobile, date range, status, phlebotomist | `ReportService::filters/search`, `/admin/reports` (also test type and state) | `testFiltersByNameMobileAndDateRange`, `testReferencePrefixSearch`, `testPhlebotomistFilterAndPaginationBounds`; TC-RPT-001 | Done |
| Pagination and limits | 5–100 per page; page clamped; export capped at 5,000 | Same test | Done |
| CSV without formula injection | `ReportService::csvCell` | `testCsvExportHasHeaderAndNeutralisedCells`; TC-RPT-002 | Done |
| Authorised data only | `role:admin` | `testRolesAreEnforcedOnTheServer` (export is 403 for patients) | Done |

## 5.9 Notifications

| Requirement | Implementation | Evidence | Status |
| --- | --- | --- | --- |
| Bell, unread count, list, mark read, timestamps, links | `NotificationService`, bell popover, `/notifications`, `/api/notifications` | `testNotificationsReadStateAndOwnership`, `testNotificationPollingAndMarkRead`; TC-NOTIF-001 | Done |
| Persisted | `notifications` table | Same | Done |
| Polling or push documented honestly | Polling every 30 s while visible (ARCHITECTURE.md) | Not applicable | Done |

## 5.10 Patient dashboard and history

| Requirement | Evidence | Status |
| --- | --- | --- |
| Current requests, status, history, results, notifications, profile, next actions | `/patient` browser check; functional tests | Done |
| Only own records | Ownership tests and mutation | Done |
| State-wise summary computed from data | Public state/UT table from `test_requests.state_code` with small-cell suppression | Done |

## 5.11 Comments and feedback

| Requirement | Implementation | Evidence | Status |
| --- | --- | --- | --- |
| Form, server-side validation, persistence | `CommentService::submit` | `testCommentFormEndToEnd` | Done |
| Escaped display | `e()` | `testCommentHtmlIsEscapedWhenPublished`; TC-COM-001 | Done |
| Moderation | Pre-moderation and `/admin/comments` | `testCommentIsStoredAsPendingAndNotPublicUntilApproved` | Done |
| Spam and abuse controls | Honeypot, signed timing, rate limit, link cap | `testHoneypotSubmissionIsDiscardedSilently`, `testTooFastForgedAndStaleStampsAreRefused`, `testCommentRateLimitAndLinkLimit` | Done |

## 5.12 Account administration

| Requirement | Implementation | Evidence | Status |
| --- | --- | --- | --- |
| Manage users without exposing hashes | `UserAdminService` never selects `password_hash` | `testUserAdministrationGuards` (asserts no hash column) | Done |
| Staff invitations | One-time set-password link | `testStaffInvitationCreatesAccountWithoutPassword` | Done |
| Reset tokens not reusable; no credential disclosure | See 5.2 | Same | Done |

## 6 STQA laboratory

| Requirement | Implementation | Evidence | Status |
| --- | --- | --- | --- |
| 6.1 Regex rules for name, email, Indian mobile, password, required, reference and more (PIN, employee code, barcode, Ct value, date) | `src/Validation/Rules.php`; same patterns in the browser | `RulesTest` (every catalogue case), Node consistency test, regex suite run | Done |
| 6.2 Catalogue with ID, module, title, preconditions, input, expected, actual, status, timestamp, notes and defect | `qa_test_cases` and `qa_run_results`; `/qa/cases`; [TEST_CASES.md](TEST_CASES.md) | 151 cases, all executed with recorded results | Done |
| 6.3 Tester: choose a rule, enter a value, see the match, description and format, inspect cases | `/qa/validator` (browser and server verdicts, agreement indicator) | Browser check; `testQaLabEvaluatesRulesAndRunsTheSuite` | Done |
| No unsafe user-supplied regex | Predefined rules only; 500-character cap | `testEvaluateRefusesOversizedInput` | Done |
| 6.4 Execution history, pass/fail summaries, module results, failures, timestamps, defect links; no fabricated data | `qa_runs`, `/qa/runs`, `/qa`; results only from runs | `QaLabTest` (9 tests); run #1 kept as evidence of the real failure | Done |
| 6.5 Defect register with all listed fields; demo records labelled | `defects` table, `/qa/defects`, `origin` column | `testDefectLifecycleRequiresResolutionNotes`; [BUG_REPORT.md](BUG_REPORT.md) | Done |

## 7 Backend architecture

| Requirement | Evidence | Status |
| --- | --- | --- |
| Layered structure (routes, auth, logic, data access, validation, views, config, migrations, seeds, tests, assets, docs) | [ARCHITECTURE.md](ARCHITECTURE.md) | Done |
| Normalised schema with keys, indexes, constraints, timestamps and deletion rules | [DATABASE.md](DATABASE.md), `database/migrations` | Done |
| PDO prepared statements, transactions, pagination | Code; security checklist | Done |
| `.env.example`, `.gitignore`, setup docs, safe defaults, config outside the web root | [ENVIRONMENT_SETUP.md](ENVIRONMENT_SETUP.md); `/.env` returns 404 | Done |
| Synthetic demo data; no predictable admin password | `DemoSeeder` (random per-install passwords, printed once) | Done |

## 8 to 10 Security, testing, accessibility

- **Security:** [SECURITY_CHECKLIST.md](SECURITY_CHECKLIST.md).
- **Testing:** [TEST_PLAN.md](TEST_PLAN.md), [TEST_EXECUTION_REPORT.md](TEST_EXECUTION_REPORT.md).
- **Accessibility:**
  - Lighthouse 100 on 8 audited pages.
  - A keyboard-only run of the request form (TC-A11Y-001).
  - Reduced-motion behaviour (TC-UI-002).
  - 375 px layout (TC-UI-001).
  - Chart data tables.
  - Known gaps are listed in the execution report.
