# 09. Decisions, risks, and open questions

Status: DECIDED, PROPOSED (default in force, needs sign-off), OPEN. Proposal corrections are tracked in the author's local revision checklist.

## 1. Decision log

| ID | Decision | Status | Default and rationale | Sign-off |
|---|---|---|---|---|
| D-01 | Own GitHub account `147203`, private repo `crime-reporting-dashboard` | DECIDED (author) | Keeps this project's history under the author's own account | n/a |
| D-02 | No GIS in any form; "hotspot" is a statistical area or time flag | DECIDED (panel) | Panel judged GIS too complex; recorded as future work | n/a |
| D-03 | PHP with the Laravel 12 framework | DECIDED (author, 2026-09-24) | Proposal names PHP, not a framework; Laravel gives tested defaults for auth, CSRF, validation, hashing, migrations, throttling, so security work is about configuration and tests rather than reinvention | Supervisor informed |
| D-04 | Database: XAMPP MariaDB 10.4 (MySQL-compatible) on 127.0.0.1:3306 for the app and the tests, not SQLite | DECIDED (author, 2026-09-24; revised the same day from MySQL 8 on 3307) | Already installed, so nothing to set up for a local presentation; tests use the same engine as the app, so date functions behave the same in both. Trade-offs accepted: MariaDB 10.4 is end-of-life, and the proposal's "MySQL" wording needs a note that MariaDB is a MySQL-compatible server (VERIFY with the supervisor) | Author |
| D-05 | Frontend: Blade templates, Bootstrap 5, Chart.js, plain JavaScript, Vite; no SPA framework | DECIDED (author, 2026-09-24) | Matches "HTML, CSS, JavaScript" in 3.5 with less to explain at the defence | Author |
| D-06 | Three roles: Administrator, Reporting Officer, Analyst (proposal 3.4 lists Administrator and Authorized User) | DECIDED (author, 2026-09-24) | The conceptual framework already names officers, analysts, and administrators; separate roles make access control demonstrable | Supervisor |
| D-07 | Synthetic data only; the generator plants hotspots, trends, errors, and duplicates so accuracy can be tested against ground truth | DECIDED | Real records are confidential; the generator doubles as the evaluation instrument | n/a |
| D-08 | No personal identifiers of victims, suspects, or witnesses in the schema; PII guard on free text | DECIDED | Data minimisation under the Kenya Data Protection Act, 2019; reduces risk | n/a |
| D-09 | Final title | OPEN | Options in the local proposal revision checklist | Supervisor |
| D-10 | Five single-action objectives including a design objective | DECIDED (author, 2026-09-24) | Marker feedback; doc 02 section 8 | Supervisor |
| D-11 | Hotspot rule A8: ward z score at least 1.5 and at least 10 incidents; sensitivity reported | DECIDED (author, 2026-09-24) | Simple, explainable, testable against planted hotspots | Author |
| D-12 | Exports: CSV and PDF required (PDF through barryvdh/laravel-dompdf); Excel not built | DECIDED (author, 2026-09-24) | Proposal figure lists PDF, Excel, CSV; Excel is first to cut under time pressure | Author |
| D-13 | Time zone Africa/Nairobi, local time stored | DECIDED | Kenya has no daylight saving; hour-of-day analytics stay simple | n/a |
| D-14 | Usability measured with SUS and task metrics, small n reported honestly | DECIDED (author, 2026-09-24) | Standard, cheap, comparable | Supervisor |
| D-15 | Instruments split by data type with analysis plans | DECIDED | Marker comment | n/a |
| D-16 | The author writes all thesis prose; AI assistance is logged and disclosed per policy | DECIDED | Assessment guide lists AI-generated content as a reject reason | n/a |
| D-17 | `docs/` tracked in the repo except `docs/reference/` (proposal, marks, faculty material) | DECIDED | Reference material contains personal and assessment details | n/a |
| D-18 | Demo runs locally on a clean checkout; an optional private demo host is not part of scope | DECIDED (author, 2026-09-24) | Keeps deployment risk low; proposal does not require hosting | Author |
| D-19 | Authentication with Laravel Fortify (headless) and own Bootstrap 5 Blade views; registration, e-mail reset, 2FA and passkeys disabled | DECIDED (author, 2026-09-24; revised from Breeze) | Breeze views are built on Tailwind, which conflicts with D-05; Fortify supplies tested login, logout, throttling and password confirmation without any styling to strip out | Author |
| D-20 | Population-based rates (A7) deferred: counts, shares and ranks only, unless a verified official ward population table becomes available | DECIDED (author, 2026-09-24) | No verified ward population source yet (VERIFY KNBS 2019 census by ward) | Author |
| D-21 | Caching only if performance targets are missed, with invalidation tested first | DECIDED (author, 2026-09-24) | Avoids stale-data bugs | Author |
| D-22 | PHP 8.4 instead of 8.3 | DECIDED (author, 2026-09-24) | 8.4 is the machine's installed runtime and is supported by Laravel 12; `composer.json` pins the platform to 8.4 so dependencies resolve the same on any machine. The proposal names PHP without a version, so it needs no change | Author |
| D-23 | Preflight scope: `app:preflight` checks APP_KEY, app DB settings, test DB separation and ADMIN_INITIAL_PASSWORD; the web middleware checks only APP_KEY and app DB settings on every request | DECIDED (author, 2026-09-24) | The initial admin password is only needed when seeding; not requiring it at runtime lets the operator remove it from `.env` after the first seed instead of keeping a live admin password on disk | Author |
| D-24 | No public routes except the login form: the `/up` health route and the local-disk `storage/{path}` route are removed | DECIDED (author, 2026-09-24) | Neither is needed for a local demo; every remaining route has an entry in the route access matrix test | Author |
| D-25 | Local database account: `root` with a blank password for the app and test schemas, shipped in `.env.example`; preflight relaxes the DB password and separate-user rules only when `APP_ENV` is not `production` | DECIDED (author, 2026-09-24) | Local presentation project on the author's own machine; removes password setup. Kept: placeholder rejection, separate `*_test` schema, APP_KEY and admin password checks, full rules under production. Recorded as a deliberate exception to the project security rules | Author |
| D-26 | Private working material stays local and is excluded from git through the repository's local exclude file: assistant instruction files and tool folders, the AI assistance log, defence notes, the proposal-feedback digests (docs 00, 08) and the planning docs (01, 07) | DECIDED (author, 2026-09-24) | Only files relevant to the system and its evidence are published; private notes and assessment feedback are not deliverables | Author |
| D-27 | Metric clarifications recorded in docs/02 section 4 (universes of A7, A8, A9; A4 previous period; nearest-rank percentiles for A10; zero-share conventions); no metric was redefined | DECIDED (Phase 5, 2026-09-24) | Needed so the application and the independent oracle compute the same thing; each is covered by TC-AC-01 | Author |
| D-28 | Location reference data: 17 sub-counties (the IEBC 2012 constituencies of Nairobi City County) and 85 wards loaded from `database/seeders/data/*.csv`, marked VERIFY; stations are synthetic, one per sub-county, named "... Sample Station" | DECIDED (author, 2026-09-24) | Unblocks the build; the author verifies names against the official gazette before relying on them in the thesis | Author |
| D-29 | Build run mode: Phases 2 to 6 run continuously from one approved plan; each phase ends with tests, an independent review, logs and a commit on `phase/<n>-<slug>` merged into `main`; the author pushes | DECIDED (author, 2026-09-24) | Keeps momentum to the November demo while each phase stays reviewable in git history | Author |
| D-30 | UI: civic dashboard shell with light and dark themes (Bootstrap 5.3 colour modes), reusable Blade components, colour-blind-safe chart palette (Okabe-Ito) | DECIDED (author, 2026-09-24) | Responsive from 360 px and accessible (NFR-07, NFR-08) | Author |
| D-31 | New users (and any pre-existing user rows) default to role Reporting Officer with no station, which sees and creates nothing until an administrator assigns a station; every account must change its password at first login | DECIDED (author, 2026-09-24, in the approved plan) | Least privilege by default | Author |
| D-32 | Audit log immutability enforced in the database: BEFORE UPDATE and BEFORE DELETE triggers on `audit_logs`, and `restrict` on its user key | DECIDED (Phase 2 review, 2026-09-24) | The Eloquent guard alone can be bypassed by query-builder or console writes | Author |
| D-33 | Users are never deleted, only deactivated; every user foreign key uses `restrict` | DECIDED (Phase 2 review, 2026-09-24) | Keeps who-did-what provenance for incidents, imports, exports and audit entries (FR-03, FR-12) | Author |
| D-34 | Import error reports store row number, column and rule ID only; uploaded files are deleted after commit or discard | DECIDED (Phase 2 review, 2026-09-24) | A rejected cell may contain exactly the PII that V6 refused | Author |
| D-35 | New incidents default to `is_synthetic = true` | DECIDED (Phase 2 review, 2026-09-24) | Everything in this project is sample data (D-07), including records typed in during demos | Author |
| D-36 | V6 patterns made concrete (docs/02 V6); a stand-alone 7 or 8 digit number is refused even when it is not an ID, which is accepted as a safe false positive | DECIDED (Phase 3, 2026-09-24) | Clarifies the rule without changing its intent; the message tells the user why | Author |
| D-37 | Browser tests use a dedicated `crime_dashboard_e2e` schema rebuilt per run by a guarded command, with per-run random passwords | DECIDED (Phase 3, 2026-09-24) | Keeps end-to-end tests away from development data and avoids any stored test credential | Author |
| D-38 | V8 compares only records that both have a location description; records without one are never flagged as duplicates | DECIDED (Phase 4, 2026-09-24) | The rule depends on text similarity; two empty texts would otherwise match every pair in the same ward and hour | Author |
| D-39 | The login page says when an account is locked, which reveals that the e-mail exists; anyone can keep a known account locked by guessing | DECIDED (Phase 3 review, 2026-09-24) | A locked-out officer needs to know to wait or call an administrator; the administrator can clear a lock by resetting the password; accepted for an internal system | Author |
| D-40 | Who may change an incident's status: Administrator (any), Reporting Officer (any incident at their own station), Analyst (no) | PROPOSED (Phase 3 review, 2026-09-24) | The access matrix in docs/02 did not say; officers at one station work cases together. Confirm or restrict to the officer's own records | Author |
| D-41 | An incident's station (who recorded it) may differ from its sub-county and ward (where it happened) | DECIDED (Phase 3 review, 2026-09-24) | People report at the nearest station, not always the one covering the place of the incident | Author |
| D-42 | No "remember me": sessions end when the browser closes or after SESSION_LIFETIME; a password change, reset or deactivation ends the user's other sessions | DECIDED (Phase 3 review, 2026-09-24) | A shared or lost device should not keep a long-lived login to sensitive-type data | Author |
| D-43 | The failed-attempt counter resets on a successful login or when a lock is applied; it does not decay with time | DECIDED (Phase 3 review, 2026-09-24) | Simple and testable; five failures spread over days still lock, which is the safer error | Author |
| D-44 | A record that confirmed duplicates point to cannot be deleted | DECIDED (Phase 4, 2026-09-24) | The confirmed copy is already excluded from analytics; deleting the original would remove the event entirely | Author |
| D-45 | Import CSV carries `status` and `closed_at` (optional) so historical records keep their outcome; dates only as `YYYY-MM-DD HH:MM`; text must be UTF-8; limits 5 MB and 10,000 rows per web upload (measured about 3 ms per row); the console loader has no row limit and records an import batch too; a batch is committed at most once (row lock) and only if the re-check matches the dry run; abandoned dry runs are closed after 24 hours and their files deleted | DECIDED (Phase 4 review, 2026-09-24) | A10 needs status and closing dates; day-first dates are ambiguous; a web request must stay bounded; a double submit must not double the data | Author |
| D-46 | A suspected duplicate can be confirmed only while its earlier record is counted (not deleted, not itself confirmed); a record is never linked back to its own duplicate | DECIDED (Phase 4 review, 2026-09-24) | Otherwise one real incident could disappear from every count | Author |
| D-47 | A8 for a single station (an officer's view or a station filter) compares against all wards, so it mostly flags where that station's incidents cluster | PROPOSED (Phase 5 review, 2026-09-24) | Kept as written so the rule is the same for every view; the alternative is to compare only wards where the station has incidents. The screen says which applies. Author to confirm | Author |

## 2. Risk register

| ID | Risk | L | I | Mitigation | Trigger |
|---|---|---|---|---|---|
| R-01 | Corrected proposal is late or not approved | M | H | Do phase 1 first, follow the revision checklist line by line, supervisor check before submission | No draft by Oct 1 |
| R-02 | Project seen as plain CRUD (the faculty topic guide warns against CRUD-analysis topics) | M | H | Put the weight on the analytics catalogue, data-quality engine, oracle-based accuracy evaluation, statistical hotspot rule, usability study | Supervisor or panel raises it |
| R-03 | Fieldwork permissions or participants unavailable | H | M | Ask in W1; proxy user groups; report limits honestly; keep n small but real | No permission by W3 |
| R-04 | Synthetic data criticised as unrealistic | M | M | Anchor to published statistics; document assumptions; use as evaluation instrument; state what cannot be claimed | Panel question |
| R-05 | Compressed schedule to a November demo | H | H | Critical path in the local master plan; cut list: Excel export, population rates, PDF styling; feature freeze W7 | A gate slips more than 4 days |
| R-06 | Scope creep back to maps | M | H | Project rule: no GIS; any spatial idea needs a logged decision | "Just a small map" request |
| R-07 | Machine lacks PHP, Composer, or MySQL, or wrong versions | H | M | Phase 0 verification found no MySQL 8 and PHP 8.4 without bcmath; install commands in doc 10 section 1 | Preflight fails |
| R-08 | Security defects in a system holding sensitive-type data | M | H | Security tests, role matrix, review checklist, no credential defaults, startup validation | Any failing SE test |
| R-09 | Integrity: text or work judged AI-generated or not the author's | M | H | Author writes prose, dated drafts, AI log, defence notes, walkthroughs, similarity checks | Similarity or panel questions |
| R-10 | Citation defects carried into later chapters | H | M | Reference hygiene rules; reconcile lists | Duplicates or missing entries found |
| R-11 | Engine mismatch hides SQL bugs | L | M | Tests on the same XAMPP MariaDB server as the app | Any SQLite-only pass |
| R-12 | Author cannot explain the system at the defence | M | H | Defence notes per phase; explain-as-you-build; author performs the demo run-through | Cannot answer notes questions |
| R-13 | Demo failure on the day | M | H | Clean-clone rehearsal, seeded data, backup recording, offline-capable local run | Any rehearsal failure |
| R-14 | Anchor statistics wrong or misquoted | M | M | VERIFY each against the source, record page and table | Discrepancy found |
| R-15 | Too few usability participants | M | M | Aim for 5 to 8; report n and limits | Under 5 participants |
| R-16 | A commit or push made under the wrong GitHub account | M | M | Account settings are local to this repository and checked before the first push | Wrong author on a commit |
| R-17 | Data loss of local-only reference material | L | M | Back up `docs/reference/` and `fieldwork-data/` to the author's own storage | Disk issue |
| R-18 | XAMPP MariaDB listens on all interfaces with a passwordless root (D-25), so anyone on the same network can read or change the data | M | M | Data is synthetic only; recommended: `bind-address=127.0.0.1` in `/opt/lampp/etc/my.cnf`; never run the demo on an untrusted network with 3306 open | Demo on a shared or public network |
| R-19 | MariaDB 10.4 is end-of-life and not MySQL 8; the panel may query the engine choice | L | M | Tests and app run on the same engine; defence notes explain the choice; migrating to MySQL 8 later needs only `.env` and the driver name | Panel question |

(L = likelihood, I = impact; H, M, L.)

## 3. Open questions for the author

| # | Question | Needed by |
|---|---|---|
| Q1 | What are the real demo date and final submission date? | Phase 0 |
| Q2 | What is the deadline and format for resubmitting the corrected proposal, and to whom? | Phase 0 |
| Q3 | What does the university's AI-use policy require you to disclose? | Phase 1 |
| Q4 | Does fieldwork need ethics clearance or a research permit, and who signs? | Phase 1 |
| Q5 | Which title, and which abstract length (180 to 210 or 200 to 250)? | Phase 1 |
| Q6 | Which machine is the performance-test machine, and does it have at least 16 GB RAM? (Development machine checked 2026-09-24: 14 GB RAM, 12 cores, Ubuntu 24.04) | Phase 6 |
| Q7 | Which participant groups can you realistically reach? | Phase 1 |
