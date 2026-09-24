# Implementation run log (as executed)

Commands run, outputs, deviations, in order. Raw material for Chapter 5.2 and 5.4. Never paste secrets, tokens, or passwords.

## Template

```
## <date> Phase <n> <short title>
- Commands:
- Output (trimmed):
- Result and any deviation from plan:
- Files changed:
- Evidence captured (figure IDs):
- Requirement IDs covered:
```

## Entries

## 2026-09-24 Phase 0 Setup (scaffold, preflight, quality tools, test harness)
- Environment check: PHP 8.4.25 (8.2 also present, no 8.3), extension bcmath missing on 8.4; Composer 2.8.9; Node 22.18, npm 11.9; git 2.43; no MySQL 8 (XAMPP MariaDB 10.4.32 listening on *:3306); Ubuntu 24.04, 14 GB RAM, 12 cores. Decisions D-04, D-22 taken by the author.
- Commands:
  - `composer create-project laravel/laravel:^12.0 <temp-dir>/skeleton --no-scripts --no-install` (v12.12.2), copied into the repo with rsync excluding `.env*`, `.gitignore`, `README.md`, the project rules, `docs/`
  - `composer require laravel/fortify` (^1.40), `composer require --dev larastan/larastan` (^3.12); removed `laravel/sail`, `laravel/pail`; `composer audit`: no advisories
  - `php artisan vendor:publish --tag=fortify-config` (no `--force`)
  - `npm install --save-dev bootstrap@^5.3 @popperjs/core@^2.11`, removed Tailwind and axios; `npm run build` OK; `npm audit`: 0 vulnerabilities
  - `vendor/bin/pint` (added `declare(strict_types=1)` to skeleton files), `vendor/bin/phpstan analyse` (level 8)
  - `php artisan app:preflight` without `.env`: exit 1, five failures named (APP_KEY, DB_DATABASE, DB_USERNAME, DB_PASSWORD, ADMIN_INITIAL_PASSWORD)
- Deviations from the skeleton, with reason:
  - Composer scripts `setup` (ran `migrate --force`) and `post-update-cmd` (ran `vendor:publish --force`) removed: project rule against `--force` on migration-related commands.
  - `config/database.php`: default connection `mysql`; no default database, user or password (skeleton defaulted to `root` with an empty password); `DB_URL` override removed from `mysql`; session time zone `+03:00`; new `mysql_testing` connection.
  - `DatabaseSeeder` no longer creates `test@example.com` with password `password`.
  - Public routes `/up` and `storage/{path}` removed (D-24).
  - rsync exclusion also skipped nested `.gitignore` placeholders in `storage/`, `bootstrap/cache/`, `database/`; restored from the skeleton, and root `.gitignore` rules that ignored whole storage folders were removed so a fresh clone keeps them.
  - Larastan findings fixed at the cause: `rtrim(env(...))` in `config/filesystems.php` could receive a bool under strict types; tests switched from `artisan()` to `Artisan::call`.
- Result: scaffold, preflight (command, middleware, tests), Fortify login and logout with Bootstrap views, route access matrix harness, Pint and Larastan clean. MySQL-backed tests blocked until MySQL 8 is installed (see TEST_LOG).
- Files changed: see the session report; key files `app/Support/Preflight.php`, `app/Http/Middleware/EnsurePreflightPasses.php`, `app/Console/Commands/PreflightCommand.php`, `app/Providers/AppServiceProvider.php`, `bootstrap/app.php`, `routes/web.php`, `config/{database,fortify,filesystems,admin,security,app}.php`, `phpunit.xml`, `tests/**`, `resources/views/**`, `.env.example`, `.gitignore`, `composer.json`, `package.json`, `pint.json`, `phpstan.neon`, docs 03, 09, 10, README.
- Independent review (`pr_review_phase-0-setup.md`, gitignored): verdict REQUEST CHANGES. Fixed in this session: unused mariadb, pgsql, sqlsrv and sqlite connections with `root` defaults removed (blocker); preflight now fails when `DB_CONNECTION` is not mysql and when the test schema does not end in `_test`; all Fortify routes throttled (`auth-actions`); CSRF test added; route-matrix "allowed" assertion tightened; session defaults hardened (encrypt true, SameSite strict); tests log to the null channel; unused Fortify passkeys config removed (it crashed every artisan command under strict types when APP_URL was empty); middleware 503 built without the session. Deferred to Phase 3 with the feature they belong to: account lockout (LOGIN_LOCKOUT_MINUTES), security-headers middleware, role columns of the route matrix, deterministic Faker seed, rate-limiting the preflight critical log line.
- Evidence captured (figure IDs): none yet; capture after MySQL is up (green test run, preflight output, login page at 360 px and desktop).
- Requirement IDs covered: FR-01 (login and logout only, no registration), NFR-01 (startup validation, fail-closed routes), NFR-09 (Pint, Larastan).

## 2026-09-24 Phase 0 change: XAMPP MariaDB with the local root account (D-04 revised, D-25)
- Author's decision: use XAMPP MariaDB 10.4 on 3306 with `root` and a blank password for a local presentation project, and document it in the project rules. MySQL 8 was never installed.
- Changes: both connections use the `mariadb` driver, default port 3306; `.env.example` ships `DB_USERNAME=root`, blank `DB_PASSWORD`, the same for the test schema; `Preflight` enforces the DB password length and separate-user rules only when `APP_ENV=production`, and still rejects `changeme` placeholders and a test schema that does not end in `_test` or equals the app schema; TC-FT-00 now asserts MariaDB and a `*_test` schema; new TC-UT-10, TC-UT-11. project rules, README, docs 00, 01, 02 (NFR-01), 03, 06, 09 (D-04, D-25, R-11, R-18, R-19), 10 updated.
- Checks: Pint passed; Larastan level 8 no errors; unit suite 11 tests OK. MariaDB was stopped at the time, so the database tests are not yet run.

## 2026-09-24 Phase 0 gate run
- Author ran key:generate, app:preflight (passed), migrate (created `crime_dashboard`, 3 migrations), test (32 passed). Details in TEST_LOG.
- Gate G0: tests on the database pass; preflight with tests pass; Pint and Larastan clean; push pending (GitHub account setup).


## 2026-09-24 Phase 0 gate closed
- First commit `044e26b` pushed by the author to the private repository; `main` tracks `origin/main`.
- Gate G0: database tests pass (32), preflight with tests, Pint and Larastan clean, push done. D-03, D-05, D-06 awaiting confirmation.

## 2026-09-24 Phase 2 Requirements and design (schema)
- Commands: `php artisan migrate`, `php artisan migrate:rollback --step=4`, `php artisan migrate`, `php artisan db:seed`; tests in a clean worktree of the commit.
- Built: migrations for sub_counties, wards, stations, crime_categories, users security columns, incidents, import_batches, audit_logs (append-only triggers), report_exports; enums Role, IncidentStatus, DuplicateStatus; models with station and countable scopes; factories; reference data seeder with 17 sub-counties and 85 wards (VERIFY, D-28) and 17 sample stations; Mermaid design diagrams in `docs/diagrams/`.
- Deviation: validation V1 to V7 including the PII guard moves into Phase 3 with the incident form; Phase 4 keeps duplicates, import, generator and oracle.
- Result: gate G2 passed (see TEST_LOG). Commit `2ec8b96` on `phase/2-design`.
- Requirement IDs: FR-02 (scope), FR-04 (reference data), FR-12 (audit table), NFR-02 (no identifiers), NFR-06 (reversible migrations).

## 2026-09-24 Phase 3 Build P0: skeleton
- Built: Fortify login with lockout and inactive-account refusal, forced first-login password change, security headers middleware (CSP with nonce), role middleware, account-usable middleware, audit logger, incident create, list (filters, search, sort, pagination), show, edit, status workflow, soft delete with station scoping in queries and policies, user management, reference data management, audit log view, `user:create` command, `AdminSeeder` from ADMIN_* only, UI shell with reusable Blade components (layout, page header, card, KPI, chart with table view, form fields, badges, empty state, confirm modal, sort link), SCSS design tokens, light and dark themes, Playwright harness on a separate `crime_dashboard_e2e` schema.
- Dependencies added: `sass`, `bootstrap-icons`, `chart.js`, `@playwright/test` (dev).
- Deviation: validation V1 to V7 including the PII guard was built here with the form (planned for Phase 4).
- Result: gate G3 passed (TEST_LOG). Commit `41861cd` on `phase/3-skeleton`.
- Requirement IDs: FR-01 to FR-06, FR-12, NFR-01, NFR-02, NFR-07, NFR-08.

## 2026-09-24 Phase 4 Build P1: data quality
- Built: duplicate detector (V8) on the form and import paths, duplicate review queue, CSV import (upload, dry run, per-row error report without cell values, commit, discard, error report download), data-quality summary, deterministic sample-data generator with ground-truth files, `sample:generate` and `sample:clear`, the independent oracle script and its expected values for eight filter cases.
- Commands: `php artisan sample:generate --size=1000 --seed=20260924 --end=2026-08`, `php scripts/oracle.php --input <dir> --filters tests/Fixtures/oracle/filters.json --out tests/Fixtures/oracle/expected_metrics_1000.json`, `DB_DATABASE=crime_dashboard_e2e php artisan sample:generate --size=10000 --load`.
- Deviation: a defect was found by the accuracy test before commit: the import read `Validator::failed()` without running the rules, so V2, V3, V5 and V6 were skipped; the database CHECK constraint stopped the first bad row. Fixed (`passes()` before `failed()`).
- Result: gate G4 passed (TEST_LOG). Commit `8e50bc6` on `phase/4-quality`.
- Requirement IDs: FR-07, FR-08, FR-13, FR-14, NFR-04 (import), NFR-05 (data quality).
