# Feature status

Statuses: NOT STARTED, IN PROGRESS, IMPLEMENTED, TESTED, PUSHED, BLOCKED. "Tests" names the PHPUnit class that covers the feature.

## A. Patient platform

| # | Feature | Status | Routes | Tests |
| --- | --- | --- | --- | --- |
| 1 | Patient dashboard | PUSHED | `/patient` | PatientPlatformTest, UserJourneysTest |
| 2 | Health passport | PUSHED | `/patient/passport` | PatientPlatformTest::testPassportValidatesAndSaves |
| 3 | Family profiles | PUSHED | `/patient/family`, `?for=` on every patient page | PatientPlatformTest::testFamilyProfilesAreScopedToTheirHolder |
| 4 | Appointments | PUSHED | `/patient/appointments`, `/admin/appointments` | PatientPlatformTest::testAppointmentBookingRulesAndCalendar, testCancellationNoticeAndStaffTransitions |
| 5 | Calendar | PUSHED | `/patient/appointments?view=calendar`, `/admin/appointments` | PatientPlatformTest::testAppointmentBookingRulesAndCalendar |
| 6 | QR health ID (revocable) | PUSHED | `/patient/health-id`, public `/h/{token}` | PatientPlatformTest::testQrHealthIdOpensCardUntilRevoked |
| 7 | Encrypted document vault | PUSHED | `/patient/documents` | PatientPlatformTest::testDocumentVaultEncryptsAndScopesFiles |
| 8 | Emergency contacts | PUSHED | `/patient/contacts` | PatientPlatformTest::testEmergencyContactsKeepExactlyOnePrimary |
| 9 | Health timeline | PUSHED | `/patient/timeline` | PatientPlatformTest::testTimelineFeedbackAndDashboard |
| 10 | Feedback | PUSHED | `/patient/feedback`, `/admin/feedback` | PatientPlatformTest::testTimelineFeedbackAndDashboard |

## B. Laboratory

| # | Feature | Status | Routes | Tests |
| --- | --- | --- | --- | --- |
| 11 | Multi-test orders | PUSHED | `/lab/orders`, `/lab/orders/new`, `/lab/orders/{id}` | LaboratoryTest::testOrderCreationThroughTheFormAndAnalytics, testCatalogueLoadsOnceAndBarcodesCarryACheckDigit |
| 12 | Inventory | PUSHED | `/lab/inventory`, `/lab/inventory/{id}` | LaboratoryTest::testInventoryUsesEarliestExpiryAndNeverExpiredStock |
| 13 | Sample lifecycle | PUSHED | `/lab/samples`, `/lab/samples/{id}` | LaboratoryTest::testRejectionRecollectionAndNotReportable |
| 14 | Barcode scanning | PUSHED | `/lab/scan` (scanner, typed or camera), `/lab/samples/{id}/label` | LaboratoryTest::testCatalogueLoadsOnceAndBarcodesCarryACheckDigit, testLabRoutesEnforceRoleAndPermission |
| 15 | Equipment | PUSHED | `/lab/equipment`, `/lab/equipment/{id}` | LaboratoryTest::testEquipmentCalibrationAndQcFailure |
| 16 | Result validation against reference ranges | PUSHED | `/lab/catalogue`, `/lab/catalogue/{id}` | LaboratoryTest::testRangeResolutionAndFlags, testRangeEditorValidatesLimits |
| 17 | Verification workflow | PUSHED | `/lab/verify`, `/lab/items/{id}/verify` | LaboratoryTest::testFullWorkflowWithTwoPersonVerificationAndRelease |
| 18 | Rejections | PUSHED | sample rejection, send-back, not reportable | LaboratoryTest::testRejectionRecollectionAndNotReportable, testNotReportableItemsSettleTheOrderAndReleaseNeedsAResult |
| 19 | Workload | PUSHED | `/lab` | LaboratoryTest::testOrderCreationThroughTheFormAndAnalytics |
| 20 | Performance analytics | PUSHED | `/lab/performance` | LaboratoryTest::testOrderCreationThroughTheFormAndAnalytics |

## C. Public health

| # | Feature | Status | Routes | Tests |
| --- | --- | --- | --- | --- |
| 21 | Surveillance | PUSHED | `/surveillance` | SurveillanceTest::testCountsCombineWorkflowLaboratoryAndImports |
| 22 | Geographic mapping | PUSHED | `/surveillance/map` (state tile map) | SurveillanceTest::testCountsCombineWorkflowLaboratoryAndImports |
| 23 | Outbreak threshold alerts | PUSHED | `/surveillance/alerts`, `bin/surveillance-check.php` | SurveillanceTest::testOutbreakRulesRaiseOnceAndNeedANoteToClose |
| 24 | Trend estimates with uncertainty | PUSHED | `/surveillance/trends` | SurveillanceTest::testStatisticsHelpers |
| 25 | Demographics | PUSHED | `/surveillance/demographics` | SurveillanceTest::testCountsCombineWorkflowLaboratoryAndImports, testScreensExportsAndAccess |
| 26 | Seasonal analysis | PUSHED | `/surveillance/trends` (monthly table) | SurveillanceTest::testScreensExportsAndAccess |
| 27 | Facility comparison | PUSHED | `/surveillance/facilities` | SurveillanceTest::testScreensExportsAndAccess |
| 28 | CSV/JSON importer | PUSHED | `/surveillance/imports` | SurveillanceTest::testImporterValidatesPreviewsCommitsReplacesAndRollsBack, testFilesThatAreNotTabularAreRefused |
| 29 | PDF/CSV reports | PUSHED | `/surveillance/reports`, `/surveillance/reports/export` | SurveillanceTest::testScreensExportsAndAccess, testPdfEscapesTextAndPaginates |
| 30 | Data quality | PUSHED | `/surveillance/quality` | SurveillanceTest::testScreensExportsAndAccess |

## D. Notifications

| # | Feature | Status | Routes | Tests |
| --- | --- | --- | --- | --- |
| 31 | Email | PUSHED · live sending BLOCKED (needs SMTP credentials) | `MAIL_DRIVER=smtp`, `bin/notify-worker.php` | NotificationChannelsTest::testNothingLeavesTheAppUntilTheUserOptsIn, testSmtpMessageIsSafeAndEncoded |
| 32 | SMS | PUSHED · live sending BLOCKED (needs Twilio account) | `SMS_DRIVER=twilio` | NotificationChannelsTest::testTwilioSmsAndWhatsAppWithRetries |
| 33 | WhatsApp | PUSHED · live sending BLOCKED (needs Twilio WhatsApp sender) | `WHATSAPP_DRIVER=twilio` | NotificationChannelsTest::testTwilioSmsAndWhatsAppWithRetries |
| 34 | Real-time updates (SSE or polling) | PUSHED | `/api/notifications/stream` (SSE), `/api/notifications` (polling fallback) | NotificationChannelsTest::testEventStreamReportsUnreadAndNewSinceLastId |
| 35 | Preferences | PUSHED | `/account/preferences` | NotificationChannelsTest::testNothingLeavesTheAppUntilTheUserOptsIn |
| 36 | Reminders | PUSHED | `bin/notify-worker.php` | PatientPlatformTest::testAppointmentBookingRulesAndCalendar |
| 37 | Broadcast | PUSHED | `/admin/broadcasts` | NotificationChannelsTest::testBroadcastReachesItsAudienceOnly |
| 38 | EN/HI/MR translations | PUSHED · partial coverage (shell, navigation, patient overview, preferences, public header) | `/locale`, `/account/preferences` | NotificationChannelsTest::testLanguageAndAccessibilityPreferences |

## E. Administration

| # | Feature | Status | Routes | Tests |
| --- | --- | --- | --- | --- |
| 39 | Granular RBAC | PUSHED | `/admin/roles`, `/admin/users/{id}/permissions`, `perm:` middleware | AdministrationTest::testRoleMatrixAndUserOverridesChangeAccess, NavigationAndPermissionsTest |
| 40 | Multi-facility | PUSHED | `/admin/facilities`, `/admin/facilities/{id}` | AdministrationTest::testFacilitiesStaffAndLabOrdersFollowTheHomeFacility |
| 41 | Audit analytics | PUSHED | `/admin/audit/analytics` | AdministrationTest::testSystemHealthSearchAndAuditAnalytics |
| 42 | Staff scheduling | PUSHED | `/admin/schedule`, `/staff/schedule` | AdministrationTest::testShiftsRefuseOverlapsAndSpanMidnight |
| 43 | Backups | PUSHED | `/admin/backups`, `bin/backup.php`, `bin/backup-restore.php` | AdministrationTest::testBackupsAreEncryptedVerifiedAndRestorable |
| 44 | Health monitoring | PUSHED | `/admin/system`, public `/api/health` | AdministrationTest::testSystemHealthSearchAndAuditAnalytics |
| 45 | Search | PUSHED | `/search` | AdministrationTest::testSystemHealthSearchAndAuditAnalytics |

## F. Security

| # | Feature | Status | Routes | Tests |
| --- | --- | --- | --- | --- |
| 46 | TOTP two-factor authentication | PUSHED | `/account/security`, `/login/verify` | SecurityFeaturesTest::testTotpMatchesRfc6238, testTwoStepSignInWithReplayProtectionAndRecoveryCodes |
| 47 | Session dashboard | PUSHED | `/account/security` (sessions) | SecurityFeaturesTest::testSessionsCanBeEndedRemotelyAndNewDevicesRaiseAnAlert |
| 48 | Encrypted storage | PUSHED | vault files, TOTP secrets, backups (`src/Support/Crypto.php`) | SecurityFeaturesTest::testEncryptionIsBoundToItsPurposeAndDetectsTampering, PatientPlatformTest, AdministrationTest |
| 49 | Retention and deletion | PUSHED | `/admin/retention`, `/account/security` (deletion), `bin/retention.php` | SecurityFeaturesTest::testRetentionPurgesOperationalRecordsOnly, testDeletionRemovesPersonalContentAndKeepsClinicalRecords |
| 50 | Security alerts | PUSHED | notifications of type `security` | SecurityFeaturesTest::testSessionsCanBeEndedRemotelyAndNewDevicesRaiseAnAlert, testRepeatedFailedSignInsWarnTheAccountOwner |

## Advanced

| # | Feature | Status | Routes | Tests |
| --- | --- | --- | --- | --- |
| 51 | AI assistant (configurable provider) | NOT STARTED | | |
| 52 | Drug interaction reference | NOT STARTED | | |
| 53 | Teleconsultation | NOT STARTED | | |
| 54 | Triage questionnaire | NOT STARTED | | |
| 55 | Alert engine | NOT STARTED | | |
| 56 | Report verification tokens | NOT STARTED | | |
| 57 | FHIR resources | NOT STARTED | | |
| 58 | Offline portal | NOT STARTED | | |
| 59 | Accessibility mode | NOT STARTED | | |
| 60 | Public versioned API | NOT STARTED | | |
