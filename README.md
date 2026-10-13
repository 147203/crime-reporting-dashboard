# Crime Reporting, Analysis and Visualization Platform (Nairobi County)

Final-year project, BBIT, Strathmore University. A responsive web application where authorised users record crime incidents in one central database; the system validates and cleans the data, analyses it descriptively, and presents trends, time patterns, area rankings and non-spatial hotspot flags as dashboards, charts and exportable reports.

**Author:** Mathai, Margaret Elsie Wairimu (adm. 147203). **Supervisor:** Mr Nicodemus Maingi.

**Status:** development is complete (phases 0 and 2 to 6b merged; 310 automated tests and 28 browser tests passing). Proposal correction, fieldwork, usability sessions, Chapters 4 to 6 and the demonstration are pending; see [the progress report](docs/reports/PROGRESS_REPORT.md).

**Scope notes:** the panel ruled out GIS, so there are no maps, GPS, coordinates or geocoding. Location is sub-county and ward from reference lists plus a free-text street or landmark; a "hotspot" is a statistical flag on a ward, shown in tables and charts. There is no machine learning or prediction: the analytics are descriptive. All data is synthetic and labelled as sample data.

---

## Contents

1. [What it does](#1-what-it-does)
2. [Technology](#2-technology)
3. [Prerequisites](#3-prerequisites)
4. [First-time setup](#4-first-time-setup)
5. [Configuration (.env)](#5-configuration-env)
6. [Running the project day to day](#6-running-the-project-day-to-day)
7. [Using the application](#7-using-the-application)
8. [Project commands](#8-project-commands)
9. [Testing](#9-testing)
10. [Project structure](#10-project-structure)
11. [Troubleshooting](#11-troubleshooting)
12. [Documentation map](#12-documentation-map)
13. [Security and data notes](#13-security-and-data-notes)

---

## 1. What it does

| Role              | Can do                                                                                                                                                                                                        |
| ----------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Administrator     | Manage users (create, view activity, edit, generated or typed temporary passwords, unlock, deactivate, delete accounts never used) and reference data (categories, sub-counties, wards, stations), import CSV, review duplicates, view the audit log and data-quality summary, see all dashboards, export reports, delete incidents |
| Reporting Officer | Report incidents for their own station, edit their own records while still "Reported", change status of their station's incidents, see their station's records and dashboard, export their station's data     |
| Analyst           | County-wide dashboards and records (read-only), review duplicates, data-quality summary, export reports                                                                                                       |

| Module                     | Features                                                                                                                                                                                                                            |
| -------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Sign-in and access control | Login with lockout after 5 wrong passwords, forced password change at first login, no self-registration, three roles, officers limited to their own station, audit log of every important action; a show or hide (eye) button on every password field; accounts with a history are deactivated, never deleted (only never-used accounts can be deleted), and the last active administrator cannot be removed |
| Incidents                  | Structured form with validation, list with search, filters, sorting and pagination, detail page, status workflow (Reported, Under investigation, Closed), soft delete                                                               |
| Data quality               | Validation rules V1 to V8, a guard that refuses phone and national ID numbers in free text, duplicate detection with a review queue, bulk CSV import with a dry run and per-row error report, data-quality summary                  |
| Analytics                  | A1 to A10: totals, category mix, monthly trend with moving average, period comparison, day-of-week and time-of-day patterns, ward and sub-county ranking, statistical hotspot flag, sub-county by month grid, status and timeliness |
| Backup                     | `db:backup` writes a compressed copy of the database; `db:restore` loads it into a named schema (local use) |
| Dashboard                  | KPI cards, charts with a table view for every chart, period presets and filters, light and dark themes, usable from 360 px phone width; click a bar, slice or table row to open the matching incidents |
| Reports                    | CSV and Excel (.xlsx) export of the matching records and a PDF summary, both limited to what the user may see and recorded in the audit log                                                                                                           |

The metric definitions (formula, filters, denominator) are in [docs/02](docs/02_REQUIREMENTS_AND_SCOPE.md).

---

## 2. Technology

| Layer               | Technology                                                                                                  |
| ------------------- | ----------------------------------------------------------------------------------------------------------- |
| Presentation        | Laravel Blade templates, Bootstrap 5, Bootstrap Icons, Chart.js, plain JavaScript, built with Vite and Sass |
| Application         | PHP 8.4, Laravel 12, Laravel Fortify (sign-in), barryvdh/laravel-dompdf (PDF)                               |
| Data                | MariaDB 10.4 from XAMPP (MySQL-compatible), port 3306                                                       |
| Quality and testing | PHPUnit, Playwright, Laravel Pint (code style), Larastan level 8 (static analysis)                          |

The database must be **MariaDB**: the timeliness measure (A10) uses `PERCENTILE_DISC`, which MySQL does not provide.

---

## 3. Prerequisites

| Tool | Version | Why it is needed |
| --- | --- | --- |
| PHP | **8.4** (8.4.x) with the extensions curl, fileinfo, mbstring, openssl, pdo_mysql, zip | Runs the application (Laravel 12) |
| Composer | 2.x | Installs the PHP libraries |
| MariaDB | 10.4 or newer, from **XAMPP** (only its database is used, not its Apache or PHP) | The database. It must be MariaDB: measure A10 uses `PERCENTILE_DISC`, which MySQL does not have |
| Node.js and npm | Node **20.19+ or 22.12+** (the current LTS is fine) | Builds the stylesheets and scripts (Vite) |
| Git | Any recent | Gets the code |
| Disk and memory | About 1.5 GB free (code, libraries, browser for tests); 4 GB RAM or more | |

Pick your system below, install what is missing, then continue with [section 4](#4-first-time-setup), which is the same everywhere. Commands are shown for **PowerShell** on Windows and for a **bash/zsh terminal** on Linux and macOS.

### 3.1 Windows 10 or 11

> XAMPP for Windows ships its own PHP, but it is **8.2**, which is too old for this project. Install XAMPP only for its MariaDB, and install PHP 8.4 separately as below.

1. **Microsoft Visual C++ Redistributable (x64)**, needed by PHP for Windows: https://aka.ms/vs/17/release/vc_redist.x64.exe. Install it, accepting the defaults.
2. **PHP 8.4**: from https://windows.php.net/download/ download the **PHP 8.4, VS17 x64 Non Thread Safe** zip and extract it to `C:\php` (so `C:\php\php.exe` exists). Then, in PowerShell:

   ```powershell
   cd C:\php
   Copy-Item php.ini-development php.ini
   # Turn on the extension folder and the six extensions the project needs
   (Get-Content php.ini) `
     -replace '^;\s*extension_dir = "ext"', 'extension_dir = "ext"' `
     -replace '^;extension=(curl|fileinfo|mbstring|openssl|pdo_mysql|zip)$', 'extension=$1' |
     Set-Content php.ini
   # Add C:\php to your user PATH
   [Environment]::SetEnvironmentVariable('Path', [Environment]::GetEnvironmentVariable('Path', 'User') + ';C:\php', 'User')
   ```

   **Close PowerShell and open a new window**, then check:

   ```powershell
   php -v                     # must say PHP 8.4.x
   where.exe php              # C:\php\php.exe must be listed first (not C:\xampp\php\php.exe)
   php -m                     # the list must include curl, fileinfo, mbstring, openssl, pdo_mysql, zip
   ```

3. **Composer**: run `Composer-Setup.exe` from https://getcomposer.org/download/. When it asks for the PHP to use, choose `C:\php\php.exe`. Open a new PowerShell window and check `composer --version`.
4. **XAMPP**: download XAMPP for Windows from https://www.apachefriends.org and install it to `C:\xampp` (the default; avoid `C:\Program Files`). Only the **MySQL** component is needed (it is MariaDB). Open the **XAMPP Control Panel** (`C:\xampp\xampp-control.exe`) and press **Start** next to **MySQL**; it turns green. You do not create any database by hand; `php artisan app:preflight` later checks that the server answering is MariaDB.

   If MySQL will not start, or preflight reports another server, another MySQL server (often the `MySQL80` Windows service) holds port 3306; see [Troubleshooting](#11-troubleshooting).
5. **Node.js**: install the LTS version from https://nodejs.org (Windows installer, defaults). Check `node -v` and `npm -v` in a new window. If PowerShell says *running scripts is disabled on this system*, run once: `Set-ExecutionPolicy -Scope CurrentUser RemoteSigned`.
6. **Git**: install from https://git-scm.com/download/win (defaults). Check `git --version`.

Keep the project in a plain folder such as `C:\projects`. Avoid folders synced by OneDrive (often Desktop and Documents): syncing locks files while Composer and npm write thousands of them.

### 3.2 Ubuntu or Debian Linux

```bash
# PHP 8.4 (Ubuntu 24.04: add the ondrej/php PPA first if php8.4 is not found: sudo add-apt-repository ppa:ondrej/php)
sudo apt install -y php8.4-cli php8.4-mbstring php8.4-xml php8.4-curl php8.4-zip php8.4-mysql php8.4-bcmath php8.4-intl php8.4-gd unzip git
# Composer 2: https://getcomposer.org/download/   Node.js LTS: https://nodejs.org (or your distribution's nodejs 20.19+/22.12+)
```

Install XAMPP for Linux from https://www.apachefriends.org (it installs to `/opt/lampp`), then:

```bash
sudo /opt/lampp/lampp startmysql
```

If Ubuntu's own MySQL server is installed, it starts at boot and takes port 3306 first (error 1698). Turn it off for good: `sudo systemctl stop mysql && sudo systemctl disable mysql`, then start XAMPP's MariaDB again.

### 3.3 macOS

```bash
brew install php@8.4 composer node git      # Homebrew from https://brew.sh
brew link --overwrite --force php@8.4
php -v                                      # must say PHP 8.4.x
```

Install XAMPP for macOS (the native installer, not the "-VM" one) from https://www.apachefriends.org, then:

```bash
sudo /Applications/XAMPP/xamppfiles/xampp startmysql
```

If Homebrew's MySQL is running it holds port 3306: `brew services stop mysql`.

> The Windows and macOS steps follow the official installers; the project was developed and tested on Ubuntu 24.04. Section 4.8 is the check that proves a new machine is set up correctly.

---

## 4. First-time setup

Every command below runs from the project folder unless it says otherwise. Where Windows differs, the PowerShell line is shown after the bash line.

### 4.1 Start the database

Make sure MariaDB is running: XAMPP Control Panel on Windows, `startmysql` on Linux and macOS (section 3). **No database is created by hand.** Laravel creates each one the first time it is needed:

| Database | Created by | Used by | Note |
| --- | --- | --- | --- |
| `crime_dashboard` | `php artisan migrate`, on its first run | The application | Your working data |
| `crime_dashboard_test` | `php artisan test`, on its first run | The test suite | Wiped on every test run; the name must end in `_test` |
| `crime_dashboard_e2e` | `npx playwright test`, on its first run | The browser tests | Wiped and rebuilt on every run; the name must end in `_e2e` |

### 4.2 Get the code

The repository is private: your GitHub account needs access to it. Over HTTPS, Git opens a browser window to sign in the first time.

```bash
cd ~                                 # Linux, macOS: any folder you like
git clone https://github.com/147203/crime-reporting-dashboard.git
cd crime-reporting-dashboard
```

```powershell
mkdir C:\projects; cd C:\projects    # Windows
git clone https://github.com/147203/crime-reporting-dashboard.git
cd crime-reporting-dashboard
```

Copying the folder from another machine (for example on a USB drive) also works: leave out `vendor`, `node_modules`, `.env` and `storage/app/private`, and continue with 4.3.

### 4.3 Install the libraries and build the front end

```bash
composer install
npm install
npm run build
```

### 4.4 Configure

```bash
cp .env.example .env                 # Linux, macOS
```

```powershell
Copy-Item .env.example .env          # Windows
notepad .env                         # opens it for editing
```

In `.env`, set the first administrator (see [section 5](#5-configuration-env)); never commit `.env`:

```
ADMIN_NAME="Your Name"
ADMIN_EMAIL=you@example.com
ADMIN_INITIAL_PASSWORD=<at least 14 characters, not starting with "changeme">
```

Everything else already matches a standard XAMPP install (`127.0.0.1`, port `3306`, user `root`, blank password, `APP_ENV=local`). Then:

```bash
php artisan key:generate
php artisan app:preflight            # must end with "Preflight passed."
```

`app:preflight` also connects to the database and checks the front end is built. If it fails, it says what is wrong and how to fix it on your system.

### 4.5 Create the tables, reference data and first administrator

```bash
php artisan migrate                  # never add --force; answer "yes" when it offers to create the database
php artisan db:seed                  # 17 sub-counties, 85 wards, sample stations, categories, and the administrator from .env
```

On the first run `migrate` says *The database 'crime_dashboard' does not exist ... Would you like to create it?*; answer **yes** and it creates the database with the project's character set, then the tables.

Keep `ADMIN_INITIAL_PASSWORD` in `.env`, because `app:preflight` checks it. It stops being a working password once the administrator chooses their own at first login, which the system forces.

### 4.6 Load demo data (optional, recommended)

```bash
php artisan sample:generate --size=20000 --load
```

This creates about 20,600 synthetic incidents over the last 24 complete months, labelled as sample data, loaded through the same import and validation pipeline as a real CSV upload (about 45 seconds). Run it once; running it again adds another set. To remove all sample records: `php artisan sample:clear`. Sizes up to 20,000 run within PHP's default 128 MB memory limit; for `--size=100000` set `memory_limit = 512M` in `php.ini` (Windows: `C:\php\php.ini`).

### 4.7 Start the application

```bash
php artisan serve                    # then open http://127.0.0.1:8000
```

Leave this window open while you use the application; `Ctrl+C` stops it.

### 4.8 Check that the new machine works

| Check | Expected |
| --- | --- |
| `php artisan app:preflight` | `Preflight passed.` |
| Open http://127.0.0.1:8000 and sign in with `ADMIN_EMAIL` and `ADMIN_INITIAL_PASSWORD` | Asked to choose a new password, then the dashboard |
| Dashboard after loading demo data | KPI cards and charts show figures; the banner at the top says "Sample data" |
| `php artisan test` (optional, about 30 seconds) | All tests pass |
| `npx playwright install chromium`, then `npx playwright test` (optional, about 3 minutes) | All browser tests pass |

---

## 5. Configuration (.env)

`.env.example` lists every setting. The ones you are most likely to touch:

| Variable                                                         | Default in`.env.example`                | Meaning                                                                                                              |
| ---------------------------------------------------------------- | ----------------------------------------- | -------------------------------------------------------------------------------------------------------------------- |
| `APP_ENV`                                                      | `local`                                 | `local` for this project; `production` switches on the strict checks                                             |
| `APP_DEBUG`                                                    | `false`                                 | Keep`false` except when diagnosing an error                                                                        |
| `APP_URL`                                                      | `http://127.0.0.1:8000`                 | Must be`127.0.0.1` or `localhost` for the local database shortcut                                                |
| `APP_KEY`                                                      | empty                                     | Set by`php artisan key:generate`                                                                                   |
| `DB_ALLOW_LOCAL_SHORTCUT`                                      | `true`                                  | Allows XAMPP's`root` with a blank password, only with `APP_ENV=local` and a loopback `APP_URL` (decision D-25) |
| `DB_HOST`, `DB_PORT`                                         | `127.0.0.1`, `3306`                   | XAMPP MariaDB                                                                                                        |
| `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`                | `crime_dashboard`, `root`, blank      | Application schema                                                                                                   |
| `DB_TEST_DATABASE`, `DB_TEST_USERNAME`, `DB_TEST_PASSWORD` | `crime_dashboard_test`, `root`, blank | Test schema (name must end in`_test`)                                                                              |
| `ADMIN_NAME`, `ADMIN_EMAIL`, `ADMIN_INITIAL_PASSWORD`      | placeholders                              | First administrator; no working default exists                                                                       |
| `LOGIN_MAX_ATTEMPTS`, `LOGIN_LOCKOUT_MINUTES`                | `5`, `15`                             | Account lockout                                                                                                      |
| `SESSION_LIFETIME`, `SESSION_EXPIRE_ON_CLOSE`                | `120`, `true`                         | Idle minutes before sign-out; sign-out when the browser closes                                                       |
| `SESSION_SECURE_COOKIE`                                        | `false`                                 | Set`true` whenever the site is served over HTTPS                                                                   |
| `HOTSPOT_Z_THRESHOLD`, `HOTSPOT_MIN_INCIDENTS`               | `1.5`, `10`                           | Hotspot rule A8 (decision D-11)                                                                                      |
| `DUPLICATE_WINDOW_MINUTES`, `DUPLICATE_SIMILARITY`           | `120`, `0.8`                          | Duplicate rule V8                                                                                                    |
| `SAMPLE_DATA_SEED`                                             | `20260924`                              | Seed for the sample-data generator (same seed, same data)                                                            |

`php artisan app:preflight` checks the key, database settings, test-schema separation and admin password, and names any problem without printing the value. The web application itself refuses every request (HTTP 503) while the key or database settings are invalid.

---

## 6. Running the project day to day

After a restart, start the database, then the application:

| System | Start the database | Start the application (in the project folder) |
| --- | --- | --- |
| Windows | XAMPP Control Panel, **Start** next to MySQL (tick **Svc** once, as administrator, to start it with Windows) | `php artisan serve` |
| Linux | `sudo /opt/lampp/lampp startmysql` | `php artisan serve` |
| macOS | `sudo /Applications/XAMPP/xamppfiles/xampp startmysql` | `php artisan serve` |

Then open http://127.0.0.1:8000. While changing styles or JavaScript, run `npm run dev` in a second terminal for live reloading (or `composer run dev` to start both); otherwise `npm run build` once after pulling changes.

After pulling new code:

```bash
composer install
npm install
npm run build
php artisan migrate
```

Commands that set a variable for one run are written the Linux way in this README (`NAME=value command`). In PowerShell use `$env:NAME='value'; command; Remove-Item Env:NAME`.

---

## 7. Using the application

1. Open http://127.0.0.1:8000 and sign in with the administrator from `.env`.
2. Choose your own password when asked (at least 12 characters, letters and numbers).
3. Go to **Administration, Users** and create a Reporting Officer (assign a station) and an Analyst to try the other roles. Leave **Generate a secure temporary password** selected: the password is shown once on the next page with a Copy button, and the user must choose their own at first login. Accounts can also be created from the terminal with `php artisan user:create`.
4. Try the main tasks: report an incident, find it with the filters, open the dashboard and its presets, click a bar to drill down to the matching incidents, review a suspected duplicate, import a CSV, and download CSV, Excel and PDF reports.
5. Your own account is not edited from the Users page: change your password from the account menu, **Change password** (it asks for the current one). A locked account is opened again with **Unlock** on the Users page.

CSV import format (header exactly as shown, dates as `YYYY-MM-DD HH:MM`, names as in Reference data, UTF-8, up to 5 MB and 10,000 rows):

```
category,occurred_at,reported_at,sub_county,ward,station,location_description,description,status,closed_at
Theft,2026-08-01 10:00,2026-08-01 11:00,Kibra,Makina,Kibra Sample Station,Near the market 5,Phone taken.,reported,
```

---

## 8. Project commands

| Command                                                                                            | What it does                                                                                                                     |
| -------------------------------------------------------------------------------------------------- | -------------------------------------------------------------------------------------------------------------------------------- |
| `php artisan app:preflight`                                                                      | Checks secrets and database settings, connects to the database (it must be MariaDB) and checks the front end is built; exits with an error naming anything missing or weak and how to fix it |
| `php artisan db:backup`                                                                          | Writes a gzipped dump of the application database to `storage/app/private/backups/` (readable by the owner only) |
| `php artisan db:restore <file> --target=<schema>`                                                | Restores a dump into the named schema after confirmation (local or testing only); used to test the backup |
| `php artisan user:create`                                                                        | Creates a user interactively (password typed hidden, must be changed at first login)                                             |
| `php artisan sample:generate --size=N [--seed=S] [--end=YYYY-MM] [--load]`                       | Generates synthetic incidents plus ground-truth files in`storage/app/sample/`; `--load` imports them (local or testing only) |
| `php artisan sample:evaluate <dir> <batch-id>`                                                   | Scores a loaded sample against its ground truth (rejection recall, duplicate precision and recall)                               |
| `php artisan sample:clear`                                                                       | Removes all synthetic incidents (asks first; local or testing only)                                                              |
| `php artisan imports:prune`                                                                      | Closes CSV dry runs older than 24 hours and deletes their files (also scheduled hourly with`php artisan schedule:work`)        |
| `php artisan e2e:prepare`                                                                        | Rebuilds the`*_e2e` schema for browser tests (Playwright runs it automatically)                                                |
| `php scripts/oracle.php --input <dir> --filters tests/Fixtures/oracle/filters.json --out <file>` | Independent calculation of A1 to A10 used to prove accuracy (see`scripts/ORACLE.md`)                                           |
| `PERF_PASSWORD=... php scripts/perf.php --base=URL --email=EMAIL`                                | Measures page response times (mean, p50, p95)                                                                                    |
| `node scripts/progress-report.cjs`                                                               | Rebuilds the Word copy of the progress report from`docs/reports/PROGRESS_REPORT.md`                                            |

---

## 9. Testing

```bash
php artisan test                          # unit, feature, security and accuracy tests on crime_dashboard_test
npx playwright install chromium           # once
npx playwright test                       # browser tests on crime_dashboard_e2e, desktop 1366 px and phone 360 px
vendor/bin/pint --test                    # code style
vendor/bin/phpstan analyse --memory-limit=1G   # static analysis, level 8
```

| Level                | Examples                                                                                                                                                        |
| -------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Unit                 | PII guard patterns, preflight rules, status workflow, duplicate similarity                                                                                      |
| Feature and security | Every route tried as guest, administrator, officer and analyst; lockout; CSRF; station isolation; SQL-injection strings in filters; stored XSS escaped; headers |
| Accuracy             | A1 to A10 compared with the independent oracle on a seeded 1,000-record dataset; rejection and duplicate rates against planted ground truth                     |
| Browser (black-box)  | The eight usability tasks, plus an officer refused administration pages; screenshots in `docs/evidence/`                                                       |
| Accessibility        | Automated WCAG 2.1 AA scan (axe) of every main page in light and dark themes at 1366 px and 360 px; any serious or critical issue fails the test |

Results and dates are logged in [docs/logs/TEST_LOG.md](docs/logs/TEST_LOG.md) and [docs/logs/EVAL_LOG.md](docs/logs/EVAL_LOG.md).

---

## 10. Project structure

```
app/
  Actions/Auth/          sign-in with lockout
  Console/Commands/      project commands (section 8)
  Enums/                 Role, IncidentStatus, DuplicateStatus
  Http/Controllers/      thin controllers (Admin/ for administration)
  Http/Middleware/       preflight, security headers, role check, account checks
  Http/Requests/         validation of every form and filter
  Models/                Incident, User, Ward, SubCounty, Station, CrimeCategory, ImportBatch, AuditLog, ReportExport
  Policies/              IncidentPolicy (who may do what)
  Services/              incidents, PII guard, duplicates, import, analytics (A1 to A10), reports, audit, sample data
  Support/               preflight, filters, option lists
config/analytics.php     hotspot and duplicate parameters
config/sample_data.php   sample-data assumptions
database/migrations/     schema (reversible, named indexes)
database/seeders/        reference data (data/*.csv) and the first administrator
resources/views/         Blade pages and reusable components (components/)
resources/scss/          design tokens and styles
resources/js/modules/    theme, charts, dependent ward list, confirmations, list filter
scripts/                 oracle, performance measurement, progress report
tests/                   Unit, Feature (Auth, Admin, Incidents, Quality, Schema, Security), e2e (Playwright), Fixtures
docs/                    requirements, architecture, testing, decisions, logs, diagrams, reports, evidence
```

---

## 11. Troubleshooting

Run `php artisan app:preflight` first: it names most problems and the fix for your system.

**Database**

| Symptom | Cause | Fix |
| --- | --- | --- |
| `SQLSTATE[HY000] [1698] Access denied for user 'root'@'localhost'` (Linux) | Ubuntu's own MySQL server is running on port 3306 instead of XAMPP's MariaDB (it can re-enable itself after updates) | `sudo systemctl stop mysql && sudo systemctl disable mysql && sudo /opt/lampp/lampp startmysql` |
| `[1045] Access denied for user 'root'@'localhost' (using password: NO)` (Windows) | Another MySQL server, usually the `MySQL80` service from MySQL Installer, holds port 3306 | Administrator PowerShell: `Stop-Service MySQL80; Set-Service MySQL80 -StartupType Manual`, then Start MySQL in the XAMPP Control Panel |
| XAMPP Control Panel: *Port 3306 in use* / MySQL will not start (Windows) | Same as above, or a second XAMPP | Find the owner: `netstat -ano \| findstr :3306`, then `tasklist /FI "PID eq <number>"`; stop that program or service |
| `app:preflight` says the server is not XAMPP's MariaDB | A MySQL server answered on port 3306 | As the two rows above for your system |
| `SQLSTATE[HY000] [2002] Connection refused` / *No connection could be made* | The database is not running (or still starting) | Windows: Start MySQL in the XAMPP Control Panel. Linux: `sudo /opt/lampp/lampp startmysql`, wait a few seconds |
| `[1049] Unknown database 'crime_dashboard'` | The database has not been created yet | `php artisan migrate`, answer **yes** to create it |
| `app:preflight` names `DB_USERNAME ... not root` or `DB_PASSWORD is not set` | The local shortcut is off | In `.env`: `DB_ALLOW_LOCAL_SHORTCUT=true`, `APP_ENV=local`, `APP_URL=http://127.0.0.1:8000` |

**PHP, Composer and Node**

| Symptom | Cause | Fix |
| --- | --- | --- |
| `php -v` shows 8.2 (Windows) | XAMPP's PHP (`C:\xampp\php`) is found before `C:\php` | Remove `C:\xampp\php` from PATH or move `C:\php` above it; check with `where.exe php`; open a new window |
| `'php' is not recognized` | PHP folder not on PATH, or the window was open before PATH changed | Redo the PATH step in 3.1 and open a new window |
| Composer: *requires PHP extension ext-zip / ext-fileinfo / ext-openssl* | Extension not enabled in `php.ini` | Windows: in `C:\php\php.ini` remove the `;` before `extension=zip` (and the others in 3.1); Linux: `sudo apt install php8.4-zip` etc.; check `php -m` |
| `could not find driver` | `pdo_mysql` not enabled | As above, for `extension=pdo_mysql` |
| PHP for Windows: *VCRUNTIME140.dll was not found* | Visual C++ Redistributable missing | Install it (3.1, step 1) |
| PowerShell: *npm.ps1 cannot be loaded because running scripts is disabled* | PowerShell execution policy | `Set-ExecutionPolicy -Scope CurrentUser RemoteSigned`, or use Command Prompt |
| `npm install` or `composer install` fails with *EPERM* / *file locked* (Windows) | OneDrive or an antivirus scan is locking files | Move the project to `C:\projects`, run the command again |
| Vite: *requires Node.js version 20.19+ or 22.12+* | Node too old | Install the current LTS from nodejs.org |
| `Vite manifest not found` | Front-end assets not built | `npm run build` |

**Application**

| Symptom | Cause | Fix |
| --- | --- | --- |
| Every page shows "The application is not configured" (503) | `APP_KEY` or database settings invalid | Run `php artisan app:preflight` and fix what it names |
| `MissingAppKeyException` | No application key | `php artisan key:generate` |
| `Failed to listen on 127.0.0.1:8000` | Another `artisan serve` is running | Close the other window (Linux: `pkill -f "artisan serve"`), or use the port it offers |
| Login says the account is locked | 5 wrong passwords | Wait 15 minutes, or an administrator presses **Unlock** under Users |
| `db:backup` says mysqldump was not found | XAMPP is not in its default folder (`/opt/lampp`, `C:\xampp`, `/Applications/XAMPP`) | Set `DB_DUMP_BINARY` and `DB_CLIENT_BINARY` in `.env` to the full paths |
| `sample:generate --size=100000` stops with *Allowed memory size exhausted* | PHP memory limit (128 MB on Windows by default) | Set `memory_limit = 512M` in `php.ini` |
| Tests refuse to run ("must use the mysql_testing connection and a *_test database") | Test database misnamed | Keep `DB_TEST_DATABASE` ending in `_test` and different from `DB_DATABASE` (the suite creates it on first run) |
| The dashboard's last month looks low | The default period ends today, so the current month is incomplete (the chart caption says so) | Use a preset ending on a complete month, or read the table view |

---

## 12. Documentation map

| File                                                                        | Purpose                                                                                            |
| --------------------------------------------------------------------------- | -------------------------------------------------------------------------------------------------- |
| [docs/11_HOW_IT_WORKS.md](docs/11_HOW_IT_WORKS.md) | **Start here:** every setup command and what it does, where the data comes from, what happens on each screen |
| [docs/reports/PROGRESS_REPORT.md](docs/reports/PROGRESS_REPORT.md)           | Supervisor progress report, updated at the end of every phase (Word copy:`Progress_Report.docx`) |
| [docs/02_REQUIREMENTS_AND_SCOPE.md](docs/02_REQUIREMENTS_AND_SCOPE.md)       | Roles and access matrix, requirements, analytics definitions A1 to A10, validation rules           |
| [docs/03_ARCHITECTURE_AND_DATA.md](docs/03_ARCHITECTURE_AND_DATA.md)         | Layers, schema, security architecture                                                              |
| [docs/04_SAMPLE_DATA.md](docs/04_SAMPLE_DATA.md)                             | Synthetic data generator and the oracle                                                            |
| [docs/05_FIELDWORK_AND_INSTRUMENTS.md](docs/05_FIELDWORK_AND_INSTRUMENTS.md) | Interviews, questionnaire, usability study                                                         |
| [docs/06_TESTING_AND_EVALUATION.md](docs/06_TESTING_AND_EVALUATION.md)       | Test levels and requirement traceability                                                           |
| [docs/09_DECISIONS_RISKS.md](docs/09_DECISIONS_RISKS.md)                     | Decision log, risks, open questions                                                                |
| [docs/10_SETUP_AND_GIT.md](docs/10_SETUP_AND_GIT.md)                         | Machine setup details                                                                              |
| [docs/diagrams/](docs/diagrams/)                                             | Design diagram sources (Mermaid) for Chapter 4                                                     |
| [docs/usability/USABILITY_KIT.md](docs/usability/USABILITY_KIT.md)           | Draft usability session kit (tasks, SUS, observer sheet)                                           |
| [docs/logs/](docs/logs/)                                                     | Implementation, test, evaluation and fieldwork logs                                                |
| [scripts/ORACLE.md](scripts/ORACLE.md)                                       | How the independent accuracy oracle works                                                          |

---

## 13. Security and data notes

- The repository is private. `.env`, database dumps (`*.sql.gz`, kept in `storage/app/private/backups/`), fieldwork data and `docs/reference/` are never committed.
- No real personal data is used or stored. The schema has no fields for victims, suspects or witnesses, and free text containing phone or national ID numbers is refused.
- Every route requires sign-in plus a role or policy check; officers cannot see other stations' records, even by typing an address.
- The blank-password database account is a local-only convenience (decision D-25): it is accepted only on this machine with `APP_ENV=local` and a loopback address. With `APP_ENV=production`, the app requires a dedicated database user and password, secure cookies and debug mode off.
- XAMPP's MariaDB listens on every network interface by default. To keep it on this machine only, add `bind-address=127.0.0.1` under `[mysqld]` in its settings file (Linux `/opt/lampp/etc/my.cnf`, Windows `C:\xampp\mysql\bin\my.ini`, macOS `/Applications/XAMPP/xamppfiles/etc/my.cnf`) and restart MySQL.
