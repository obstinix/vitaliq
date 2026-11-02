# Platform roadmap and progress tracker

Started 1 Nov 2026, from commit `4bd75d9`. The roadmap turns Vitaliq from a COVID-19 testing app into a digital healthcare and public-health platform. Nothing that already works is removed. Every schema change is a new, additive migration (`0005` onwards). No existing table is dropped.

Status values: NOT STARTED, IN PROGRESS, IMPLEMENTED (code merged locally), TESTED (automated tests pass), PUSHED (on `origin/main`), BLOCKED (needs something outside the repository, named in the row).

## Baseline (audit, 1 Nov 2026)

- PHP 8.4.27, MariaDB 11.4.13.
- Custom kernel (`App`, `Router`, middleware `guest`/`auth`/`role:`), CSRF on every POST, CSP `script-src 'self'`.
- 4 migrations, 21 tables, 4 roles (patient, phlebotomist, admin, qa).
- 275 PHPUnit tests, all passing; PHPStan level 6 clean.
- Front end:
  - one stylesheet (`vitaliq.css`, 1,249 lines), vanilla ES modules, a D3 subset for charts;
  - GSAP on public pages only, and it respects `prefers-reduced-motion`.

## Design direction

- **App screens** follow the *framed sheet* style dealt by the design-taste-frontend skill (seed 283104398), with the brand palette and type kept:
  - one tinted L-frame (sidebar and 48 px top bar) in a clinical blue-grey;
  - work on a white sheet with a 1 px edge and no shadow;
  - navy ink text, teal on data and the active tab only, muted green for good outcomes, coral for bad.
- **The face.** The frame is tinted from the product's clinical blue-grey, and the brand teal is the one chart hue.
- **Signature element.** The sample's path (requested → collected → tested → verified → released) is drawn on every request and sample.
- **Public pages** keep the editorial design that is already there.
- **Animation.** The existing GSAP build covers public pages. App screens use CSS transitions only, as feedback; nothing performs an entrance.
- **awesome-design-md** (VoltAgent, MIT) was reviewed. It has no healthcare entry. Its Oracle, IBM Carbon and Linear entries confirm the same direction: restrained enterprise chrome, colour on data. None of its files are copied.

## Roles

| Role | Existing | Home | Notes |
| --- | --- | --- | --- |
| patient | yes | `/patient` | Family profiles hang off the account holder |
| phlebotomist | yes | `/staff` | Field sample collection |
| lab | **new** | `/lab` | Laboratory staff: samples, results, inventory, equipment |
| officer | **new** | `/surveillance` | Public-health officer: surveillance, alerts, reports |
| admin | yes | `/admin` | Everything, plus RBAC, facilities, backups |
| qa | yes | `/qa` | STQA laboratory |

Granular permissions (feature 39) sit on top of roles. The role remains the default bundle of permissions.

## Milestones

| # | Milestone | Status |
| --- | --- | --- |
| 0 | Backup, audit, design direction, roadmap | PUSHED |
| 1 | Design system, app shell, role-aware navigation, new roles | PUSHED |
| 2 | Patient platform (features 1–10) | PUSHED |
| 3 | Laboratory operations (11–20) | PUSHED |
| 4 | Public-health intelligence (21–30) | PUSHED |
| 5 | Notifications and languages (31–38) | PUSHED |
| 6 | Administration (39–45) | PUSHED |
| 7 | Security (46–50) | PUSHED |
| 8 | Advanced capabilities (51–60) | PUSHED |
| 9 | Stabilisation: full regression, accessibility, performance | PUSHED |
| 10 | Documentation and release | PUSHED |

## Feature tracker

The feature table is kept in [FEATURE_STATUS.md](FEATURE_STATUS.md), one row per feature, with its routes, tables and tests.
