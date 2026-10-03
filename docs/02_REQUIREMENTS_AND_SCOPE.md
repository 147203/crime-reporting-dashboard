# 02. Requirements, scope, and analytics definitions

This is the contract for what is built and how every number is computed. Requirement IDs (FR, NFR) are used in tests, commits, and Chapter 4. All numeric targets are **PROVISIONAL** until confirmed with the supervisor after the first prototype cycle.

## 1. Scope in one table

| Area | Built | Not built |
|---|---|---|
| Users | Administrator, Reporting Officer, Analyst; no public registration | Public reporting portal, citizen accounts |
| Data capture | Structured incident form; CSV bulk import | Evidence, attachments, case files |
| Location | County, sub-county, ward from reference tables; free-text street or landmark | Map picker, GPS, geocoding, coordinates, spatial types |
| Data quality | Validation, PII guard, duplicate detection and review, import error report | Automatic record merging without review |
| Analysis | Descriptive analytics A1 to A10 | Prediction, machine learning, real-time streams |
| Presentation | Dashboards, charts with table alternatives, PDF and CSV reports | Maps, external BI tools |
| Governance | Audit log, role and station scoping | Integration with police or government systems |
| Data | Synthetic sample data, clearly labelled | Real personal data |

## 2. Roles and access matrix

| Capability | Administrator | Reporting Officer | Analyst |
|---|---|---|---|
| Log in, change own password | Yes | Yes | Yes |
| Manage users, reset passwords, deactivate | Yes | No | No |
| Manage reference data (categories, sub-counties, wards, stations) | Yes | No | No |
| Create incident | Yes (any station) | Yes (own station only) | No |
| Edit incident | Yes | Own incidents while status is Reported | No |
| Change incident status (Reported, Under investigation, Closed) | Yes | Own station (D-40, proposed) | No |
| Delete incident (soft delete) | Yes | No | No |
| View incident list and detail | All | Own station | All |
| Review and resolve suspected duplicates | Yes | No | Yes |
| Bulk CSV import | Yes | No | No |
| Dashboards | County-wide | Own station | County-wide |
| Export CSV and PDF | All data | Own station | All data |
| View audit log | Yes | No | No |
| View data-quality summary | Yes | No | Yes |

Enforced server-side by policies and middleware; every route x role pair has a test (allowed and denied).

## 3. Functional requirements

| ID | Requirement | Acceptance |
|---|---|---|
| FR-01 | Authentication: login and logout, no public registration, lockout after repeated failures, password change | Wrong password five times locks the account for the configured time; lockout and throttling tested |
| FR-02 | Role-based access control with station scoping (section 2) | Authorization tests cover every route and role |
| FR-03 | User management by administrators: create, edit role and station, deactivate, reset password (generated or typed), unlock a locked account | Deactivated users cannot log in; changes are audited; a generated password is shown once and never stored or logged; an administrator cannot reset their own password, role or status here, and the last active administrator cannot be demoted or deactivated |
| FR-04 | Reference data management: crime categories, sub-counties, wards, stations | Ward always belongs to one sub-county; inactive categories cannot be selected |
| FR-05 | Incident reporting form with server-side validation (section 5) | Invalid input rejected with clear messages; valid input creates a reference number |
| FR-06 | Incident records: search, filter, sort, paginate, view, edit under the role rules, soft delete, status workflow Reported, Under investigation, Closed | Filters combine correctly; status changes recorded with timestamp |
| FR-07 | Data quality: validation, PII guard on free text, duplicate detection with review queue | Seeded bad and duplicate records are caught at the rates in NFR-05 |
| FR-08 | Bulk CSV import with a dry run, per-row error report, and an accepted-only commit | Rejected rows never enter the database; report downloadable |
| FR-09 | Analytics engine implementing A1 to A10 (section 4) | Oracle tests match exactly on the seeded dataset |
| FR-10 | Dashboards with KPI cards, charts, and filters (period, category, sub-county, ward, status) | Charts reflect filters; each chart has a table alternative and shows period and n; clicking a bar, slice or table row opens the incident list with the same filters, and its total equals the chart value (D-12, Phase 6b) |
| FR-11 | Reports: PDF summary and CSV and Excel (.xlsx) data export honouring role scope and filters (D-12) | Export equals what the screen shows for the same filters |
| FR-12 | Audit log of logins, failed logins, incident create, update, delete, imports, exports, user changes | Viewable and filterable by administrators; contains no sensitive field content |
| FR-13 | Data-quality summary view: rejection counts by rule, duplicates flagged and resolved, completeness of optional fields | Numbers match the audit and import records |
| FR-14 | Deterministic sample-data generator (command) with labelled records and an oracle answer file | Same seed gives identical data and oracle output |

## 4. Analytics catalogue (definitions used everywhere)

Common rules: time zone is Africa/Nairobi (no daylight saving, so local time is stored). A **countable incident** is not soft-deleted and not a confirmed duplicate. All analytics accept the standard filters: period (on `occurred_at`), category, sub-county, ward, station, status. Role scope is applied before any aggregate.

| ID | Metric | Definition | Output |
|---|---|---|---|
| A1 | Total incidents | Count of countable incidents matching the filters | KPI card |
| A2 | Category distribution | Count per category; share = count divided by A1 for the same filters | Bar or doughnut chart, table |
| A3 | Monthly trend | Count per calendar month in the period. 3-month moving average = mean of the month and the two before it (shown only when three months exist). Month-on-month change = (m minus m-1) divided by (m-1); shown as n/a when m-1 is 0 | Line chart, table |
| A4 | Period comparison | Count in the selected period versus the immediately preceding period of equal length; absolute and percentage change, n/a when the previous count is 0 | KPI with delta |
| A5 | Day-of-week pattern | Count and share per ISO weekday (Mon to Sun) | Bar chart |
| A6 | Time-of-day pattern | Count and share in four bands: 00:00 to 05:59, 06:00 to 11:59, 12:00 to 17:59, 18:00 to 23:59 | Bar chart |
| A7 | Area ranking | Count, share, and rank per ward and per sub-county. Optional rate per 100,000 residents when a population table is loaded (VERIFY source); the rate is not annualised when the period is under 12 months | Ranked table, bar chart |
| A8 | Area hotspot flag (non-spatial) | For the chosen grain (ward by default) let c_w be the count in ward w over the period, including wards with zero. Mean mu and population standard deviation sigma over all wards; z_w = (c_w minus mu) divided by sigma. A ward is flagged when z_w is at least `HOTSPOT_Z_THRESHOLD` (default 1.5) and c_w is at least `HOTSPOT_MIN_INCIDENTS` (default 10). No flags when sigma is 0. Can be computed per category | Flagged list with z score, highlighted rows |
| A9 | Area by time grid | Matrix of sub-county by month (or by time band); cell value is the count; colour scale by quintile | Heat grid table (a table, not a map) |
| A10 | Status and timeliness | Count per status; closure rate = Closed divided by all countable incidents (denominator stated); median and 90th percentile of hours between `occurred_at` and `reported_at`; median days between `reported_at` and `closed_at` for closed incidents | KPI cards, table |

Every chart and KPI shows the period, active filters, and n. Percentages state their denominator.

Clarifications (D-27, as implemented and oracle-tested): A2 lists only categories present; A5, A6 and A7 list every weekday, band and area, with 0 shares when n is 0. A7 ward list is limited by the sub-county and ward filters; the sub-county list by the sub-county filter and, with a ward filter, to that ward's sub-county. A8 ignores the ward filter (one ward has no spread) and compares all wards of the sub-county filter (all 85 otherwise), zeros included; for a single station this mostly reflects where that station's incidents occur (D-47). A9 rows are all sub-counties of the sub-county filter. A4 previous period: same number of days, ending the day before `from`. A10 percentiles are nearest-rank. Presentation only (not metrics): status shares in the A10 chart are status count divided by A1; the A9 cell shading uses the quintiles of the non-zero cells (level 0 for empty cells, then 1 to 5). Parameters live in configuration, not code. Hotspot parameters are justified in Chapter 4 and their sensitivity is reported in Chapter 5.

## 5. Validation and data-quality rules

| ID | Rule | Action |
|---|---|---|
| V1 | Category required and active | Reject |
| V2 | `occurred_at` required, not in the future, not older than 10 years | Reject |
| V3 | `reported_at` not before `occurred_at`, not in the future | Reject |
| V4 | Ward must belong to the chosen sub-county | Reject |
| V5 | Location description at most 150 characters; description at most 1,000 characters, both stripped of HTML | Reject or sanitise |
| V6 | **PII guard:** description and location must not contain Kenyan phone numbers or national ID numbers. Method (D-36, revised after the Phase 6 security review): normalise the text (Windows-1252 read as text, full-width and other scripts' digits to ASCII, invisible characters removed); set aside calendar dates and amounts after KES, KSh or Sh; read every group of digits joined by separators (space, dot, comma, hyphen, slash, underscore, asterisk, brackets) as one number; a phone is 254 (optionally with the trunk 0) or 0, then 7 or 1, then 8 digits; a national ID is 7 or 8 digits, or 6 to 9 digits after "ID", "ID no" or "ID number". Any stand-alone 7 or 8 digit number is refused (a safe false positive). Tags are removed repeatedly until none remain; output encoding remains the XSS control | Reject with a message that explains why |
| V7 | Reference number unique | Reject |
| V8 | **Duplicate candidate:** same category and ward, `occurred_at` within `DUPLICATE_WINDOW_MINUTES` (default 120), and normalised location similarity at least `DUPLICATE_SIMILARITY` (default 0.8, normalised Levenshtein: lower case, punctuation removed, spaces collapsed; 1 minus distance over the longer length). Records without a location description are not compared (D-38), and the numbers in the two locations (house, plot or stage numbers) must be the same, in order, ignoring leading zeros (D-48). The best match above the threshold is linked | Save as `suspected`, add to review queue; never auto-delete |

Duplicate status values: none, suspected, confirmed, dismissed. Confirmed duplicates are excluded from every analytic. A suspected duplicate can be confirmed only while its earlier record is still counted (not deleted and not itself a confirmed duplicate); otherwise the flag is dismissed (D-46).

### 5a. Data-quality summary (FR-13) definitions

| Measure | Definition |
|---|---|
| Rows in files, accepted, rejected | Sums over committed import batches (web uploads and console loads) |
| Rejections by rule | Number of problems per rule over every error of every committed import; one row can break more than one rule, so these can exceed rejected rows |
| Duplicate review | Records the viewer can see (not deleted), by duplicate status |
| Completeness of a field | Countable records the viewer can see with the field filled in, divided by countable records the viewer can see, as a percentage (2 dp) |

## 6. Location model (no coordinates)

`sub_counties` (Nairobi County sub-counties) and `wards` (each ward in one sub-county) are reference tables loaded from an official list; the author verifies names and counts against the source (VERIFY: loaded as 17 sub-counties and 85 wards from the IEBC 2012 boundaries, `database/seeders/data/`, D-28). Stations are synthetic sample stations, one per sub-county. Police stations belong to a sub-county. An incident stores `sub_county_id`, `ward_id`, `station_id`, and a free-text `location_description`. No latitude, longitude, or spatial column exists anywhere.

## 7. Non-functional requirements (measurable, PROVISIONAL)

| ID | Requirement | Target |
|---|---|---|
| NFR-01 | Security | Auth and policy on 100 percent of routes; lockout, throttling, CSRF, hashing, security headers covered by tests; no credential defaults except the local database account (D-25), which preflight rejects under `APP_ENV=production` |
| NFR-02 | Privacy | No personal identifiers of victims or suspects in schema or logs; PII guard blocks seeded PII samples in at least 95 percent of cases |
| NFR-03 | Usability | System Usability Scale mean at least 68 across participants; at least 90 percent completion of core tasks (log in, report incident, filter, view dashboard, export) |
| NFR-04 | Performance | On 100,000 sample incidents on the development machine: dashboard 95th percentile response at most 2 s; list page at most 1 s; import of 10,000 rows at most 60 s |
| NFR-05 | Accuracy | Analytics match the oracle exactly; seeded invalid records rejected at least 95 percent; duplicate detection precision and recall each at least 90 percent on the seeded set; planted hotspots recovered (report recall and precision) |
| NFR-06 | Reliability | Documented backup and restore procedure tested once; migrations reversible |
| NFR-07 | Responsiveness | All screens usable at 360 px width and on desktop |
| NFR-08 | Accessibility | Chart table alternatives; colour contrast at WCAG AA; keyboard operable forms |
| NFR-09 | Maintainability | Layered code, Pint and Larastan clean, service-level test coverage reported |
| NFR-10 | Portability | Fresh checkout runs on Linux or Windows by following `10_SETUP_AND_GIT.md` |

If a target is missed, report the result and analyse why; do not change targets after seeing results.

## 8. Recommended objective set (author words them)

The marker asked for split, measurable objectives and a design objective. A structure that satisfies this and links to the problem:

| # | Objective (topic) | Measure or evidence |
|---|---|---|
| 1 | Analyse the requirements for crime data reporting, analysis, and visualization in Nairobi County | Interview themes and questionnaire results; requirements list FR and NFR |
| 2 | Identify the challenges of current crime data management and review at least three existing systems, techniques, and tools | Related-works review with actors, data flows, processing; gap table |
| 3 | Design the platform (architecture, database, interfaces) | Chapter 4 diagram set, schema, wireframes reviewed by the supervisor |
| 4 | Develop the platform for reporting, validating, analysing, and visualising crime data | Modules FR-01 to FR-14 delivered |
| 5 | Test the platform on sample data for functionality, accuracy, and usability | Test pass rates, NFR-05 accuracy results, SUS score |

Research questions are the same five as questions. Keep one action per objective.
