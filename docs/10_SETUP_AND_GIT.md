# 10. Setup

How to run the project on a fresh machine.

## 1. Machine prerequisites

The full step-by-step guide for **Windows 10/11, Ubuntu/Debian and macOS** (installers, PATH, PHP extensions, XAMPP, checks and troubleshooting) is in the project README, sections 3, 4 and 11. Summary:

| Tool | Needed | Windows | Linux | Check |
|---|---|---|---|---|
| PHP | 8.4 (D-22) with curl, fileinfo, mbstring, openssl, pdo_mysql, zip | Zip from windows.php.net in `C:\php` (XAMPP's own PHP 8.2 is too old), plus the Visual C++ Redistributable | `php8.4-*` packages | `php -v`, `php -m` |
| Composer | 2.x | Composer-Setup.exe | getcomposer.org | `composer --version` |
| MariaDB | XAMPP's MariaDB 10.4 on 127.0.0.1:3306, root with a blank password (D-04, D-25) | `C:\xampp`, started from the XAMPP Control Panel | `/opt/lampp`, `sudo /opt/lampp/lampp startmysql` | `php artisan app:preflight` |
| Node and npm | 20.19+ or 22.12+ | nodejs.org installer | nodejs.org or distribution | `node -v` |
| Git | any recent | git-scm.com | `apt install git` | `git --version` |

A second MySQL server on port 3306 is the most common failure: Ubuntu's `mysql` service (error 1698) or the Windows `MySQL80` service (error 1045). `php artisan app:preflight` detects it and prints the fix for the system it runs on. MySQL cannot replace MariaDB here: analytics A10 uses `PERCENTILE_DISC`, which MySQL lacks.

## 2. Databases (three schemas on XAMPP MariaDB)

No database is created by hand. Laravel creates each one on first use:

| Database | Created by | Used by | Note |
| --- | --- | --- | --- |
| `crime_dashboard` | `php artisan migrate`, on its first run | The application | Your working data |
| `crime_dashboard_test` | `php artisan test`, on its first run | The test suite | Wiped on every test run; the name must end in `_test` |
| `crime_dashboard_e2e` | `npx playwright test`, on its first run | The browser tests | Wiped and rebuilt on every run; the name must end in `_e2e` |

`php artisan app:preflight` passes before the application database exists and says that `migrate` will create it. Each table is created with utf8mb4 and the `utf8mb4_unicode_ci` collation from `config/database.php`.

Recommended (R-18): XAMPP's MariaDB listens on every network interface. Add `bind-address=127.0.0.1` under `[mysqld]` in `/opt/lampp/etc/my.cnf` (Windows: `C:\xampp\mysql\bin\my.ini`), then restart MySQL.

## 2a. First run of the application

```bash
composer install && npm install && npm run build
cp .env.example .env            # Windows PowerShell: Copy-Item .env.example .env
                                # then edit .env yourself: ADMIN_NAME, ADMIN_EMAIL, ADMIN_INITIAL_PASSWORD
php artisan key:generate
php artisan app:preflight       # must print "Preflight passed."
php artisan migrate             # never with --force; answer yes to create the database
php artisan db:seed             # reference data and the first administrator from ADMIN_* in .env
php artisan sample:generate --size=20000 --load   # optional demo data (run once)
php artisan serve               # http://127.0.0.1:8000
php artisan test
```

`composer install && npm install` works in PowerShell 7 and Command Prompt; in Windows PowerShell 5.1 run the commands one at a time. Commands written as `NAME=value command` in these docs become `$env:NAME='value'; command; Remove-Item Env:NAME` in PowerShell.

## 3. Browser (end-to-end) tests

Playwright runs against a separate schema, `crime_dashboard_e2e`, which it wipes and rebuilds on every run (`php artisan e2e:prepare` refuses any database whose name does not end in `_e2e`). Test accounts get a random password generated for each run; nothing is stored.

```bash
npx playwright install chromium    # once
npm run build
npx playwright test                # desktop (1366 px) and mobile (360 px) projects
```


## 4. Backup and restore (NFR-06)

```bash
php artisan db:backup                                   # writes storage/app/private/backups/crime_dashboard-<date>-<time>.sql.gz
php artisan db:restore storage/app/private/backups/<file> --target=crime_dashboard_restore_e2e   # local or testing only; asks first
```

The backup refuses the `_test` schema, passes the database password to `mysqldump` through the environment (never on the command line), and makes the file readable by its owner only. Restore creates the target schema if it does not exist; to restore over the live schema, name it as the target on purpose. Dumps (`*.sql.gz`) are gitignored. The tested procedure and its result are in `docs/logs/TEST_LOG.md`.
