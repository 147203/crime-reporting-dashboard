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
- Independent review (`pr_review_phase-6b-completion.md`, local only): APPROVE, no critical or high. Fixed: one medium (ward drill-down links looked the ward up by name, which is unique only within a sub-county; A7 and A8 rows now carry the ward id and A8 counts by id; new test with two wards of the same name) and the low items: last-administrator check now inside the transaction under a row lock, one-time password page sent with `Cache-Control: no-store`, backup file owner-only from creation and a failed write fails the backup, restore refuses server schemas and the live schema without `--replace-live` and streams the dump, temporary workbook deleted on error, weekday and hour expressions shared between analytics and the list, officer cases for drill-down and Excel, preflight test no longer needs built assets. Backup and restore rerun after the change: incidents 20,601 = 20,601, both triggers restored, audit log one row apart as before.
- Static checks: Pint passed; Larastan level 8 no errors. Final browser run: 20 of 20 passed.
- Duplicate recall explained: `sample:evaluate` now prints why each missed pair was missed. Reruns: 20,000 records (fresh schema): 356 of 400 found, 34 below similarity 0.8, 10 with a changed number, 0 other; 100,000 records: 1,766 of 2,000, 155, 79, 0. Table in EVAL_LOG.
- Black-box TC-BB-11 to 14 (`tests/e2e/account-and-drilldown.spec.ts`), desktop and 360 px: 8 of 8 passed (generated password shown once, works, forces a change; eye button; drill-down list from a dashboard value; Excel download). Evidence `docs/evidence/*-BB-11-generated-password.png` (password masked), `*-BB-12-password-shown.png`, `*-BB-13-drill-down-list.png`. These cover features already built and unit-tested, so they passed on first run.
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

## 2026-09-24 Phase 6: hardening, browser tests, performance, evaluation
- PHP suite: `php artisan test`: 284 tests, 9,660 assertions, all passed. Pint passed; Larastan level 8 no errors.
- Black-box (TC-BB-01 to 09): `npx playwright test` on the `crime_dashboard_e2e` schema (2,060 sample incidents), desktop 1366 px and mobile 360 px: 16 of 16 passed (the eight usability tasks plus an officer refused administration, and a visual tour in light and dark themes). Screenshots in `docs/evidence/`. Two defects found and fixed during the first runs: the account menu had no accessible name at 360 px (the user name is hidden there), and the auth rate limit counted page loads of the login form (a shared office address could be locked out).
- Performance (TC-PF-01): `scripts/perf.php`, 10 warm-up and 100 timed requests per page, signed in as an analyst, `php artisan serve` (single process), 105,060 incidents in `crime_dashboard_perf_e2e`. Machine: AMD Ryzen 5 7530U, 14 GB RAM, Ubuntu 24.04, PHP 8.4.25, MariaDB 10.4.32 (XAMPP), no cache. A separate 100,000-row evaluation load was running at the same time, so these figures are conservative.
- Before the index: dashboard (12 months) mean 3,211 ms, p95 3,476 ms; (24 months) p95 3,787 ms. Target p95 at most 2 s: missed. Profiling showed the category, ward and sub-county aggregates walking the whole (category_id, occurred_at) index (about 600 to 700 ms each); a covering index `incidents_analytics_covering_index` (migration 2026_09_25_000500, reversible) made the A1 to A10 set drop from 3,525 ms to 390 ms. After (below): target met on every page.
- TC-PF-02 import: 100,000-record file (109,000 rows including seeded problems) loaded through the import pipeline in 367 s; 10,000 records in 33.7 s earlier; 20,000 records in 56.3 s. Target 10,000 rows within 60 s: met.
- Security (independent pentest-style static review, report `pr_review_phase-6-pentest.md`, local only): no Critical or High; one Medium (F1: the local database shortcut applied to any environment not named production) and Low items. All fixed and tested: the shortcut now needs APP_ENV local or testing, an explicit `DB_ALLOW_LOCAL_SHORTCUT=true` and a loopback APP_URL, and root is refused otherwise (TC-UT-10); production preflight refuses debug mode, insecure cookies and non-database sessions (TC-UT-16); remember flag stripped from the query string (TC-SE-13); sessions end when the browser closes; locked-account attempts now reach the audit log (TC-SE-03); status changes, duplicate decisions and import discards re-checked under row locks; officers get 404 for other stations' incidents and see duplicate flags only for records they may view; PII guard rewritten (37 samples, TC-UT-01); tag stripping repeated until stable; security headers moved ahead of framework middleware; dev-server CSP widening only for local loopback; abandoned uploads pruned hourly (`imports:prune`) and on the imports page; quality page reads only per-rule counts; sample commands limited to local or testing.

## 2026-10-03 Phase 6b: development completion (branch phase/6b-completion, base 997adad)

- Unit, feature, security, accuracy: `php artisan test` on `crime_dashboard_test`: 310 passed (11,252 assertions), up from 284. New: TC-UT-17 (password generator), TC-UT-18 (V8 numbers), TC-FT-55 to 62 (generated password, unlock, eye toggle, drill-down totals, backup and restore guards, preflight server and asset checks and fixes), TC-SE-19 (no self password reset through the admin form), TC-SE-20 (last active administrator kept, also from a stale form), TC-AC-07 (Excel rows equal CSV rows, text cells only).
- Black-box: `npx playwright test`: 20 of 20 passed (16 earlier plus TC-BB-10 in two themes at two widths). The login helper now targets the password field by id, because the eye button also carries the word "Password".
- Accessibility (TC-BB-10, NFR-08): axe-core, WCAG 2.1 A and AA rules, `/login` and 12 signed-in pages. First run: serious or critical issues on 11 of 13 pages: colour contrast (dimmed status badge, hotspot links, inline code, outline buttons in the dark theme, darkest heat-grid cells), wide tables not reachable by keyboard, and a tab list whose children were links. All fixed; final run: none in light or dark, at 1366 px or 360 px.
- Backup and restore (NFR-06): `php artisan db:backup` wrote `crime_dashboard-20261003-132541.sql.gz` (641,970 bytes); `php artisan db:restore <file> --target=crime_dashboard_restore_e2e` loaded it. Every table's row count and `CHECKSUM TABLE` value matched the source except `audit_logs` (6 against 5): the backup records its own audit entry after the dump is taken, as designed. Both triggers were restored. Result: Pass.
- Duplicate rule after D-48 (b), `sample:evaluate`: see the accuracy table.
- Static checks: Pint passed; Larastan level 8 no errors.

## 2026-10-13 User CRUD (D-52): detail page, Actions menu, confirmed delete

- `php artisan test` on `crime_dashboard_test`: 322 passed (11,311 assertions). New: TC-FT-64 (detail page and activity), TC-FT-65 (unused account deleted only with the confirmation field, audited, sessions ended), TC-SE-21 (used accounts, own account and last active administrator refused; disabled menu items show the reason); route matrix extended to `admin.users.show` and `admin.users.destroy`.
- `npx playwright test`: 30 of 30 passed. TC-BB-15 (desktop and 360 px): View from the Actions menu (no Delete on the detail page), Delete opens the confirmation dialog, Cancel keeps the account, Confirm deletes it; own account shows Delete disabled. Evidence `docs/evidence/*-BB-15-user-actions-menu.png`, `*-BB-15-user-delete-confirm.png`.
- Defect found and fixed during TC-BB-15 at 360 px: with a wide table, a visually hidden column label (absolutely positioned) was placed against the page instead of the table's scroll box, so the phone widened the whole page to 446 px and controls were mis-hit. Fixed for every table by giving `.table-responsive` `position: relative`; accessibility scan and all browser tests rerun green.
- The first runs of the 2026-10-13 commits `4cb8181` and `383568f` were pushed before this run (MariaDB was stopped); this entry is their first full test run.

## Performance table (fill from TC-PF runs)

| Page or action | Rows in database | Mean ms | p50 ms | p95 ms | Cache | Machine | Date |
|---|---|---|---|---|---|---|---|
| Dashboard, last 12 months (before covering index) | 105,060 | 3,211 | 3,180 | 3,476 | none | Ryzen 5 7530U, 14 GB | 2026-09-24 |
| Dashboard, last 24 months (before covering index) | 105,060 | 2,687 | 3,532 | 3,787 | none | same | 2026-09-24 |
| Dashboard, last 12 months | 105,060 | 448 | 408 | 689 | none | same | 2026-09-24 |
| Dashboard, last 24 months | 105,060 | 868 | 837 | 1,048 | none | same | 2026-09-24 |
| Dashboard, one category | 105,060 | 339 | 330 | 365 | none | same | 2026-09-24 |
| Incident list, page 1 | 105,060 | 52 | 51 | 57 | none | same | 2026-09-24 |
| Incident list, filtered and sorted | 105,060 | 281 | 270 | 327 | none | same | 2026-09-24 |
| Reports page | 105,060 | 99 | 99 | 106 | none | same | 2026-09-24 |

## Accuracy table (fill from TC-AC runs)

| Measure | Result | Target | Verdict | Date |
|---|---|---|---|---|
| A1 to A10 vs independent oracle (1,000 records, 8 cases) | 5,387 of 5,387 values equal | 100 % | Pass | 2026-09-24 |
| Export fidelity (CSV rows = A1; 2,500-record export, each once) | equal | 100 % | Pass | 2026-09-24 |
| Rejection recall per rule V1 to V6 (1,000 / 20,000 / 100,000) | 1.000 at every size | at least 0.95 | Pass | 2026-09-24 |
| False rejections (1,000 / 20,000 / 100,000) | 0 / 0 / 0 | near 0 | Pass | 2026-09-24 |
| Duplicate precision (1,000 / 20,000 / 100,000) | 1.000 / 0.943 / 0.775 | at least 0.90 | Pass, Pass, Fail at 100,000 | 2026-09-24 |
| Duplicate recall (1,000 / 20,000 / 100,000) | 1.000 / 0.915 / 0.920 | at least 0.90 | Pass | 2026-09-24 |
| Duplicate precision after D-48 (b) (1,000 / 20,000 / 100,000) | 1.000 / 1.000 / 0.998 | at least 0.90 | Pass | 2026-10-03 |
| Duplicate recall after D-48 (b) (1,000 / 20,000 / 100,000) | 1.000 / 0.890 / 0.883 | at least 0.90 | Pass, Fail, Fail (just below) | 2026-10-03 |
| Excel export fidelity (rows equal CSV, text cells only) | equal | 100 % | Pass | 2026-10-03 |
| Planted hotspots recovered at z 1.5, n 10 (100,000 records) | 3 of 3, 0 unplanted | report | see EVAL_LOG | 2026-09-24 |
