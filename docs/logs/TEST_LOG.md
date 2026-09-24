# Test log

Every test run that matters, before and after fixes. Definitions and targets: `../06_TESTING_AND_EVALUATION.md`.

## Template

```
## <date> <level> <what was run>
- Commit:
- Command:
- Environment: PHP, Laravel, MySQL versions; machine
- Result: passed / failed counts, key failures
- Follow-up: fixes made, re-run result
- Evidence (figure IDs):
```

## Runs

## 2026-09-24 UT, FT, SE first runs (Phase 0)
- Commit: none yet (repository has no commits)
- Environment: PHP 8.4.25, Laravel 12 (skeleton v12.12.2), PHPUnit 11.5.56; MySQL 8 not installed; Ubuntu 24.04, 14 GB RAM
- Run 1, `php artisan test` with no `.env`: 8 passed (TC-UT-01 to 08), 18 failed. Every failure is the intended test-database guard in `tests/TestCase.php` ("Refusing to run tests ..."), raised before any migration. This is the guard failing for the right reason.
- Run 2, same suite with throwaway values in process environment variables (no `.env` written): 8 passed; 6 failed in `LoginTest` (TC-FT-00, 03 to 07), all `SQLSTATE[HY000] [2002] Connection refused (Connection: mysql_testing, Port: 3307)`; 12 warnings on the non-DB feature tests, caused by the `artisan test` runner reading a missing `.env` file. Plain `vendor/bin/phpunit --filter=PreflightMiddleware`: OK (2 tests).
- Static checks: `vendor/bin/pint --test` passed; `vendor/bin/phpstan analyse` level 8: no errors.
- Follow-up: rerun the full suite on MySQL 8 once installed; record the result here and in the gate report.
- Evidence (figure IDs): pending the MySQL run.

## 2026-09-24 Rerun after the independent review fixes (Phase 0)
- Commit: none yet
- Changes tested: preflight rejects a non-mysql `DB_CONNECTION` and a test schema not ending in `_test` (TC-UT-06, TC-UT-09); CSRF enforced on every POST route (TC-SE-05); login throttle (TC-SE-06); password-confirmation throttle (TC-SE-07); stricter "allowed" assertion in the route matrix (TC-SE-01b); middleware 503 built without the session so it also works with no APP_KEY (TC-SE-04).
- Command: `vendor/bin/phpunit` with throwaway values in process environment variables (no `.env`)
- Result: 30 tests, 53 assertions; 21 passed; 9 errors, all `SQLSTATE[HY000] [2002] Connection refused (Connection: mysql_testing, Port: 3307)` in `LoginTest` (6) and `CsrfAndThrottleTest` (3). `DB_CONNECTION=sqlite php artisan app:preflight`: fails with "DB_CONNECTION must be mysql." Pint: passed. Larastan level 8: no errors.
- Test ID note: IDs now follow doc 06 (TC-SE-01 is the route x role matrix, split into 01a completeness and 01b per actor); TC-SE-02 (SQL injection) and TC-SE-03 (lockout) stay reserved for Phase 3.

## 2026-09-24 First full run on the database (Phase 0, gate G0)
- Commit: none yet
- Command: `php artisan key:generate`, `php artisan app:preflight`, `php artisan migrate`, `php artisan test` (run by the author)
- Environment: PHP 8.4.25, Laravel 12, PHPUnit 11.5, XAMPP MariaDB 10.4.32 on 127.0.0.1:3306 (root, D-25); Ubuntu 24.04, 14 GB RAM
- Result: preflight passed; three skeleton migrations applied to `crime_dashboard`; tests 32 passed, 89 assertions, 5.73 s. TC-FT-00 confirms the suite ran on MariaDB against the `*_test` schema.
- Deviation: `crime_dashboard` did not exist before `migrate`; Laravel offered to create it and the author accepted.
- Static checks re-run: Pint passed; Larastan level 8 no errors.
- Evidence (figure IDs): screenshot of this run still to capture.

## 2026-09-24 Phase 2 gate: schema, reference data, design (commit 2ec8b96)
- Command: `vendor/bin/phpunit` in a clean checkout of commit `2ec8b96` (own vendor copy, process env, MariaDB 10.4.32)
- Result: 45 tests, 133 assertions, all passed. New: TC-FT-10 to TC-FT-20 (no coordinate columns, named indexes, reference data 17/85 and per-sub-county counts, station scope, countable scope, no personal-identifier columns, composite ward key, CHECK constraints, append-only audit through Eloquent and raw SQL, users cannot be deleted), TC-UT-12 (status workflow), TC-SE-08 (mass assignment).
- Reversibility (NFR-06): `php artisan migrate:rollback --step=4` then `php artisan migrate` on the development schema, twice, both clean; reference seeder idempotent.
- Static checks: Pint passed; Larastan level 8 no errors.
- Independent review: REQUEST CHANGES (no security blocker); fixes applied before the commit (station not fillable, audit triggers and restrict keys, composite ward key, CHECK constraints, synthetic default, countable scope independent of soft-delete scope, LF line endings). Decisions D-32 to D-35.

## 2026-09-24 Phase 3 gate: skeleton, auth, incidents, admin (commit 41861cd)
- Command: `vendor/bin/phpunit` in a clean checkout of commit `41861cd` (own vendor, fresh asset build, process env, MariaDB 10.4.32)
- Result: 198 tests, 697 assertions, all passed. Pint passed; Larastan level 8 no errors.
- Coverage added: route x role matrix for every route and 4 actors (TC-SE-01a, 01b, 108 cases), station IDOR (TC-SE-09), lockout (TC-SE-03), SQL injection strings in every list filter (TC-SE-02), headers with CSP nonce (TC-SE-10, 10b), stored XSS escaped (TC-SE-11), sessions ended on password change (TC-SE-12), remember-me ignored (TC-SE-13), inactive station (TC-SE-14), validation V1 to V6 (TC-FT-02), status workflow, soft delete, filters, user management, reference data, admin seeder, console user creation, PII guard 26 samples (TC-UT-01).
- Mutation check: an officer delete permission planted in `IncidentPolicy` turned 2 tests red (matrix and IDOR); restored, green.
- Browser check: Playwright visual tour at 1366 px and 360 px, light and dark; one defect found and fixed (sidebar background transparent on desktop because Bootstrap's `offcanvas-lg` forces it).
- Independent review: REQUEST CHANGES; fixed before the commit: lockout race (row lock per attempt), other sessions ended and remember token rotated on password change, reset or deactivation, remember-me disabled, password-change throttle and audit, PII guard formats (brackets, (0), slashes, grouped IDs, full-width and zero-width tricks, shilling amounts), tag stripping that no longer cuts text at "<", ward move refused when it has incidents, deactivated category or station kept on edit, officer at inactive station cannot create, e2e guard on the default connection, security headers on the preflight 503. Decisions D-39 to D-43 (D-40 awaits the author).

## 2026-09-24 Phase 4 gate: data quality (commit 8e50bc6)
- Command: `vendor/bin/phpunit` in a clean checkout of `8e50bc6` (own vendor via `composer install`, fresh assets, MariaDB 10.4.32). Result: 252 tests, 884 assertions, all passed. Pint passed; Larastan level 8 no errors.
- TC-AC-02 rejection recall on the seeded 1,000-record set (1,090 rows): V1 1.00, V2 1.00, V3 1.00, V4 1.00, V5 1.00, V6 1.00 (a row counts only when its seeded rule is reported); false rejections 0. Target at least 0.95 per rule: met.
- TC-AC-03 duplicate detection on the same set (20 true pairs with retyped locations, 10 near misses): flagged 20, hits 20, precision 1.000, recall 1.000. Target at least 0.90 each: met.
- 10,000-record load (10,900 rows incl. seeded problems) through the import pipeline on the e2e schema: 33.7 s (about 3 ms per row), 10,300 accepted, 600 rejected, 207 flagged against 200 planted duplicates (so about 7 accidental matches at this size: different addresses on one landmark within the window). Target 10,000 rows within 60 s: met. Note: this load ran before the retyped-duplicate change; re-measure in Phase 6.
- TC-AC-04 determinism: same seed gives identical files; the incidents.csv SHA-256 for (300, seed 7, 2026-08) is pinned in the test.
- Independent oracle (`scripts/oracle.php`, written without access to application code): deterministic (byte-identical output on two runs); 1,030 accepted rows, equal to the application's import.
- Independent review: REQUEST CHANGES; fixed before the commit: atomic commit under a row lock (double submit), strict `YYYY-MM-DD HH:MM` import dates, UTF-8 check per row and PII guard reading Windows-1252 text, abandoned dry-run pruning and file deletion on any failure, console loads recorded as import batches, per-rule counts over all errors, reference-number update limited to the batch, review refuses a confirmation that would remove an incident from every count and never links a record to its own duplicate, closest-in-time candidates, character-based similarity, delete rule checked under a lock, sample:clear audited, realistic retyped duplicates and text near misses in the generator, commit refused when the re-check differs from the dry run, web upload limit 10,000 rows. Decisions D-45 (revised), D-46.

## 2026-09-24 Phase 5 gate: analytics, dashboard, exports (commit a38ca30)
- Command: `vendor/bin/phpunit` in a clean checkout of `a38ca30`. Result: 272 tests, 9,632 assertions, all passed. Pint passed; Larastan level 8 no errors.
- TC-AC-01 accuracy: application A1 to A10 compared with the independent oracle on the seeded 1,000-record dataset for 8 filter cases: 5,387 values compared, 0 mismatches (exact for integers and nulls; floats already rounded by both sides compared within 1e-9). Target 100 percent match: met.
- Mutation checks: a continuous median (PERCENTILE_CONT) planted in A10 made TC-AC-01 fail at `all.A10.report_delay_hours.median`; paging the export by id made TC-AC-06 fail. Both restored, green.
- TC-AC-05 and TC-AC-06 export fidelity: CSV rows equal A1 for the same filters and scope; a 2,500-record export has every record exactly once and in date order. Target 100 percent: met.
- Independent review: REQUEST CHANGES; fixed: export paging (blocker: sorting by date while paging by id skipped and repeated rows beyond 1,000), oracle comparison of null values, time bands read from config, dompdf hardened (no remote files, no JavaScript, restricted file root), sample-data notice checks the records, flagged hotspots listed in the PDF, captions say when A8 ignores a ward filter, when a station view is compared county-wide (D-47, author to confirm) and when the last month is incomplete, filter label computed once, oracle sanity facts updated to the regenerated dataset. Clarifications recorded as D-27.

## Performance table (fill from TC-PF runs)

| Page or action | Rows in database | Mean ms | p50 ms | p95 ms | Cache | Machine | Date |
|---|---|---|---|---|---|---|---|

## Accuracy table (fill from TC-AC runs)

| Measure | Result | Target | Verdict | Date |
|---|---|---|---|---|
