# Database

MariaDB 10.6+ or MySQL 8.0+, InnoDB, `utf8mb4_unicode_ci`. Every connection runs with strict SQL mode and the application's time-zone offset.

## Initialisation

```bash
php bin/migrate.php
```

```bash
php bin/seed.php
```

- `bin/migrate.php` applies `database/migrations/*.sql` in order and records each in `schema_migrations`, so running it again is a no-op.
- `php bin/migrate.php --fresh` drops every table first. It asks you to type the database name and is disabled when `APP_ENV=production`.
- `bin/seed.php` loads synthetic demonstration data (see below).

| Migration | Contents |
| --- | --- |
| `0001_core_schema.sql` | All application tables |
| `0002_reference_states.sql` | 28 states and 8 union territories |
| `0003_app_settings.sql` | Key/value settings (records when demo data was seeded) |
| `0004_qa_coverage_run_type.sql` | Adds the `coverage` QA run type |

## Entity relationships

```
users 1─0..1 patients 1─* test_requests *─1 states
users 1─0..1 phlebotomists 1─* test_assignments *─1 test_requests
test_requests 1─* test_request_symptoms
test_requests 1─0..1 sample_collections *─1 phlebotomists
test_requests 1─0..1 test_results
test_requests 1─* test_status_history *─0..1 users (actor)
users 1─* notifications, password_tokens, audit_events (actor), comments (author/moderator)
qa_test_cases *─0..1 defects;  qa_runs 1─* qa_run_results *─0..1 qa_test_cases
```

## Tables

| Table | Purpose | Notable constraints |
| --- | --- | --- |
| `users` | Sign-in identities for all roles | Unique `email` and `mobile`. `password_hash` is NULL until an invited user sets one. `session_version` revokes sessions. |
| `patients` | Demographic record. Guests have `user_id` NULL; account holders have exactly one row. | Unique `user_id`; FK to `states` |
| `phlebotomists` | Sample collectors, optionally linked to a portal login | Unique `employee_code`, `mobile` and `user_id`; `deleted_at` for soft deletion |
| `test_requests` | One testing request | Unique `reference` (`VQ-YYMMDD-XXXXX`) and `idempotency_key` (SHA-256 of the form token). Status ENUM. Indexes on status/date, patient, date and state. Patient FK is `RESTRICT`. |
| `test_request_symptoms` | Normalised symptom list | PK (`request_id`, `symptom`), cascade delete |
| `test_assignments` | Assignment history | `is_current` is 1 or NULL with `UNIQUE (request_id, is_current)`, so only one current assignment is possible. CHECK constraint on `is_current`. |
| `sample_collections` | Specimen, tube barcode and time | One per request; unique `barcode` |
| `test_results` | Outcome, optional Ct value, recorded/released by and at | `CHECK (ct_value BETWEEN 0 AND 45)` |
| `test_status_history` | Every transition with actor and role | Indexed by request and time |
| `notifications` | Per-recipient notifications | Index (`user_id`, `read_at`, `created_at`) |
| `announcements` | Public-health updates and service notices | `is_demo` flag |
| `comments` | Community comments, pre-moderated | Status ENUM; `ip_hash` only (no raw IP); `is_demo` flag |
| `password_tokens` | Reset and invite tokens | Only `token_hash` (SHA-256) is stored, and it is unique; `expires_at`, `used_at` |
| `audit_events` | Security and administrative events | JSON details without secrets; `ip_hash` |
| `rate_limits` | Fixed-window counters | Bucket name stored as a SHA-256 hash |
| `defects` | Defect register | Unique `code`; `origin` distinguishes development testing, demo and lab reports |
| `qa_test_cases` | Catalogue | Unique `case_code`; `expected_match` for regex cases; optional defect link |
| `qa_runs`, `qa_run_results` | Executions | Real timestamps, environment and per-case actual result |
| `states`, `app_settings`, `schema_migrations` | Reference and bookkeeping data | |

### Platform tables (migrations 0005-0012)

All of these were added by new migrations. No existing table was dropped, and existing columns kept their meaning. ENUM columns were only extended (users.role gained `lab` and `officer`).

| Migration | Tables | Notes |
| --- | --- | --- |
| 0005 | `role_permissions`, `user_permissions`, `facilities`, `user_facilities`; `test_requests.facility_id`; `users.locale`, `users.accessibility_mode` | Permission overrides on top of role defaults |
| 0006 | `health_profiles`, `immunisations`, `appointments`, `health_id_tokens`, `patient_documents`, `emergency_contacts`, `patient_feedback`; `patients.managed_by_user_id`, `patients.relationship` | Family profiles are patient rows managed by an account holder. Health ID tokens are stored as SHA-256 hashes. Vault files are encrypted on disk. |
| 0007 | `lab_tests`, `lab_reference_ranges`, `lab_orders`, `lab_samples`, `lab_sample_events`, `lab_order_items`, `lab_inventory_items`, `lab_inventory_lots`, `lab_inventory_movements`, `lab_equipment`, `lab_equipment_logs` | Unique 12-digit barcodes; one item per test per order; two-person verification enforced in the service |
| 0008 | `surveillance_imports`, `surveillance_records`, `surveillance_thresholds`, `outbreak_alerts` | `CHECK (positives <= tests)`; one record per disease/day/state/facility/sex/age band; one alert per rule/state/window |
| 0009 | `notification_preferences`, `notification_deliveries`, `broadcasts` | Delivery status includes `simulated` for local drivers |
| 0010 | `staff_shifts`, `backups` | `CHECK (ends_at > starts_at)`; backups register the SHA-256 of each encrypted file |
| 0011 | `user_two_factor`, `user_sessions`, `deletion_requests` | Encrypted TOTP secrets; hashed recovery codes and session keys |
| 0012 | `assistant_messages`, `triage_assessments`, `alert_rules`, `alert_rule_hits`, `report_verifications`, `api_keys` | Verification codes and API keys stored only as hashes |

## Deliberate redundancy

`users.full_name`/`mobile` and `patients.full_name`/`mobile` overlap for account holders. `users` is the sign-in identity used by every role; `patients` is the clinical record, which also exists for guests without accounts. `PatientService::updateProfile()` updates both in one transaction.

## Deletion behaviour

- Patients cannot be deleted while they have requests (`RESTRICT`).
- A phlebotomist who appears in any assignment or collection is soft-deleted (`deleted_at`, deactivated, portal login disabled) instead of removed.
- Deleting a user sets the actor references in history and audit tables to NULL rather than losing those rows.

## Demo data

`bin/seed.php` creates synthetic records through the real services with a frozen clock:

- 4 staff accounts, 7 phlebotomists, 25 patient accounts and about 80 guest patients;
- about 150 requests spread over 45 days at realistic stages;
- results, notifications and announcements (3 real WHO milestones with sources, and 2 labelled demo notices);
- 10 labelled demo comments, the defect register (`database/seeds/defects.json`) and the QA catalogue.

`bin/seed-platform.php` then adds, through the same services:

- 4 facilities, 2 laboratory staff and 1 public-health officer;
- the test catalogue, stock and equipment;
- 18 lab orders at different stages;
- 2 appointments;
- two years of synthetic daily surveillance returns for 5 states, imported through the real importer, with 3 outbreak rules.

Each part records its own marker, so it runs once.

Demo mobile numbers all start with `9000`. Emails use reserved `example.com`/`vitaliq.example` domains. Demo passwords are random for each install and printed once. The seeder refuses to run in production or twice.
