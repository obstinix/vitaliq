# Test cases

Generated from the STQA catalogue (`database/qa/regex-cases.json`, `database/qa/manual-cases.json`) and the latest recorded execution of each case in the Vitaliq QA lab, on 10 Oct 2026, 17:39.

- **Expected** was written before execution. **Actual**, **Status** and **Executed** come from the most recent run that executed the case (see [TEST_EXECUTION_REPORT.md](TEST_EXECUTION_REPORT.md)).
- **Regex cases** (115) run through the same server validator the forms use; the input is shown with invisible characters escaped.
- **Functional and security cases** (36) were either evaluated from named PHPUnit tests (`database/qa/automation-map.json`, run type *coverage*) or executed by hand in a browser and recorded in the lab (run type *manual*).

| Module | Cases |
| --- | --- |
| Accessibility | 1 |
| Address | 7 |
| Authentication | 22 |
| Authorisation | 4 |
| Comments | 2 |
| Contact details | 15 |
| Forms | 15 |
| Interface | 2 |
| Notifications | 1 |
| Phlebotomists | 5 |
| Registration | 37 |
| Reports | 2 |
| Resilience | 1 |
| Results | 8 |
| Sample collection | 6 |
| Security | 4 |
| Testing requests | 5 |
| Tracking | 10 |
| Workflow | 4 |
| **Total** | **151** |

## Accessibility

| ID | Type | Title | Preconditions | Input / steps | Expected | Actual | Status | Executed | Defect |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| TC-A11Y-001 | functional | Guest request can be completed with the keyboard only | None | Tab through and submit the new-patient form | Every control reachable with visible focus; errors linked from the summary | Completed /test/new with Tab, Shift+Tab, Space, typed text and Enter only (Chrome): reference VQ-261010-K2QQS created. Focus ring visible on each field; choosing home collection revealed the address field as the next tab stop. Empty submit focused Full name with 13 linked errors. | PASS | 10 Oct 2026 05:05 (run 8, manual) | — |

## Address

| ID | Type | Title | Preconditions | Input / steps | Expected | Actual | Status | Executed | Defect |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| TC-PIN-001 | positive | Six digits | Rule `pincode` | `"560001"` | Accepted by the validator | Accepted by the validator | PASS | 10 Oct 2026 17:39 (run 12, regex_suite) | — |
| TC-PIN-002 | whitespace | Space after third digit is removed | Rule `pincode` | `"110 001"` | Accepted by the validator | Accepted by the validator | PASS | 10 Oct 2026 17:39 (run 12, regex_suite) | — |
| TC-PIN-003 | negative | Leading zero | Rule `pincode` | `"056001"` | Rejected by the validator | Rejected: PIN code is not in the expected format (560001). | PASS | 10 Oct 2026 17:39 (run 12, regex_suite) | — |
| TC-PIN-004 | boundary | Five digits | Rule `pincode` | `"56001"` | Rejected by the validator | Rejected: PIN code is too short. | PASS | 10 Oct 2026 17:39 (run 12, regex_suite) | — |
| TC-PIN-005 | boundary | Seven digits | Rule `pincode` | `"5600011"` | Rejected by the validator | Rejected: PIN code is not in the expected format (560001). | PASS | 10 Oct 2026 17:39 (run 12, regex_suite) | — |
| TC-PIN-006 | malformed | Letters | Rule `pincode` | `"ABC123"` | Rejected by the validator | Rejected: PIN code is not in the expected format (560001). | PASS | 10 Oct 2026 17:39 (run 12, regex_suite) | — |
| TC-PIN-007 | unicode | Devanagari digits | Rule `pincode` | `"५६०००१"` | Rejected by the validator | Rejected: PIN code is not in the expected format (560001). | PASS | 10 Oct 2026 17:39 (run 12, regex_suite) | — |

## Authentication

| ID | Type | Title | Preconditions | Input / steps | Expected | Actual | Status | Executed | Defect |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| TC-AUTH-001 | functional | Registration creates an account and a patient record | Email and mobile not yet registered | Submit the registration form with valid details | User and patient rows exist; the user is signed in and sees the patient dashboard | Automated: RegistrationAndAuthTest::testRegistrationPersistsUserAndPatientWithHashedPassword, UserJourneysTest::testRegistrationSignsInAndExistingPatientRequestsWithSavedDetails passed | PASS | 10 Oct 2026 17:39 (run 11, coverage) | — |
| TC-AUTH-002 | negative | Duplicate email is rejected | An account exists for the email | Register again with the same email (any letter case) | Form re-displays with an 'already exists' error; no new rows | Automated: RegistrationAndAuthTest::testDuplicateEmailIsRejectedCaseInsensitively passed | PASS | 10 Oct 2026 17:39 (run 11, coverage) | — |
| TC-AUTH-003 | negative | Duplicate mobile number is rejected | An account uses the mobile number | Register with a different email and the same mobile | Mobile field error; no new rows | Automated: RegistrationAndAuthTest::testDuplicateMobileIsRejected passed | PASS | 10 Oct 2026 17:39 (run 11, coverage) | — |
| TC-AUTH-004 | positive | Valid credentials sign in | Active account | Sign in with the correct email and password | Redirect to the role's home page; session id regenerated | Automated: RegistrationAndAuthTest::testValidLoginRegeneratesSessionAndRecordsLogin, SecurityTest::testLoginRegeneratesSessionAndLogoutClearsIt passed | PASS | 10 Oct 2026 17:39 (run 11, coverage) | — |
| TC-AUTH-005 | negative | Wrong password shows a generic error | Active account | Sign in with a wrong password | Message does not reveal whether the email exists; not signed in | Automated: RegistrationAndAuthTest::testWrongPasswordAndUnknownEmailGiveTheSameMessage passed | PASS | 10 Oct 2026 17:39 (run 11, coverage) | — |
| TC-AUTH-006 | security | Account sign-in is limited after 5 failures | Active account | Fail sign-in 5 times, then use the correct password | Attempt refused with 'Too many sign-in attempts' for 15 minutes | Automated: RegistrationAndAuthTest::testAccountIsLockedAfterFiveFailures passed | PASS | 10 Oct 2026 17:39 (run 11, coverage) | — |
| TC-AUTH-007 | functional | Logout ends the session | Signed in | Sign out, then open /patient | Redirected to sign-in | Automated: SecurityTest::testLoginRegeneratesSessionAndLogoutClearsIt passed | PASS | 10 Oct 2026 17:39 (run 11, coverage) | — |
| TC-AUTH-008 | security | Reset link expires after 30 minutes | Reset link issued | Use the link 31 minutes later | Link unavailable page; password unchanged | Automated: PasswordTokensTest::testTokenExpiresAfterThirtyMinutes passed | PASS | 10 Oct 2026 17:39 (run 11, coverage) | — |
| TC-AUTH-009 | security | Reset link works only once | Reset link issued | Set a password, then use the same link again | Second use refused | Automated: PasswordTokensTest::testTokenResetsPasswordOnceAndSignsOutSessions, UserJourneysTest::testResetLinkWorksOnceThroughHttp passed | PASS | 10 Oct 2026 17:39 (run 11, coverage) | — |
| TC-AUTH-010 | security | Forgot-password response does not reveal accounts | None | Request a reset for a registered and an unregistered email | Identical confirmation for both | Automated: UserJourneysTest::testForgotPasswordGivesTheSameAnswerForUnknownEmails passed | PASS | 10 Oct 2026 17:39 (run 11, coverage) | — |
| TC-PWD-001 | positive | Passphrase with spaces and a digit | Rule `password` | `"correct horse 42"` | Accepted by the validator | Accepted by the validator | PASS | 10 Oct 2026 17:39 (run 12, regex_suite) | — |
| TC-PWD-002 | positive | Mixed punctuation | Rule `password` | `"Mango-Lassi-2026"` | Accepted by the validator | Accepted by the validator | PASS | 10 Oct 2026 17:39 (run 12, regex_suite) | — |
| TC-PWD-003 | boundary | Exactly 10 characters | Rule `password` | `"abcdefgh12"` | Accepted by the validator | Accepted by the validator | PASS | 10 Oct 2026 17:39 (run 12, regex_suite) | — |
| TC-PWD-004 | boundary | Nine characters | Rule `password` | `"abcdefg12"` | Rejected by the validator | Rejected: Password must be 10–128 characters and include at least one letter and one digit. | PASS | 10 Oct 2026 17:39 (run 12, regex_suite) | — |
| TC-PWD-005 | boundary | Exactly 128 characters | Rule `password` | `"a1a1a1a1a1a1a1a1a1a1a1a1a1a1a1a1a1a1a1a…` (128 chars) | Accepted by the validator | Accepted by the validator | PASS | 10 Oct 2026 17:39 (run 12, regex_suite) | — |
| TC-PWD-006 | boundary | 129 characters | Rule `password` | `"a1a1a1a1a1a1a1a1a1a1a1a1a1a1a1a1a1a1a1a…` (129 chars) | Rejected by the validator | Rejected: Password must be 10–128 characters and include at least one letter and one digit. | PASS | 10 Oct 2026 17:39 (run 12, regex_suite) | — |
| TC-PWD-007 | negative | Letters only | Rule `password` | `"onlyletterspassword"` | Rejected by the validator | Rejected: Password must be 10–128 characters and include at least one letter and one digit. | PASS | 10 Oct 2026 17:39 (run 12, regex_suite) | — |
| TC-PWD-008 | negative | Digits only | Rule `password` | `"12345678901234"` | Rejected by the validator | Rejected: Password must be 10–128 characters and include at least one letter and one digit. | PASS | 10 Oct 2026 17:39 (run 12, regex_suite) | — |
| TC-PWD-009 | negative | Common password on the blocklist | Rule `password` | `"password123"` | Rejected by the validator | Rejected: That password is too common. Choose something less predictable. | PASS | 10 Oct 2026 17:39 (run 12, regex_suite) | — |
| TC-PWD-010 | unicode | Letters from another script count as letters | Rule `password` | `"पासवर्ड2026सुरक्षित"` | Accepted by the validator | Accepted by the validator | PASS | 10 Oct 2026 17:39 (run 12, regex_suite) | — |
| TC-PWD-011 | whitespace | Spaces and one digit, no letter | Rule `password` | `"          1"` | Rejected by the validator | Rejected: Password must be 10–128 characters and include at least one letter and one digit. | PASS | 10 Oct 2026 17:39 (run 12, regex_suite) | — |
| TC-PWD-012 | malformed | Embedded line break | Rule `password` | `"abcdefgh\n12"` | Rejected by the validator | Rejected: Password must be 10–128 characters and include at least one letter and one digit. | PASS | 10 Oct 2026 17:39 (run 12, regex_suite) | — |

## Authorisation

| ID | Type | Title | Preconditions | Input / steps | Expected | Actual | Status | Executed | Defect |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| TC-AUTHZ-001 | security | Patient cannot open the admin dashboard | Signed in as a patient | Open /admin | HTTP 403 page | Automated: SecurityTest::testRolesAreEnforcedOnTheServer passed | PASS | 10 Oct 2026 17:39 (run 11, coverage) | — |
| TC-AUTHZ-002 | security | Patient cannot view another patient's request | Two patient accounts with requests | Patient A opens /patient/requests/{id of B} | HTTP 404; no data from B shown | Automated: SecurityTest::testPatientCannotSeeOrCancelAnotherPatientsRequest, TestingWorkflowTest::testDetailForPatientIsScopedToTheOwner passed | PASS | 10 Oct 2026 17:39 (run 11, coverage) | — |
| TC-AUTHZ-003 | security | Phlebotomist cannot record another phlebotomist's collection | Request assigned to phlebotomist X | Phlebotomist Y posts to /staff/tasks/{id}/collect | HTTP 404; status unchanged | Automated: SecurityTest::testPhlebotomistCannotActOnAnotherPhlebotomistsCollection, TestingWorkflowTest::testOnlyTheAssignedPhlebotomistCanRecordCollection passed | PASS | 10 Oct 2026 17:39 (run 11, coverage) | — |
| TC-AUTHZ-004 | security | Guest confirmation page is limited to the submitting session | A guest request exists | Open /test/confirmation/{ref} in a new session | HTTP 404 | Automated: UserJourneysTest::testNewPatientRequestsTracksAndCannotDoubleSubmit passed | PASS | 10 Oct 2026 17:39 (run 11, coverage) | — |

## Comments

| ID | Type | Title | Preconditions | Input / steps | Expected | Actual | Status | Executed | Defect |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| TC-COM-001 | security | Comment is hidden until approved and shown escaped | None | Submit a comment containing <b>hi</b>; approve it | Not public while pending; after approval shown as literal text | Automated: CommunityAndAdminTest::testCommentIsStoredAsPendingAndNotPublicUntilApproved, PublicSiteTest::testCommentHtmlIsEscapedWhenPublished passed | PASS | 10 Oct 2026 17:39 (run 11, coverage) | — |
| TC-COM-002 | security | Honeypot submission is discarded | None | Submit with the hidden website field filled | Success shown; nothing stored | Automated: CommunityAndAdminTest::testHoneypotSubmissionIsDiscardedSilently passed | PASS | 10 Oct 2026 17:39 (run 11, coverage) | — |

## Contact details

| ID | Type | Title | Preconditions | Input / steps | Expected | Actual | Status | Executed | Defect |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| TC-MOB-001 | positive | Ten digits | Rule `indian_mobile` | `"9876543210"` | Accepted by the validator | Accepted by the validator | PASS | 10 Oct 2026 17:39 (run 12, regex_suite) | — |
| TC-MOB-002 | positive | +91 with spaces | Rule `indian_mobile` | `"+91 98765 43210"` | Accepted by the validator | Accepted by the validator | PASS | 10 Oct 2026 17:39 (run 12, regex_suite) | — |
| TC-MOB-003 | positive | +91 with hyphens | Rule `indian_mobile` | `"+91-98765-43210"` | Accepted by the validator | Accepted by the validator | PASS | 10 Oct 2026 17:39 (run 12, regex_suite) | — |
| TC-MOB-004 | positive | Trunk prefix 0 | Rule `indian_mobile` | `"09876543210"` | Accepted by the validator | Accepted by the validator | PASS | 10 Oct 2026 17:39 (run 12, regex_suite) | — |
| TC-MOB-005 | positive | 91 prefix without plus | Rule `indian_mobile` | `"919876543210"` | Accepted by the validator | Accepted by the validator | PASS | 10 Oct 2026 17:39 (run 12, regex_suite) | — |
| TC-MOB-006 | positive | 0091 international prefix | Rule `indian_mobile` | `"0091 9876543210"` | Accepted by the validator | Accepted by the validator | PASS | 10 Oct 2026 17:39 (run 12, regex_suite) | — |
| TC-MOB-007 | boundary | First digit 6 (lowest allowed) | Rule `indian_mobile` | `"6000000000"` | Accepted by the validator | Accepted by the validator | PASS | 10 Oct 2026 17:39 (run 12, regex_suite) | — |
| TC-MOB-008 | boundary | First digit 5 is not a mobile series | Rule `indian_mobile` | `"5876543210"` | Rejected by the validator | Rejected: Indian mobile number is not in the expected format (98765 43210, +91 98765 43210 or 09876543210). | PASS | 10 Oct 2026 17:39 (run 12, regex_suite) | — |
| TC-MOB-009 | boundary | Nine digits | Rule `indian_mobile` | `"987654321"` | Rejected by the validator | Rejected: Indian mobile number is too short. | PASS | 10 Oct 2026 17:39 (run 12, regex_suite) | — |
| TC-MOB-010 | boundary | Eleven digits without prefix | Rule `indian_mobile` | `"98765432101"` | Rejected by the validator | Rejected: Indian mobile number is not in the expected format (98765 43210, +91 98765 43210 or 09876543210). | PASS | 10 Oct 2026 17:39 (run 12, regex_suite) | — |
| TC-MOB-011 | negative | Letters | Rule `indian_mobile` | `"98765ABCDE"` | Rejected by the validator | Rejected: Indian mobile number is not in the expected format (98765 43210, +91 98765 43210 or 09876543210). | PASS | 10 Oct 2026 17:39 (run 12, regex_suite) | — |
| TC-MOB-012 | negative | UK number | Rule `indian_mobile` | `"+44 7911 123456"` | Rejected by the validator | Rejected: Indian mobile number is not in the expected format (98765 43210, +91 98765 43210 or 09876543210). | PASS | 10 Oct 2026 17:39 (run 12, regex_suite) | — |
| TC-MOB-013 | whitespace | Surrounding spaces are trimmed | Rule `indian_mobile` | `" 9876543210 "` | Accepted by the validator | Accepted by the validator | PASS | 10 Oct 2026 17:39 (run 12, regex_suite) | — |
| TC-MOB-014 | whitespace | Two spaces between groups | Rule `indian_mobile` | `"98765  43210"` | Rejected by the validator | Rejected: Indian mobile number is not in the expected format (98765 43210, +91 98765 43210 or 09876543210). | PASS | 10 Oct 2026 17:39 (run 12, regex_suite) | — |
| TC-MOB-015 | unicode | Devanagari digits | Rule `indian_mobile` | `"९८७६५४३२१०"` | Rejected by the validator | Rejected: Indian mobile number is not in the expected format (98765 43210, +91 98765 43210 or 09876543210). | PASS | 10 Oct 2026 17:39 (run 12, regex_suite) | — |

## Forms

| ID | Type | Title | Preconditions | Input / steps | Expected | Actual | Status | Executed | Defect |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| TC-DATE-001 | positive | Valid date | Rule `iso_date` | `"2026-10-10"` | Accepted by the validator | Accepted by the validator | PASS | 10 Oct 2026 17:39 (run 12, regex_suite) | — |
| TC-DATE-002 | boundary | Leap day in a leap year | Rule `iso_date` | `"2024-02-29"` | Accepted by the validator | Accepted by the validator | PASS | 10 Oct 2026 17:39 (run 12, regex_suite) | — |
| TC-DATE-003 | boundary | Leap day in a common year | Rule `iso_date` | `"2026-02-29"` | Rejected by the validator | Rejected: That date does not exist. | PASS | 10 Oct 2026 17:39 (run 12, regex_suite) | — |
| TC-DATE-004 | negative | 30 February | Rule `iso_date` | `"2026-02-30"` | Rejected by the validator | Rejected: That date does not exist. | PASS | 10 Oct 2026 17:39 (run 12, regex_suite) | — |
| TC-DATE-005 | malformed | Day-first format | Rule `iso_date` | `"10/10/2026"` | Rejected by the validator | Rejected: Date is not in the expected format (2026-10-10). | PASS | 10 Oct 2026 17:39 (run 12, regex_suite) | — |
| TC-DATE-006 | negative | Month 13 | Rule `iso_date` | `"2026-13-01"` | Rejected by the validator | Rejected: Date is not in the expected format (2026-10-10). | PASS | 10 Oct 2026 17:39 (run 12, regex_suite) | — |
| TC-DATE-007 | malformed | Missing zero padding | Rule `iso_date` | `"2026-1-5"` | Rejected by the validator | Rejected: Date is too short. | PASS | 10 Oct 2026 17:39 (run 12, regex_suite) | — |
| TC-RQD-001 | positive | Single letter | Rule `required` | `"x"` | Accepted by the validator | Accepted by the validator | PASS | 10 Oct 2026 17:39 (run 12, regex_suite) | — |
| TC-RQD-002 | positive | Digit zero counts as content | Rule `required` | `"0"` | Accepted by the validator | Accepted by the validator | PASS | 10 Oct 2026 17:39 (run 12, regex_suite) | — |
| TC-RQD-003 | whitespace | Text with surrounding spaces | Rule `required` | `" ok "` | Accepted by the validator | Accepted by the validator | PASS | 10 Oct 2026 17:39 (run 12, regex_suite) | — |
| TC-RQD-004 | whitespace | Spaces only | Rule `required` | `"   "` | Rejected by the validator | Rejected: Required field is empty. | PASS | 10 Oct 2026 17:39 (run 12, regex_suite) | — |
| TC-RQD-005 | whitespace | Tab and newline only | Rule `required` | `"\t\n"` | Rejected by the validator | Rejected: Required field is empty. | PASS | 10 Oct 2026 17:39 (run 12, regex_suite) | — |
| TC-RQD-006 | unicode | Zero-width space only | Rule `required` | `"\u{200B}"` | Rejected by the validator | Rejected: Required field is empty. | PASS | 10 Oct 2026 17:39 (run 12, regex_suite) | — |
| TC-RQD-007 | unicode | No-break space only | Rule `required` | `"\u{00A0}"` | Rejected by the validator | Rejected: Required field is empty. | PASS | 10 Oct 2026 17:39 (run 12, regex_suite) | — |
| TC-RQD-008 | unicode | Ellipsis punctuation counts as content | Rule `required` | `"…"` | Accepted by the validator | Accepted by the validator | PASS | 10 Oct 2026 17:39 (run 12, regex_suite) | — |

## Interface

| ID | Type | Title | Preconditions | Input / steps | Expected | Actual | Status | Executed | Defect |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| TC-UI-001 | functional | No horizontal scrolling at 375 px | None | Open key pages at 375 px wide | Document width equals viewport width | At 375x812: document width equalled the viewport (375 px) on /, /symptoms, /testing, /statistics, /contact, /test/new, /track, /register (Chrome DevTools mobile emulation) and on /community, /patient, /patient/requests, /patient/requests/85, /account, /notifications (in-app browser), after fixing DEF-005 and DEF-006. | PASS | 10 Oct 2026 05:05 (run 6, manual) | — |
| TC-UI-002 | functional | Reduced motion leaves all content visible | prefers-reduced-motion: reduce | Open the homepage | No reveal animations; all content visible immediately | Simulated prefers-reduced-motion: reduce by overriding matchMedia before page scripts (Chrome DevTools init script): no motion-ok class, all 16 reveal elements opacity 1 at load, counters showed final values. CSS @media rule not exercised by this method. | PASS | 10 Oct 2026 05:05 (run 7, manual) | — |

## Notifications

| ID | Type | Title | Preconditions | Input / steps | Expected | Actual | Status | Executed | Defect |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| TC-NOTIF-001 | functional | New request notifies administrators and can be marked read | Active admin | Submit a request; admin marks the notification read | Unread count rises then falls; read time stored | Automated: TestingWorkflowTest::testGuestRequestCreatesAllRecordsInOneTransaction, UserJourneysTest::testNotificationPollingAndMarkRead, ReportsAndNotificationsTest::testNotificationsReadStateAndOwnership passed | PASS | 10 Oct 2026 17:39 (run 11, coverage) | — |

## Phlebotomists

| ID | Type | Title | Preconditions | Input / steps | Expected | Actual | Status | Executed | Defect |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| TC-EMP-001 | positive | Issued format | Rule `employee_code` | `"PHL-0007"` | Accepted by the validator | Accepted by the validator | PASS | 10 Oct 2026 17:39 (run 12, regex_suite) | — |
| TC-EMP-002 | positive | Lower case is normalised | Rule `employee_code` | `"phl-1234"` | Accepted by the validator | Accepted by the validator | PASS | 10 Oct 2026 17:39 (run 12, regex_suite) | — |
| TC-EMP-003 | boundary | Too few digits | Rule `employee_code` | `"PHL-7"` | Rejected by the validator | Rejected: Phlebotomist employee code is too short. | PASS | 10 Oct 2026 17:39 (run 12, regex_suite) | — |
| TC-EMP-004 | malformed | Missing hyphen | Rule `employee_code` | `"PHL0007"` | Rejected by the validator | Rejected: Phlebotomist employee code is too short. | PASS | 10 Oct 2026 17:39 (run 12, regex_suite) | — |
| TC-EMP-005 | negative | Wrong prefix | Rule `employee_code` | `"LAB-0007"` | Rejected by the validator | Rejected: Phlebotomist employee code is not in the expected format (PHL-0007). | PASS | 10 Oct 2026 17:39 (run 12, regex_suite) | — |

## Registration

| ID | Type | Title | Preconditions | Input / steps | Expected | Actual | Status | Executed | Defect |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| TC-EMAIL-001 | positive | Typical address | Rule `email` | `"ananya.iyer@example.com"` | Accepted by the validator | Accepted by the validator | PASS | 10 Oct 2026 17:39 (run 12, regex_suite) | — |
| TC-EMAIL-002 | positive | Plus tag and sub-domain | Rule `email` | `"first+tag@sub.example.in"` | Accepted by the validator | Accepted by the validator | PASS | 10 Oct 2026 17:39 (run 12, regex_suite) | — |
| TC-EMAIL-003 | positive | Upper case is normalised | Rule `email` | `"Ananya@Example.COM"` | Accepted by the validator | Accepted by the validator | PASS | 10 Oct 2026 17:39 (run 12, regex_suite) | — |
| TC-EMAIL-004 | whitespace | Surrounding spaces are trimmed | Rule `email` | `"  ananya@example.com  "` | Accepted by the validator | Accepted by the validator | PASS | 10 Oct 2026 17:39 (run 12, regex_suite) | — |
| TC-EMAIL-005 | negative | Missing @ | Rule `email` | `"ananya.example.com"` | Rejected by the validator | Rejected: Email address is not in the expected format (name@example.com — up to 254 characters). | PASS | 10 Oct 2026 17:39 (run 12, regex_suite) | — |
| TC-EMAIL-006 | negative | Missing top-level domain | Rule `email` | `"ananya@example"` | Rejected by the validator | Rejected: Email address is not in the expected format (name@example.com — up to 254 characters). | PASS | 10 Oct 2026 17:39 (run 12, regex_suite) | — |
| TC-EMAIL-007 | malformed | Two @ signs | Rule `email` | `"ananya@@example.com"` | Rejected by the validator | Rejected: Email address is not in the expected format (name@example.com — up to 254 characters). | PASS | 10 Oct 2026 17:39 (run 12, regex_suite) | — |
| TC-EMAIL-008 | whitespace | Space inside local part | Rule `email` | `"an anya@example.com"` | Rejected by the validator | Rejected: Email address is not in the expected format (name@example.com — up to 254 characters). | PASS | 10 Oct 2026 17:39 (run 12, regex_suite) | — |
| TC-EMAIL-009 | malformed | Consecutive dots in local part | Rule `email` | `"ananya..iyer@example.com"` | Rejected by the validator | Rejected: Email address is not in the expected format (name@example.com — up to 254 characters). | PASS | 10 Oct 2026 17:39 (run 12, regex_suite) | — |
| TC-EMAIL-010 | malformed | Leading dot in local part | Rule `email` | `".ananya@example.com"` | Rejected by the validator | Rejected: Email address is not in the expected format (name@example.com — up to 254 characters). | PASS | 10 Oct 2026 17:39 (run 12, regex_suite) | — |
| TC-EMAIL-011 | malformed | Domain label ending in hyphen | Rule `email` | `"ananya@example-.com"` | Rejected by the validator | Rejected: Email address is not in the expected format (name@example.com — up to 254 characters). | PASS | 10 Oct 2026 17:39 (run 12, regex_suite) | — |
| TC-EMAIL-012 | unicode | Non-ASCII local part (not supported) | Rule `email` | `"अनन्या@example.com"` | Rejected by the validator | Rejected: Email address is not in the expected format (name@example.com — up to 254 characters). | PASS | 10 Oct 2026 17:39 (run 12, regex_suite) | — |
| TC-EMAIL-013 | boundary | Longer than 254 characters | Rule `email` | `"aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa…` (260 chars) | Rejected by the validator | Rejected: Email address is too long. | PASS | 10 Oct 2026 17:39 (run 12, regex_suite) | — |
| TC-EMAIL-014 | security | HTML in address | Rule `email` | `"<script>@example.com"` | Rejected by the validator | Rejected: Email address is not in the expected format (name@example.com — up to 254 characters). | PASS | 10 Oct 2026 17:39 (run 12, regex_suite) | — |
| TC-NAME-001 | positive | Two-part Latin name | Rule `full_name` | `"Ananya Iyer"` | Accepted by the validator | Accepted by the validator | PASS | 10 Oct 2026 17:39 (run 12, regex_suite) | — |
| TC-NAME-002 | positive | Single name (mononym) | Rule `full_name` | `"Rahul"` | Accepted by the validator | Accepted by the validator | PASS | 10 Oct 2026 17:39 (run 12, regex_suite) | — |
| TC-NAME-003 | positive | Apostrophe in surname | Rule `full_name` | `"Sean O'Brien"` | Accepted by the validator | Accepted by the validator | PASS | 10 Oct 2026 17:39 (run 12, regex_suite) | — |
| TC-NAME-004 | positive | Hyphen and apostrophe | Rule `full_name` | `"Mary-Jane D'Souza"` | Accepted by the validator | Accepted by the validator | PASS | 10 Oct 2026 17:39 (run 12, regex_suite) | — |
| TC-NAME-005 | positive | Initials with full stops | Rule `full_name` | `"A. P. J. Abdul Kalam"` | Accepted by the validator | Accepted by the validator | PASS | 10 Oct 2026 17:39 (run 12, regex_suite) | — |
| TC-NAME-006 | unicode | Devanagari script | Rule `full_name` | `"प्रिया शर्मा"` | Accepted by the validator | Accepted by the validator | PASS | 10 Oct 2026 17:39 (run 12, regex_suite) | — |
| TC-NAME-007 | unicode | Tamil script (ends in a vowel sign) | Rule `full_name` | `"கார்த்திக் ராஜா"` | Accepted by the validator | Accepted by the validator | PASS | 10 Oct 2026 17:39 (run 12, regex_suite) | — |
| TC-NAME-008 | unicode | Accented Latin letters | Rule `full_name` | `"Zoë Fernández"` | Accepted by the validator | Accepted by the validator | PASS | 10 Oct 2026 17:39 (run 12, regex_suite) | — |
| TC-NAME-009 | unicode | Emoji is not a letter | Rule `full_name` | `"Ananya 😊"` | Rejected by the validator | Rejected: Full name is not in the expected format (2–100 characters, e.g. “Ananya Iyer”, “O’Brien”, “प्रिया शर्मा”). | PASS | 10 Oct 2026 17:39 (run 12, regex_suite) | — |
| TC-NAME-010 | boundary | Minimum length: 2 characters | Rule `full_name` | `"Li"` | Accepted by the validator | Accepted by the validator | PASS | 10 Oct 2026 17:39 (run 12, regex_suite) | — |
| TC-NAME-011 | boundary | Below minimum: 1 character | Rule `full_name` | `"A"` | Rejected by the validator | Rejected: Full name is too short. | PASS | 10 Oct 2026 17:39 (run 12, regex_suite) | — |
| TC-NAME-012 | boundary | Maximum length: 100 characters | Rule `full_name` | `"AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA…` (100 chars) | Accepted by the validator | Accepted by the validator | PASS | 10 Oct 2026 17:39 (run 12, regex_suite) | — |
| TC-NAME-013 | boundary | Above maximum: 101 characters | Rule `full_name` | `"AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA…` (101 chars) | Rejected by the validator | Rejected: Full name is too long. | PASS | 10 Oct 2026 17:39 (run 12, regex_suite) | — |
| TC-NAME-014 | negative | Digits are rejected | Rule `full_name` | `"R2D2"` | Rejected by the validator | Rejected: Full name is not in the expected format (2–100 characters, e.g. “Ananya Iyer”, “O’Brien”, “प्रिया शर्मा”). | PASS | 10 Oct 2026 17:39 (run 12, regex_suite) | — |
| TC-NAME-015 | negative | Underscore is rejected | Rule `full_name` | `"Ravi_K"` | Rejected by the validator | Rejected: Full name is not in the expected format (2–100 characters, e.g. “Ananya Iyer”, “O’Brien”, “प्रिया शर्मा”). | PASS | 10 Oct 2026 17:39 (run 12, regex_suite) | — |
| TC-NAME-016 | malformed | Trailing hyphen | Rule `full_name` | `"Ananya-"` | Rejected by the validator | Rejected: Full name is not in the expected format (2–100 characters, e.g. “Ananya Iyer”, “O’Brien”, “प्रिया शर्मा”). | PASS | 10 Oct 2026 17:39 (run 12, regex_suite) | — |
| TC-NAME-017 | malformed | Leading apostrophe | Rule `full_name` | `"'Ananya"` | Rejected by the validator | Rejected: Full name is not in the expected format (2–100 characters, e.g. “Ananya Iyer”, “O’Brien”, “प्रिया शर्मा”). | PASS | 10 Oct 2026 17:39 (run 12, regex_suite) | — |
| TC-NAME-018 | whitespace | Leading and trailing spaces are trimmed | Rule `full_name` | `"  Ananya Iyer  "` | Accepted by the validator | Accepted by the validator | PASS | 10 Oct 2026 17:39 (run 12, regex_suite) | — |
| TC-NAME-019 | whitespace | Repeated inner spaces are collapsed | Rule `full_name` | `"Ananya     Iyer"` | Accepted by the validator | Accepted by the validator | PASS | 10 Oct 2026 17:39 (run 12, regex_suite) | — |
| TC-NAME-020 | whitespace | Tab between names is treated as a space | Rule `full_name` | `"Ananya\tIyer"` | Accepted by the validator | Accepted by the validator | PASS | 10 Oct 2026 17:39 (run 12, regex_suite) | — |
| TC-NAME-021 | whitespace | Spaces only | Rule `full_name` | `"     "` | Rejected by the validator | Rejected: Full name is required. | PASS | 10 Oct 2026 17:39 (run 12, regex_suite) | — |
| TC-NAME-022 | security | Script tag | Rule `full_name` | `"<script>alert(1)</script>"` | Rejected by the validator | Rejected: Full name is not in the expected format (2–100 characters, e.g. “Ananya Iyer”, “O’Brien”, “प्रिया शर्मा”). | PASS | 10 Oct 2026 17:39 (run 12, regex_suite) | — |
| TC-NAME-023 | security | SQL injection fragment | Rule `full_name` | `"Robert'); DROP TABLE users;--"` | Rejected by the validator | Rejected: Full name is not in the expected format (2–100 characters, e.g. “Ananya Iyer”, “O’Brien”, “प्रिया शर्मा”). | PASS | 10 Oct 2026 17:39 (run 12, regex_suite) | — |

## Reports

| ID | Type | Title | Preconditions | Input / steps | Expected | Actual | Status | Executed | Defect |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| TC-RPT-001 | functional | Search by mobile number and date range | Requests on several dates | Filter by partial mobile and a date range | Only matching requests listed; count correct | Automated: ReportsAndNotificationsTest::testFiltersByNameMobileAndDateRange passed | PASS | 10 Oct 2026 17:39 (run 11, coverage) | — |
| TC-RPT-002 | security | CSV export neutralises spreadsheet formulas | A value starting with = exists | Export CSV | Cell is prefixed with an apostrophe | Automated: ReportsAndNotificationsTest::testCsvExportHasHeaderAndNeutralisedCells, SecurityPrimitivesTest::testCsvCellsAreNeutralised passed | PASS | 10 Oct 2026 17:39 (run 11, coverage) | — |

## Resilience

| ID | Type | Title | Preconditions | Input / steps | Expected | Actual | Status | Executed | Defect |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| TC-DB-001 | functional | Database outage shows a safe 503 page | Database unreachable | Open a page that needs the database | HTTP 503 with a friendly message and no stack trace | Automated: DatabaseFailureTest::testPagesNeedingTheDatabaseReturnAFriendly503, DatabaseFailureTest::testJsonEndpointsReturnJson503 passed | PASS | 10 Oct 2026 17:39 (run 11, coverage) | — |

## Results

| ID | Type | Title | Preconditions | Input / steps | Expected | Actual | Status | Executed | Defect |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| TC-CT-001 | positive | Typical value with one decimal | Rule `ct_value` | `"24.5"` | Accepted by the validator | Accepted by the validator | PASS | 10 Oct 2026 17:39 (run 12, regex_suite) | — |
| TC-CT-002 | boundary | Zero | Rule `ct_value` | `"0"` | Accepted by the validator | Accepted by the validator | PASS | 10 Oct 2026 17:39 (run 12, regex_suite) | — |
| TC-CT-003 | boundary | Upper bound 45 | Rule `ct_value` | `"45"` | Accepted by the validator | Accepted by the validator | PASS | 10 Oct 2026 17:39 (run 12, regex_suite) | — |
| TC-CT-004 | boundary | Above the upper bound with a decimal | Rule `ct_value` | `"45.9"` | Rejected by the validator | Rejected: Ct value is not in the expected format (24.5). | PASS | 10 Oct 2026 17:39 (run 12, regex_suite) | DEF-004 |
| TC-CT-005 | boundary | 46 is out of range | Rule `ct_value` | `"46"` | Rejected by the validator | Rejected: Ct value is not in the expected format (24.5). | PASS | 10 Oct 2026 17:39 (run 12, regex_suite) | — |
| TC-CT-006 | negative | Negative value | Rule `ct_value` | `"-3"` | Rejected by the validator | Rejected: Ct value is not in the expected format (24.5). | PASS | 10 Oct 2026 17:39 (run 12, regex_suite) | — |
| TC-CT-007 | malformed | Two decimal places | Rule `ct_value` | `"24.55"` | Rejected by the validator | Rejected: Ct value is too long. | PASS | 10 Oct 2026 17:39 (run 12, regex_suite) | — |
| TC-CT-008 | negative | Text | Rule `ct_value` | `"abc"` | Rejected by the validator | Rejected: Ct value is not in the expected format (24.5). | PASS | 10 Oct 2026 17:39 (run 12, regex_suite) | — |

## Sample collection

| ID | Type | Title | Preconditions | Input / steps | Expected | Actual | Status | Executed | Defect |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| TC-BAR-001 | positive | Typical label | Rule `sample_barcode` | `"VQS20261010A"` | Accepted by the validator | Accepted by the validator | PASS | 10 Oct 2026 17:39 (run 12, regex_suite) | — |
| TC-BAR-002 | boundary | Minimum length: 8 | Rule `sample_barcode` | `"12345678"` | Accepted by the validator | Accepted by the validator | PASS | 10 Oct 2026 17:39 (run 12, regex_suite) | — |
| TC-BAR-003 | boundary | Seven characters | Rule `sample_barcode` | `"1234567"` | Rejected by the validator | Rejected: Sample tube barcode is too short. | PASS | 10 Oct 2026 17:39 (run 12, regex_suite) | — |
| TC-BAR-004 | boundary | Fifteen characters | Rule `sample_barcode` | `"ABCDEFGHIJKLMNO"` | Rejected by the validator | Rejected: Sample tube barcode is too long. | PASS | 10 Oct 2026 17:39 (run 12, regex_suite) | — |
| TC-BAR-005 | malformed | Hyphen is not allowed | Rule `sample_barcode` | `"VQS-2026"` | Rejected by the validator | Rejected: Sample tube barcode is not in the expected format (VQS20261010A). | PASS | 10 Oct 2026 17:39 (run 12, regex_suite) | — |
| TC-BAR-006 | positive | Lower case is normalised | Rule `sample_barcode` | `"vqs20261010a"` | Accepted by the validator | Accepted by the validator | PASS | 10 Oct 2026 17:39 (run 12, regex_suite) | — |

## Security

| ID | Type | Title | Preconditions | Input / steps | Expected | Actual | Status | Executed | Defect |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| TC-SEC-001 | security | SQL injection in report filters is harmless | Signed in as admin | Search name = ' OR 1=1 -- | No error; no rows unless a name literally contains the text | Automated: ReportsAndNotificationsTest::testInjectionAttemptsAreInertAndWildcardsAreLiteral, SecurityTest::testReportSearchReflectsInputSafely passed | PASS | 10 Oct 2026 17:39 (run 11, coverage) | — |
| TC-SEC-002 | security | POST without a CSRF token is refused | None | POST /login without _token | HTTP 419 | Automated: SecurityTest::testPostWithoutOrWithWrongCsrfTokenIs419, SecurityTest::testCsrfTokenFromAnotherSessionIsRejected passed | PASS | 10 Oct 2026 17:39 (run 11, coverage) | — |
| TC-SEC-003 | security | Security headers are sent | None | Request any page | CSP, X-Content-Type-Options, X-Frame-Options and Referrer-Policy present | Automated: PublicSiteTest::testPublicPageRendersWithSecurityHeaders passed | PASS | 10 Oct 2026 17:39 (run 11, coverage) | — |
| TC-SEC-004 | security | Configuration files are not served | Server running with public/ as web root | GET /.env | HTTP 404; no file contents | PHP built-in server with public/ as web root: GET /.env, /../.env and /router.php each returned 404 (curl), no file contents. | PASS | 10 Oct 2026 05:05 (run 9, manual) | — |

## Testing requests

| ID | Type | Title | Preconditions | Input / steps | Expected | Actual | Status | Executed | Defect |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| TC-REQ-001 | functional | Guest request is saved with a unique reference | None | Submit the new-patient form | Patient, request, symptoms and history rows created; reference matches VQ-YYMMDD-XXXXX | Automated: TestingWorkflowTest::testGuestRequestCreatesAllRecordsInOneTransaction passed | PASS | 10 Oct 2026 17:39 (run 11, coverage) | — |
| TC-REQ-002 | functional | Existing patient request uses saved details | Signed in as a patient | Submit the request form (no personal fields) | Request linked to the account's patient record | Automated: TestingWorkflowTest::testAccountRequestUsesProfileAndShowsOwnReferenceOnDuplicate, UserJourneysTest::testRegistrationSignsInAndExistingPatientRequestsWithSavedDetails passed | PASS | 10 Oct 2026 17:39 (run 11, coverage) | — |
| TC-REQ-003 | functional | Repeated submission of one form creates one request | None | Submit the same form token twice | One request; the repeat shows the first confirmation | Automated: UserJourneysTest::testNewPatientRequestsTracksAndCannotDoubleSubmit, TestingWorkflowTest::testSameIdempotencyKeyFindsTheExistingRequest passed | PASS | 10 Oct 2026 17:39 (run 11, coverage) | — |
| TC-REQ-004 | negative | Second active request of the same test type is refused | Patient has an active RT-PCR request | Request another RT-PCR test | Validation message; no new request | Automated: TestingWorkflowTest::testSecondActiveRequestOfSameTypeIsRefusedWithoutLeakingReference passed | PASS | 10 Oct 2026 17:39 (run 11, coverage) | — |
| TC-REQ-005 | boundary | Preferred date in the past is refused | None | Submit with yesterday as the preferred date | Preferred date error | Automated: TestingWorkflowTest::testInvalidTestDetailsAreRejected passed | PASS | 10 Oct 2026 17:39 (run 11, coverage) | — |

## Tracking

| ID | Type | Title | Preconditions | Input / steps | Expected | Actual | Status | Executed | Defect |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| TC-REF-001 | positive | Issued format | Rule `reference` | `"VQ-261010-7K3MP"` | Accepted by the validator | Accepted by the validator | PASS | 10 Oct 2026 17:39 (run 12, regex_suite) | — |
| TC-REF-002 | positive | Lower case is normalised | Rule `reference` | `"vq-261010-7k3mp"` | Accepted by the validator | Accepted by the validator | PASS | 10 Oct 2026 17:39 (run 12, regex_suite) | — |
| TC-REF-003 | whitespace | Surrounding spaces are trimmed | Rule `reference` | `" VQ-261010-7K3MP "` | Accepted by the validator | Accepted by the validator | PASS | 10 Oct 2026 17:39 (run 12, regex_suite) | — |
| TC-REF-004 | negative | Letter O is not in the alphabet | Rule `reference` | `"VQ-261010-7K3MO"` | Rejected by the validator | Rejected: Testing reference is not in the expected format (VQ-261010-7K3MP). | PASS | 10 Oct 2026 17:39 (run 12, regex_suite) | — |
| TC-REF-005 | negative | Letter I is not in the alphabet | Rule `reference` | `"VQ-261010-7K3MI"` | Rejected by the validator | Rejected: Testing reference is not in the expected format (VQ-261010-7K3MP). | PASS | 10 Oct 2026 17:39 (run 12, regex_suite) | — |
| TC-REF-006 | boundary | Five-digit date part | Rule `reference` | `"VQ-26101-7K3MP"` | Rejected by the validator | Rejected: Testing reference is too short. | PASS | 10 Oct 2026 17:39 (run 12, regex_suite) | — |
| TC-REF-007 | boundary | Six-character suffix | Rule `reference` | `"VQ-261010-7K3MPX"` | Rejected by the validator | Rejected: Testing reference is too long. | PASS | 10 Oct 2026 17:39 (run 12, regex_suite) | — |
| TC-REF-008 | malformed | Wrong prefix | Rule `reference` | `"QV-261010-7K3MP"` | Rejected by the validator | Rejected: Testing reference is not in the expected format (VQ-261010-7K3MP). | PASS | 10 Oct 2026 17:39 (run 12, regex_suite) | — |
| TC-REF-009 | malformed | Missing hyphens | Rule `reference` | `"VQ2610107K3MP"` | Rejected by the validator | Rejected: Testing reference is too short. | PASS | 10 Oct 2026 17:39 (run 12, regex_suite) | — |
| TC-REF-010 | security | SQL injection fragment | Rule `reference` | `"VQ-261010-' OR 1=1"` | Rejected by the validator | Rejected: Testing reference is too long. | PASS | 10 Oct 2026 17:39 (run 12, regex_suite) | — |

## Workflow

| ID | Type | Title | Preconditions | Input / steps | Expected | Actual | Status | Executed | Defect |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| TC-WF-001 | functional | Full workflow from submission to released result | Active phlebotomist exists | Assign, collect, start processing, record result, release | Each status recorded in history with actor; patient notified; result visible only after release | Automated: TestingWorkflowTest::testFullWorkflowWithHistoryAndPatientNotifications, UserJourneysTest::testResultIsVisibleToPatientOnlyAfterRelease passed | PASS | 10 Oct 2026 17:39 (run 11, coverage) | — |
| TC-WF-002 | security | Invalid status transition is refused on the server | Request in 'submitted' | Attempt to release a result directly | Workflow error; status unchanged | Automated: TestingWorkflowTest::testInvalidTransitionsAreRefusedAndStatusIsUnchanged, UserJourneysTest::testAdminWorkflowThroughHttp passed | PASS | 10 Oct 2026 17:39 (run 11, coverage) | — |
| TC-WF-003 | negative | Patient cannot cancel after sample collection | Request in 'sample_collected' | Patient posts to cancel | Error message; status unchanged | Automated: TestingWorkflowTest::testPatientCanCancelOnlyOwnRequestBeforeCollection passed | PASS | 10 Oct 2026 17:39 (run 11, coverage) | — |
| TC-WF-004 | functional | Reassignment keeps exactly one current assignment | Request assigned | Reassign to another phlebotomist | Old assignment ended; exactly one current assignment | Automated: TestingWorkflowTest::testReassignmentKeepsExactlyOneCurrentAssignment passed | PASS | 10 Oct 2026 17:39 (run 11, coverage) | — |

