# Platform guide

How each role uses Vitaliq, how to configure it, and what needs outside credentials. Per-feature status and tests are in [FEATURE_STATUS.md](FEATURE_STATUS.md).

## Roles and home screens

| Role | Home | Works with |
| --- | --- | --- |
| Patient | `/patient` | Tests and results, appointments and calendar, health passport, QR health ID, family profiles, document vault, emergency contacts, timeline, symptom questionnaire, assistant, medicine checker, feedback |
| Phlebotomist | `/staff` | Assigned collections, own shifts |
| Laboratory staff | `/lab` | Samples and scanning, orders, results and verification, catalogue and ranges, inventory, equipment, workload and turnaround |
| Public-health officer | `/surveillance` | Overview, map, trends and seasons, demographics, facility comparison, outbreak alerts and rules, imports, data quality, reports |
| Administrator | `/admin` | Everything above, plus users, roles and permissions, facilities, rota, broadcasts, feedback, audit log and analytics, alert rules, API keys, system health, backups, retention |
| QA analyst | `/qa` | The STQA laboratory |

**Navigation follows permissions.** The sidebar shows only screens the person can open: a link appears only when its route exists and the person holds its permission.

**Permissions.** Roles carry default permission bundles (`src/Auth/Permissions.php`). An administrator can change a role's bundle at `/admin/roles`, or adjust one person at `/admin/users/{id}/permissions`. A person's own setting beats the role's.

## Configuration (`.env`)

| Setting | Purpose |
| --- | --- |
| `APP_KEY` | At least 32 characters. Also the root of every encryption key. **Changing it makes existing vault files, TOTP secrets and backups unreadable.** |
| `MAIL_DRIVER`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_ENCRYPTION`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_FROM` | Email: `outbox` (local `.eml` files), `smtp` or `none` |
| `SMS_DRIVER`, `SMS_FROM`, `WHATSAPP_DRIVER`, `WHATSAPP_FROM`, `TWILIO_ACCOUNT_SID`, `TWILIO_AUTH_TOKEN` | SMS and WhatsApp: `log` (local file), `twilio` or `none` |
| `AI_PROVIDER`, `AI_API_KEY`, `AI_MODEL` | Assistant: `none` or `anthropic` (default model `claude-opus-5-5`) |
| `TELECONSULT_BASE_URL` | Jitsi-compatible server for teleconsultation rooms (default `https://meet.jit.si`) |
| `NOTIFICATION_POLL_SECONDS` | Event-stream retry and polling interval |

## Scheduled jobs

| Command | When | Does |
| --- | --- | --- |
| `php bin/notify-worker.php` | Every 5 minutes | Appointment reminders for the next 24 hours; sends queued email/SMS/WhatsApp with retries |
| `php bin/surveillance-check.php` | Daily | Evaluates outbreak rules for the window ending yesterday |
| `php bin/backup.php` | Daily | Encrypted backup to `storage/backups`, keeping the newest 14. Copy the files off the server too. |
| `php bin/retention.php` | Daily | Removes operational records past their retention period (never clinical records) |

## Workflows in brief

**Laboratory**

1. An order creates one sample per specimen type, each with a 12-digit Luhn barcode and a QR label.
2. Samples move: awaiting collection → collected → (in transit) → received → in analysis → stored → disposed. A sample can be rejected until analysis starts, then recollected.
3. Results are checked against the most specific reference range and flagged.
4. A second person verifies each result. The person who entered it cannot verify it.
5. Critical results notify the orderer and administrators.
6. Once every test is verified or marked not reportable, the order can be released. The patient then sees it, with a verification code.

**Outbreak alerts.** These are rule-based: a positive count, positivity, or a count above the recent baseline. The methods are in [SURVEILLANCE_METHODS.md](SURVEILLANCE_METHODS.md).

**Imports**

1. Upload a CSV or JSON file.
2. Every row is validated and shown in a preview. Nothing is written yet.
3. Commit the preview. Rows that match existing ones replace them.
4. A committed import can be rolled back.

**Delivery outside the app.** This is opt-in per category and channel, at `/account/preferences`. Each notification is stored in the app first. Outside deliveries are queued and retried five times, with backoff.

## Integrations and their status

| Integration | Implemented | Needs from the operator |
| --- | --- | --- |
| SMTP email | Client with STARTTLS/TLS and AUTH LOGIN, verified certificates | SMTP account |
| SMS and WhatsApp | Twilio Messages API | Twilio account; for WhatsApp, an approved WhatsApp sender |
| Assistant | Anthropic provider via the official PHP SDK; emergency screening; disclaimers | Anthropic API key |
| Teleconsultation | Private random Jitsi rooms per appointment | Optional self-hosted Jitsi for data-residency needs |
| Medicine interactions | Lookup engine with a small documented reference list | A licensed interaction database for real coverage |
| FHIR R4 | Read-only Patient, Observation, DiagnosticReport; LOINC where certain | Conformance testing before connecting to a clinical system |
| Public API | `/api/v1` with scoped, expiring, hashed keys | Keys issued at `/admin/api-keys`; see `/developers` |

Nothing above sends data anywhere until it is configured. With the default settings, email goes to `storage/mail`, SMS and WhatsApp are off, and the assistant says it is not switched on.

## Clinical safety boundaries

- Nothing in Vitaliq diagnoses.
- **Questionnaire.** It suggests one of four next steps and names no condition.
- **Assistant.** It answers emergencies with fixed guidance to call 112, without involving a model. Its system prompt forbids diagnosis and prescription.
- **Reference ranges and interactions.** Both are labelled as defaults or samples that need professional verification.

Vitaliq has not had a regulatory compliance assessment and makes no compliance claim.
