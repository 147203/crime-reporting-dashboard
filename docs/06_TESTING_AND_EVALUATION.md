# 06. Testing and evaluation

Implements proposal 3.6 (unit, integration, system, black-box, usability, performance testing) and objective iv (functionality, accuracy, usability), and adds a security level because the system holds sensitive kinds of data. IDs: `TC-<level>-<nn>`; levels UT, FT (feature or integration), ST (system), BB (black-box end to end), SE (security), PF (performance), AC (accuracy), US (usability).

## 1. Test pyramid

| Level | Tool | Scope | Examples |
|---|---|---|---|
| Unit (UT) | PHPUnit | Pure classes: PiiGuard, DuplicateDetector similarity, Period and Filters, hotspot z score, moving average | TC-UT-01 phone pattern detected; TC-UT-02 z score with sigma 0 returns no flags |
| Feature or integration (FT) | PHPUnit with HTTP tests on MySQL | Controller to service to database | TC-FT-01 officer creates incident in own station; TC-FT-02 ward not in sub-county is rejected |
| System (ST) | PHPUnit and scripted scenarios | Complete workflows across modules | TC-ST-01 import 1,000 rows then dashboard totals equal accepted rows |
| Black-box end to end (BB) | Playwright | Real browser, inputs and visible outputs only | TC-BB-01 login, report, see in list; TC-BB-02 apply filter, chart and table update |
| Security (SE) | PHPUnit and manual checklist | Auth, roles, injection, XSS, CSRF, headers, throttling | TC-SE-01 every route x role matrix; TC-SE-02 SQL injection strings in filters do not change results; TC-SE-03 lockout after five failures |
| Performance (PF) | Timed feature tests, `k6` or `ab` | Response times at 100,000 rows | TC-PF-01 dashboard p95; TC-PF-02 import 10,000 rows |
| Accuracy (AC) | PHPUnit against oracle files | A1 to A10 equal the oracle; data-quality rates | TC-AC-01 all metrics for the fixed filter list; TC-AC-02 rejection recall |
| Usability (US) | Sessions with real participants | Task success and SUS | TC-US-01 to TC-US-08 tasks from doc 05 |

## 2. Rules

- Every requirement ID (FR, NFR) is covered by at least one test case; keep the traceability table in section 6 current.
- Authorization: every route x role pair has an allowed and a denied test, generated from the route list so a new route without a test fails the suite.
- Tests run against a separate `*_test` schema on the same XAMPP MariaDB server (D-25). Never point tests at the application database; `tests/TestCase.php` enforces this.
- Fixed seeds; no test depends on wall-clock time (freeze the clock).
- Analytics tests use the oracle files from doc 04; expected values are never copied from the application's own output.
- A failing test is investigated, not deleted. Weakening an assertion needs a logged reason.
- Record commands and results in `docs/logs/TEST_LOG.md` after each run that matters (before and after fixes).

## 3. Functional and system coverage (minimum)

Login, logout, lockout, role redirects; user management; reference data; incident create, edit, view, filter, soft delete; status workflow; validation rules V1 to V8; duplicate review; CSV import dry run and commit; dashboards for each filter type; exports (CSV and PDF) equal to on-screen values; audit entries for every audited action; data-quality summary.

## 4. Accuracy evaluation (objective iv, "accuracy")

| Measure | Method | Target (PROVISIONAL) |
|---|---|---|
| Analytics correctness | Compare A1 to A10 with the independent oracle on the seeded dataset and a fixed filter list | 100 percent exact match |
| Validation accuracy | Seeded bad records: rejection recall per rule; valid records: false rejection rate | At least 95 percent recall; report false rejections |
| Duplicate detection | Precision and recall on seeded pairs | At least 90 percent each |
| Hotspot recovery | Planted hotspots flagged; unplanted flagged | Report recall and precision, plus the sensitivity table |
| Export fidelity | Exported CSV rows and totals equal the screen for the same filters | 100 percent |

## 5. Performance evaluation

Measure on the development machine with the 100,000-row dataset, 10 warm-up requests, then 100 timed requests per page; report mean, p50, p95, hardware string, PHP and MySQL versions, and whether caching was enabled. Record `EXPLAIN` for the three slowest queries and any index added because of them. Targets are in doc 02 NFR-04.

## 6. Security evaluation

Checklist (each item has a test or a documented manual check): no unauthenticated route except login; hashing and password policy; lockout and throttling; CSRF on writes; SQL injection strings in every filter and form field; stored and reflected XSS attempts in free-text fields; mass assignment attempts; role escalation attempts (officer requesting another station's record, analyst posting an edit); direct object reference tests; security headers present; file upload limits and type checks; audit log contains no sensitive content; preflight refuses to boot on missing secrets. Only test the local instance; never scan or probe any system you do not own.

## 7. Usability evaluation

| Item | Detail |
|---|---|
| Participants | Real people from the groups in doc 05; anonymised codes P01, P02, ... |
| Materials | Task sheet, SUS questionnaire, observer notes template, consent form |
| Metrics | Task completion, time per task, errors, SUS score, issue list with severity (cosmetic, minor, major, blocker) |
| Reporting | Table per task, SUS mean, median, range, n; list of issues fixed; be explicit that n is small |
| Rule | Never invent participants or scores |

## 8. Traceability (as built; update as tests are added)

| Requirement | Test cases (file) |
|---|---|
| FR-01 Authentication, lockout, password change | TC-FT-03 to 07 (`Auth/LoginTest`), TC-SE-03, TC-FT-21 to 25 (`Auth/AccountSecurityTest`), TC-SE-06, 07 (`Security/CsrfAndThrottleTest`) |
| FR-02 Role and station access | TC-SE-01a, 01b (`Security/RouteAccessMatrixTest`, every route x 4 actors), TC-SE-09 (`Security/StationScopeTest`), TC-FT-13, 14 (`Schema/IncidentScopeTest`) |
| FR-03 User management | TC-FT-26 to 28, 30, 31 (`Admin/AdministrationTest`), TC-UT-17, TC-FT-55 to 57, TC-SE-19, 20 (`Admin/AccountManagementTest`: generated password shown once, no self reset, last administrator kept, unlock, eye toggle) |
| FR-04 Reference data | TC-FT-12 (`Schema/SchemaTest`), TC-FT-29 (`Admin/AdministrationTest`) |
| FR-05 Incident form and validation V1 to V7 | TC-FT-01, 02 (`Incidents/IncidentWorkflowTest`), TC-FT-17, 18 (`Schema/SchemaTest`), TC-UT-01 (`Unit/PiiGuardTest`) |
| FR-06 Records: filter, status, soft delete | TC-FT-03 to 05 (`Incidents/IncidentWorkflowTest`), TC-UT-12 (`Unit/IncidentStatusTest`) |
| FR-12 Audit log | TC-FT-19 (`Schema/AuditAndMassAssignmentTest`), TC-FT-25, 28, 29 |
| NFR-01 Security | TC-SE-01 to 11, TC-FT-01, 02, 60 to 62 (preflight, database server and built-assets checks), TC-UT-01 to 11 (`Unit/PreflightTest`) |
| NFR-02 Privacy | TC-FT-10, 16 (`Schema/SchemaTest`), TC-UT-01 (`Unit/PiiGuardTest`), TC-FT-25, 28 (no secrets in audit) |
| NFR-06 Reliability (reversible migrations, backup) | Rollback and re-migrate logged in TEST_LOG (Phase 2 gate); TC-FT-59 (`BackupCommandsTest`, guards); backup and restore procedure run once and compared table by table (TEST_LOG, Phase 6b) |
| NFR-08 Accessibility | TC-BB-10 (`tests/e2e/accessibility.spec.ts`, axe WCAG 2.1 A and AA, 13 pages, light and dark, 1366 px and 360 px), TC-FT-57 (eye toggle labelled) |
| FR-07 Data quality (PII guard, duplicates, review) | TC-UT-13, 15, 18 (D-48 numbers), TC-FT-43, 48, 49, TC-AC-02, 03 (`Quality/*`) |
| FR-08 CSV import | TC-FT-40 to 42, 45 to 47, 53, TC-SE-17, 18 (`Quality/ImportAndReviewTest`, `Quality/ReviewRegressionPhase4Test`) |
| FR-09 Analytics A1 to A10 | TC-AC-01 (`Quality/AnalyticsOracleTest`, exact match with the independent oracle) |
| FR-10 Dashboards | TC-FT-50, 52, TC-SE-15 (`Quality/DashboardAndExportTest`), TC-FT-58 (`Quality/DrillDownTest`, every drill-down list total equals its chart value, for an administrator and a station officer, and with two wards of the same name), TC-BB-06 |
| FR-11 Reports and exports | TC-AC-05, TC-AC-07 (Excel rows equal CSV rows, text cells only), TC-FT-51, TC-SE-16, TC-BB-07 |
| FR-13 Data-quality summary | TC-FT-44 |
| FR-14 Generator determinism | TC-AC-04 (pinned SHA-256) |
| NFR-03 Usability | TC-US-01 to 08 (real participants, author; kit in `docs/usability/USABILITY_KIT.md`); TC-BB-01 to 09 automate the same tasks (`tests/e2e/usability-tasks.spec.ts`) |
| NFR-07 Responsiveness | TC-BB-01 to 09 at 360 px (Playwright mobile project) |
| NFR-04 Performance | TC-PF-01 (`scripts/perf.php` at 105,060 incidents), TC-PF-02 (10,000, 20,000 and 100,000-record loads); results in TEST_LOG |
| NFR-05 Accuracy | TC-AC-01 to 05 |

## 9. Chapter 5.5 results table (faculty format)

| Test case | Description | Test data | Experimental outcome | Verdict |
|---|---|---|---|---|
| TC-FT-02 | Ward outside the chosen sub-county is rejected | Sample form with mismatched ward | Validation error V4 shown, no record created | Pass |

Every row links to a test name or a log entry. Verdicts are Pass, Fail, or Fail then fixed and re-tested (both results shown).

## 10. Evidence to capture

Green test-suite output per level; the authorization matrix result; oracle comparison output; validation and duplicate rates; performance tables and `EXPLAIN` plans; Playwright run screenshots; usability task table and SUS summary; screenshots of each main screen for Chapter 5.
