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
| 21 | Surveillance | NOT STARTED | | |
| 22 | Geographic mapping | NOT STARTED | | |
| 23 | Outbreak threshold alerts | NOT STARTED | | |
| 24 | Trend estimates with uncertainty | NOT STARTED | | |
| 25 | Demographics | NOT STARTED | | |
| 26 | Seasonal analysis | NOT STARTED | | |
| 27 | Facility comparison | NOT STARTED | | |
| 28 | CSV/JSON importer | NOT STARTED | | |
| 29 | PDF/CSV reports | NOT STARTED | | |
| 30 | Data quality | NOT STARTED | | |

## D. Notifications

| # | Feature | Status | Routes | Tests |
| --- | --- | --- | --- | --- |
| 31 | Email | NOT STARTED | | |
| 32 | SMS | NOT STARTED | | |
| 33 | WhatsApp | NOT STARTED | | |
| 34 | Real-time updates (SSE or polling) | NOT STARTED | | |
| 35 | Preferences | NOT STARTED | | |
| 36 | Reminders | NOT STARTED | | |
| 37 | Broadcast | NOT STARTED | | |
| 38 | EN/HI/MR translations | NOT STARTED | | |

## E. Administration

| # | Feature | Status | Routes | Tests |
| --- | --- | --- | --- | --- |
| 39 | Granular RBAC | NOT STARTED | | |
| 40 | Multi-facility | NOT STARTED | | |
| 41 | Audit analytics | NOT STARTED | | |
| 42 | Staff scheduling | NOT STARTED | | |
| 43 | Backups | NOT STARTED | | |
| 44 | Health monitoring | NOT STARTED | | |
| 45 | Search | NOT STARTED | | |

## F. Security

| # | Feature | Status | Routes | Tests |
| --- | --- | --- | --- | --- |
| 46 | TOTP two-factor authentication | NOT STARTED | | |
| 47 | Session dashboard | NOT STARTED | | |
| 48 | Encrypted storage | NOT STARTED | | |
| 49 | Retention and deletion | NOT STARTED | | |
| 50 | Security alerts | NOT STARTED | | |

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
