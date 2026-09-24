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

## 2026-09-25 Phase 2 gate: schema, reference data, design (commit 2ec8b96)
- Command: `vendor/bin/phpunit` in a clean checkout of commit `2ec8b96` (own vendor copy, process env, MariaDB 10.4.32)
- Result: 45 tests, 133 assertions, all passed. New: TC-FT-10 to TC-FT-20 (no coordinate columns, named indexes, reference data 17/85 and per-sub-county counts, station scope, countable scope, no personal-identifier columns, composite ward key, CHECK constraints, append-only audit through Eloquent and raw SQL, users cannot be deleted), TC-UT-12 (status workflow), TC-SE-08 (mass assignment).
- Reversibility (NFR-06): `php artisan migrate:rollback --step=4` then `php artisan migrate` on the development schema, twice, both clean; reference seeder idempotent.
- Static checks: Pint passed; Larastan level 8 no errors.
- Independent review: REQUEST CHANGES (no security blocker); fixes applied before the commit (station not fillable, audit triggers and restrict keys, composite ward key, CHECK constraints, synthetic default, countable scope independent of soft-delete scope, LF line endings). Decisions D-32 to D-35.

## Performance table (fill from TC-PF runs)

| Page or action | Rows in database | Mean ms | p50 ms | p95 ms | Cache | Machine | Date |
|---|---|---|---|---|---|---|---|

## Accuracy table (fill from TC-AC runs)

| Measure | Result | Target | Verdict | Date |
|---|---|---|---|---|
