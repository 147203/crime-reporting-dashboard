# 11. How the system works, from setup to reports

A start-to-end walkthrough: every setup command and what it does, where every piece of data comes from, and what the system does with it on each screen. Installing the tools themselves (PHP, Composer, XAMPP, Node, Git) is covered step by step for Windows, Linux and macOS in the README, section 3.

---

## 1. The big picture

```
 Browser (laptop or phone)
   │  pages, forms, clicks
   ▼
 php artisan serve  ──►  Laravel application (PHP 8.4)
                           ├─ security checks on every request (section 6)
                           ├─ controllers: one per screen
                           ├─ services: validation, import, duplicates, analytics, exports
                           └─ talks to the database with parameterised SQL only
                                   │
                                   ▼
                         XAMPP MariaDB (127.0.0.1:3306)
                           ├─ crime_dashboard        the application's data
                           ├─ crime_dashboard_test   wiped and refilled by php artisan test
                           └─ crime_dashboard_e2e    wiped and refilled by npx playwright test
```

- **Nothing is stored in the browser** except the session cookie and the light or dark theme choice.
- **All incidents are synthetic** (made up). The database column `is_synthetic` defaults to true, and every page shows the "Sample data" banner.
- **There is no map anywhere.** Location is a sub-county, a ward and a free-text landmark. A "hotspot" is a ward whose count is statistically unusual, shown in a table.

---

## 2. Setup from start to end

Run every command from the project folder. Where Windows PowerShell differs from Linux or macOS, both are shown.

### Step 1. Start the database

| System  | Command or action                                                   |
| ------- | ------------------------------------------------------------------- |
| Windows | Open the XAMPP Control Panel and press**Start** next to MySQL |
| Linux   | `sudo /opt/lampp/lampp startmysql`                                |
| macOS   | `sudo /Applications/XAMPP/xamppfiles/xampp startmysql`            |

**What happens:** XAMPP starts the MariaDB server, which listens on port 3306 for connections from this computer. It is an empty server: no project database exists yet, and none is created by hand. `php artisan app:preflight` (step 6) checks that the server answering is MariaDB.

### Step 2. Get the code

```bash
git clone https://github.com/147203/crime-reporting-dashboard.git
cd crime-reporting-dashboard
```

**What happens:** Git downloads the project's source code: PHP code, page templates, styles, scripts, tests, reference lists and documentation. It does **not** download the libraries (step 3), your settings (step 4) or any data.

### Step 3. Install the libraries and build the front end

| Command              | What it does                                                                                                                                        | What appears on disk                       |
| -------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------- | ------------------------------------------ |
| `composer install` | Downloads the exact PHP library versions listed in`composer.lock`: Laravel, Fortify (sign-in), DomPDF (PDF), OpenSpout (Excel) and the test tools | `vendor/` (about 80 MB)                  |
| `npm install`      | Downloads the exact JavaScript and styling tool versions listed in`package-lock.json`: Bootstrap, Chart.js, Vite, Sass, Playwright                | `node_modules/`                          |
| `npm run build`    | Vite compiles the Sass stylesheets and the JavaScript into small, cache-friendly files the browser loads                                            | `public/build/` with a `manifest.json` |

The lock files pin every version, so every machine gets exactly the same libraries.

### Step 4. Create your settings file

```bash
cp .env.example .env                 # Linux, macOS
```

```powershell
Copy-Item .env.example .env          # Windows
```

**What happens:** `.env` is your private settings file. It is never committed to Git. `.env.example` is the committed template with safe placeholder values.

Open `.env` and set three values yourself:

| Setting                    | Meaning                                                                                                                                              |
| -------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------- |
| `ADMIN_NAME`             | Name of the first administrator                                                                                                                      |
| `ADMIN_EMAIL`            | The administrator's sign-in email                                                                                                                    |
| `ADMIN_INITIAL_PASSWORD` | A temporary password of at least 14 characters. Values starting with`changeme` are refused, so the template placeholder can never work by accident |

Everything else already matches a standard XAMPP install: host `127.0.0.1`, port `3306`, user `root` with a blank password. A blank password is accepted only when `APP_ENV=local`, `DB_ALLOW_LOCAL_SHORTCUT=true` and the app runs at a `127.0.0.1` or `localhost` address (decision D-25).

### Step 5. Create the application key

```bash
php artisan key:generate
```

**What happens:** a random 32-byte secret is written into `.env` as `APP_KEY`. Laravel uses it to encrypt and sign the session cookie and other data, so nobody can forge or read them. Every installation has its own key.

### Step 6. Check the installation

```bash
php artisan app:preflight
```

**What happens:** a read-only check of everything the app needs. It changes nothing.

| It checks                  | It fails when                                                                                                                                                                                                                             |
| -------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `APP_KEY`                | Missing or too short                                                                                                                                                                                                                      |
| Database settings          | Missing, a placeholder value, or the blank-password shortcut used outside local                                                                                                                                                           |
| Test database              | Same as the app database, or its name does not end in`_test`                                                                                                                                                                            |
| `ADMIN_INITIAL_PASSWORD` | Missing, shorter than 14 characters, or a`changeme` placeholder                                                                                                                                                                         |
| The database server        | Unreachable, or a MySQL server answering instead of MariaDB. It prints the exact fix for Windows, Linux or macOS. If only the project database is missing (a new machine), it says`php artisan migrate` will create it and still passes |
| Front end                  | `npm run build` has not been run                                                                                                                                                                                                        |

It ends with `Preflight passed.` Problems are named without ever printing a secret value. The web application runs the same secret checks on every request and shows a 503 page instead of running half-configured.

### Step 7. Create the database and its tables

```bash
php artisan migrate                  # never with --force
```

**What happens:**

1. On the first run, Laravel finds that the database `crime_dashboard` (the name in `DB_DATABASE`) does not exist and asks *Would you like to create it?* Answer **yes**: it creates the empty database.
2. Laravel then runs the files in `database/migrations/` in date order. Each one creates or changes tables, and the `migrations` table records which have run, so a migration never runs twice.

| Migration                         | Creates                                                                                                                 |
| --------------------------------- | ----------------------------------------------------------------------------------------------------------------------- |
| `0001_01_01_000000`             | `users`, `sessions` (who is signed in), `password_reset_tokens`                                                   |
| `0001_01_01_000001`, `000002` | `cache`, `jobs` (framework housekeeping)                                                                            |
| `2026_09_25_000100`             | `sub_counties`, `wards`, `stations`, `crime_categories`                                                         |
| `2026_09_25_000200`             | Adds role, station, active flag, lockout counters and "must change password" to`users`                                |
| `2026_09_25_000300`             | `incidents`, with indexes for fast filtering                                                                          |
| `2026_09_25_000400`             | `import_batches`, `audit_logs`, `report_exports`, plus two database triggers that make `audit_logs` append-only |
| `2026_09_25_000500`             | A covering index that made the dashboard about five times faster at 100,000 records                                     |

The project uses three databases on the same server, each created automatically the first time it is needed, and every table is created with utf8mb4 (any character, including Swahili and emoji):

| Database                 | Created by                                | Used by           | Note                                                         |
| ------------------------ | ----------------------------------------- | ----------------- | ------------------------------------------------------------ |
| `crime_dashboard`      | `php artisan migrate`, on its first run | The application   | Your working data                                            |
| `crime_dashboard_test` | `php artisan test`, on its first run    | The test suite    | Wiped on every test run; the name must end in`_test`       |
| `crime_dashboard_e2e`  | `npx playwright test`, on its first run | The browser tests | Wiped and rebuilt on every run; the name must end in`_e2e` |

The database itself enforces integrity, so even a bug in the PHP code cannot store bad data:

- a ward must belong to the incident's sub-county (composite foreign key);
- status values are restricted;
- a user who is referenced anywhere cannot be deleted (`restrict`); only never-used accounts can be (D-52);
- the audit log can never be edited or deleted (the triggers).

### Step 8. Load the reference data and the first administrator

```bash
php artisan db:seed
```

**What happens:** two seeders run in order.

| Seeder                  | Reads                                                                     | Writes                                                                                                                                                                  |
| ----------------------- | ------------------------------------------------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `ReferenceDataSeeder` | The CSV files in`database/seeders/data/` (section 3.1)                  | 17 sub-counties, 85 wards, 17 sample stations, 5 crime categories (Theft, Robbery, Burglary, Assault, Other)                                                            |
| `AdminSeeder`         | `ADMIN_NAME`, `ADMIN_EMAIL`, `ADMIN_INITIAL_PASSWORD` from `.env` | One Administrator account. The password is stored only as a bcrypt hash and marked "must change at first login". It refuses to run if the variables are missing or weak |

No seeder contains a working password: the only way in is the password you put in `.env`.

### Step 9. Load demo incidents (optional, recommended)

```bash
php artisan sample:generate --size=20000 --load
```

**What happens:** two stages (explained fully in section 3.2):

1. **Generate.** The generator writes a CSV of synthetic incidents plus "answer key" files to `storage/app/sample/20000-<seed>-<month>/`.
2. **Load.** `--load` feeds that CSV through the **same import pipeline as a real upload**, with the same validation rules and duplicate detection. Bad rows are rejected, suspected duplicates are flagged, and the rest are stored.

Output: about 20,600 rows accepted, 1,200 rejected, about 356 flagged as suspected duplicates, in about 45 seconds. Run it once, because running it again adds a second set. `php artisan sample:clear` removes all synthetic incidents.

### Step 10. Start the web server

```bash
php artisan serve
```

**What happens:** PHP's built-in web server starts on http://127.0.0.1:8000. It runs only while this window is open (`Ctrl+C` stops it), and it is reachable only from this computer.

### Step 11. First sign-in

1. Open http://127.0.0.1:8000. You are sent to the login page, because every page needs sign-in.
2. Sign in with `ADMIN_EMAIL` and `ADMIN_INITIAL_PASSWORD`.
3. You are forced to the **Change password** page. Choose your own password (at least 12 characters, with letters and numbers). From now on the `.env` password no longer signs in.
4. The dashboard opens.

### After a restart

Only steps 1 and 10 are needed: start MariaDB, then `php artisan serve`.

---

## 3. Where the data comes from

The database holds five kinds of data, from five sources:

| Data                | Source                                                                          | Real or synthetic                                                                           |
| ------------------- | ------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------- |
| Sub-counties, wards | `database/seeders/data/sub_counties.csv` and `wards.csv`                    | Real place names (IEBC 2012 boundaries;**VERIFY** against the official gazette)       |
| Stations            | `database/seeders/data/stations.csv`                                          | Synthetic: one "<Sub-county></sub> Sample Station" per sub-county, not real police stations |
| Crime categories    | `database/seeders/data/crime_categories.csv`                                  | Category names from the design (docs/03)                                                    |
| Users               | The first administrator from`.env`; everyone else created by an administrator | Your accounts                                                                               |
| Incidents           | The sample-data generator, a CSV upload, or the incident form                   | **Always synthetic** in this project                                                  |

No real police records or personal data are used anywhere. The schema has no fields for victims, suspects or witnesses, and free text containing phone or ID numbers is refused (rule V6).

### 3.1 Reference data

The seeder reads four small CSV files that are committed to the repository. They are reference lists, not incidents. Because they are files, every installation gets the same lists, and the generator can read them without a database.

An administrator can later add or deactivate categories, wards and stations under **Administration, Reference data**. Nothing is deleted, so old incidents keep their links.

### 3.2 Synthetic incidents: the generator

`app/Services/SampleData/SampleDataGenerator.php`, driven by `php artisan sample:generate`.

**It is deterministic:** it uses a seeded random number generator (`SAMPLE_DATA_SEED`, default `20260924`). The same seed, size and end month always produce byte-identical files, which a test proves with a SHA-256 hash. Any result in the logs can therefore be reproduced exactly.

**Every parameter is in `config/sample_data.php`.** These values are assumptions for a demonstration, not claims about real crime in Nairobi:

| Parameter         | Value                                                                  | Effect                                                                                   |
| ----------------- | ---------------------------------------------------------------------- | ---------------------------------------------------------------------------------------- |
| Period            | 24 complete months, ending last month                                  | Dates run up to the end of the previous month                                            |
| Category mix      | Theft 44 %, Assault 20 %, Robbery 14 %, Burglary 14 %, Other 8 %       | Share of each category (**VERIFY** against the KNBS Economic Survey before citing) |
| Weekday weights   | Fridays and Saturdays busier; assaults peak at weekends                | Gives the day-of-week chart (A5) a pattern                                               |
| Time-band weights | Burglary mostly 00:00 to 05:59; theft mostly daytime; assault evenings | Gives the time-of-day chart (A6) a pattern                                               |
| Seasonality       | ±10 % sine wave over the year                                         | A gentle monthly rise and fall in the trend (A3)                                         |
| Ward weights      | Random 0.5 to 2.0 per ward                                             | Some wards are naturally busier                                                          |

**What one generated incident looks like** (the CSV import format):

| Column               | Example                                      | Where it comes from                                                                                                                                                           |
| -------------------- | -------------------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| category             | Theft                                        | Drawn from the category mix                                                                                                                                                   |
| occurred_at          | 2026-08-14 10:35                             | Month by seasonality, weekday and hour by the weights                                                                                                                         |
| reported_at          | 2026-08-14 11:20                             | 0 to 72 hours after occurred_at, mostly within the first few hours                                                                                                            |
| sub_county, ward     | Kibra, Makina                                | Ward drawn by ward weight; its sub-county from the reference list                                                                                                             |
| station              | Kibra Sample Station                         | The sub-county's sample station                                                                                                                                               |
| location_description | Near the market 45                           | One of 12 landmark phrases plus a number from 1 to 400                                                                                                                        |
| description          | Mobile phone taken from a pocket in a crowd. | One of a few fixed sentences per category                                                                                                                                     |
| status, closed_at    | closed, 2026-08-20 09:00                     | Incidents older than 60 days: 55 % closed, 25 % under investigation, 20 % reported; newer ones: 10 %, 30 %, 60 %. Closed ones get closed_at 1 hour to 60 days after reporting |

**Known problems are planted on purpose**, so the system's checks can be scored against a known answer:

| Planted                     | How many (20,000 run) | What is done to the row                                                                                                                                      | Expected system response                |
| --------------------------- | --------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------ | --------------------------------------- |
| V1 bad category             | 200                   | Category blank or "Unknown category"                                                                                                                         | Rejected                                |
| V2 bad date                 | 200                   | Date in the future or 12 years ago                                                                                                                           | Rejected                                |
| V3 reported before occurred | 200                   | reported_at set 3 to 22 hours before occurred_at                                                                                                             | Rejected                                |
| V4 wrong ward               | 200                   | A ward from a different sub-county                                                                                                                           | Rejected                                |
| V5 text too long            | 200                   | Location over 150 or description over 1,000 characters                                                                                                       | Rejected                                |
| V6 personal data            | 200                   | A phone number like "07xx xxx xxx" or "House of ID 2xxxxxxx"                                                                                                 | Rejected                                |
| True duplicate              | 400                   | A copy of a real row 5 to 96 minutes later with the location retyped (upper case, a dropped or swapped letter, " gate" added, "the" removed, or a full stop) | Flagged as suspected duplicate          |
| Near miss                   | 200                   | Same place but more than 2 hours later, or same time with a different landmark                                                                               | **Not** flagged                   |
| Ward hotspots               | 3 wards               | Incident rate multiplied by 3                                                                                                                                | Flagged by the hotspot rule A8          |
| Category hotspots           | 2 wards               | Theft multiplied by 3                                                                                                                                        | Visible in the category and ward tables |
| Trend                       | Burglary              | Grows by 60 % across the period                                                                                                                              | Visible in the monthly trend            |

**Output files** in `storage/app/sample/<size>-<seed>-<end>/`:

| File                                          | Contents                                                                           |
| --------------------------------------------- | ---------------------------------------------------------------------------------- |
| `incidents.csv`                             | All rows (valid, bad, duplicates, near misses), shuffled                           |
| `truth/bad_records.csv`                     | Row number and rule of every planted bad row                                       |
| `truth/duplicates.csv`                      | Each planted copy, its original, and whether it is a true duplicate or a near miss |
| `truth/hotspots.json`, `truth/trend.json` | The planted hotspots and trend                                                     |
| `manifest.json`                             | Parameters, counts and file hashes                                                 |

The `truth/` files are never loaded into the application. They are the answer key that `php artisan sample:evaluate` uses to score the system (section 7).

### 3.3 Incidents from a CSV upload

**Imports** screen (Administrator). The file must have exactly the header shown in the README, section 7, be at most 5 MB and 10,000 rows, and be UTF-8.

1. **Upload, then dry run.** Every row is checked against rules V1 to V7, and nothing is stored yet. You see how many rows would be accepted, and a list of problems by row, column and rule. The cell values themselves are never stored in the report, so personal data in a bad file is not kept.
2. **Commit or discard.**
   - **Commit:** the accepted rows are stored in one transaction (all or nothing), each one is checked for duplicates (V8), and reference numbers are assigned.
   - **Discard:** nothing is stored.
   - Either way, the uploaded file is deleted. Dry runs left unfinished for 24 hours are closed and deleted by `php artisan imports:prune`.

### 3.4 Incidents from the form

**Report incident** screen (Administrator, Reporting Officer).

- The officer picks a category, sub-county, ward (filtered to that sub-county), station, dates, landmark and description.
- The same rules V1 to V7 apply.
- On save, the incident gets a reference number `NRB-<year>-<id>`, for example `NRB-2026-000123`, and is checked for duplicates (V8).
- A Reporting Officer can only choose their own station.

---

## 4. What happens to every incident

Whichever way an incident arrives (generator, upload or form), it goes through the same steps:

```
 incoming row
   ├─ V1  category exists and is active ............ else REJECT
   ├─ V2  occurred_at valid, not future, ≤ 10 years . else REJECT
   ├─ V3  reported_at not before occurred_at ........ else REJECT
   ├─ V4  ward belongs to the sub-county ............ else REJECT
   ├─ V5  length limits, HTML removed ............... else REJECT
   ├─ V6  no phone or national ID numbers ........... else REJECT
   ├─ V7  reference number unique ................... else REJECT
   ├─ store in `incidents` (is_synthetic = true)
   ├─ V8  duplicate check: same category and ward, within 2 hours, location similarity ≥ 0.8
   │      and the same numbers in the location  ..... if matched: "Suspected duplicate", linked to the earlier record
   └─ audit log entry: who did it and when (never the text content)
```

- **Rejected rows** are never stored. For an upload, they appear in the dry-run report.
- **Suspected duplicates** are stored but not counted until someone reviews them. On the **Duplicate review** screen they are either:

  - **confirmed:** excluded from all counts for good; or
  - **dismissed:** counted as a normal incident.

  Nothing is merged or deleted automatically.
- **Counted ("countable") incidents** are all incidents that are not deleted and not confirmed duplicates. Every chart and figure counts only these.
- **Status workflow:**

  - Reported, then Under investigation, then Closed.
  - Closing records `closed_at`, which feeds the timeliness measures.
  - Administrators can change any incident's status, and officers can change those at their own station.

The rule definitions, with exact limits, are in docs/02, section 5.

---

## 5. What happens on each screen

| Screen                       | Who                    | What it does, and where its figures come from                                                                                                                                                                                                              |
| ---------------------------- | ---------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| **Login**              | Everyone               | Checks the email and password hash. 5 wrong attempts lock the account for 15 minutes, and an administrator can unlock it early. A new or reset password must be changed at first sign-in. The session ends when the browser closes                         |
| **Dashboard**          | All roles              | Computes the ten measures A1 to A10**live in the database on every visit**, from the counted incidents matching the filters. The default period is the last 12 months up to today (the current month is incomplete). Officers see only their station |
| **Incidents**          | All roles              | Searchable, filterable, sortable list (20 per page), plus a detail page and status changes. Opening it from a dashboard click adds that chart's filter, and the list total equals the chart value                                                          |
| **Report incident**    | Administrator, Officer | The form in section 3.4                                                                                                                                                                                                                                    |
| **Duplicate review**   | Administrator, Analyst | Suspected pairs side by side; confirm or dismiss                                                                                                                                                                                                           |
| **CSV import**         | Administrator          | Section 3.3                                                                                                                                                                                                                                                |
| **Quality summary**    | Administrator, Analyst | Import totals (rows, accepted, rejected) and rejections by rule, duplicates by status, and how complete the optional fields (landmark, description) are                                                                                                    |
| **Reports and export** | All roles              | CSV, Excel and PDF of exactly what the dashboard shows for the same filters and role. Each export is recorded in`report_exports` and in the audit log, and is rate-limited                                                                               |
| **Users** | Administrator | Create, view, edit, reset a password, deactivate, unlock or delete accounts, from each row's **Actions** menu (View, Edit, Unlock, Delete). The detail page shows the account, everything it has done (incidents, imports, exports, audit entries) and its last 10 actions. **Delete** works only for an account that has never been used, and always asks for confirmation first (the server refuses a delete that was not confirmed); one with a history is deactivated instead, so records keep naming who did them (D-52). Temporary passwords are generated and shown once. You cannot change your own role, status or password here, nor delete yourself, and the last active administrator is protected |
| **Reference data**     | Administrator          | Categories, sub-counties, wards and stations                                                                                                                                                                                                               |
| **Audit log**          | Administrator          | Read-only history of who did what and when                                                                                                                                                                                                                 |
| **Change password**    | Everyone               | Needs the current password; ends your other sessions                                                                                                                                                                                                       |

### The ten measures (A1 to A10)

All are computed with SQL over the counted incidents for the chosen period and filters. Every chart has a table view and shows its period, filters and n.

| Measure | What it shows                                                                                                                                                                                            |
| ------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| A1      | Total incidents (n)                                                                                                                                                                                      |
| A2      | Count and share per category                                                                                                                                                                             |
| A3      | Count per month, 3-month moving average, month-on-month change                                                                                                                                           |
| A4      | This period against the previous period of the same length                                                                                                                                               |
| A5      | Count per weekday                                                                                                                                                                                        |
| A6      | Count per time band (00 to 05, 06 to 11, 12 to 17, 18 to 23)                                                                                                                                             |
| A7      | Ward and sub-county ranking                                                                                                                                                                              |
| A8      | **Hotspot flag:** a ward is flagged when its z-score is at least 1.5 **and** it has at least 10 incidents (z = (ward count minus the mean over all wards) divided by the standard deviation) |
| A9      | Sub-county by month grid, shaded by quintile                                                                                                                                                             |
| A10     | Count per status, closure rate, and median and 90th-percentile reporting delay and time to close                                                                                                         |

Exact formulas and denominators are in docs/02, section 4. An independent script (`scripts/oracle.php`), written without looking at the application code, computes the same measures, and a test proves all 5,387 values match exactly.

---

## 6. What protects every request

In order, before any page logic runs:

| Layer                     | What it does                                                                                                              |
| ------------------------- | ------------------------------------------------------------------------------------------------------------------------- |
| `EnsurePreflightPasses` | Refuses every request (503) if the key or database settings are invalid                                                   |
| `SecurityHeaders`       | Sends a content security policy and anti-framing, MIME and referrer headers                                               |
| Session and CSRF          | Every form carries a secret token; a form posted from another site is rejected                                            |
| `auth`                  | Not signed in: sent to the login page                                                                                     |
| `EnsureAccountUsable`   | Deactivated mid-session: signed out at once. "Must change password": only the change-password page and logout work        |
| `role:` and Policies    | Wrong role: 403. An officer opening another station's incident: 404, so other stations' records cannot even be discovered |
| Form Requests             | Every input and filter validated, with sizes, formats and allowed values                                                  |
| Rate limits               | Login attempts and exports are throttled                                                                                  |

A test tries every one of the 34 routes as a guest, an administrator, an officer and an analyst, and checks each is allowed or refused exactly as designed.

---

## 7. Every command, what it does and when to use it

| Command                                                                      | What it does                                                                                                                                                         | Changes data?       | When                                                          |
| ---------------------------------------------------------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ------------------- | ------------------------------------------------------------- |
| `php artisan app:preflight`                                                | Checks settings, database server and build (section 2, step 6)                                                                                                       | No                  | After setup, after changing`.env`, whenever something fails |
| `php artisan migrate`                                                      | Applies new migrations                                                                                                                                               | Tables              | Setup, and after pulling new code                             |
| `php artisan db:seed`                                                      | Reference data and first administrator                                                                                                                               | Yes                 | Once, at setup                                                |
| `php artisan serve`                                                        | Starts the web server                                                                                                                                                | No                  | Every time you use the app                                    |
| `php artisan user:create`                                                  | Creates a user from the terminal (password typed hidden)                                                                                                             | Yes                 | Alternative to the Users screen                               |
| `php artisan sample:generate --size=N [--seed=S] [--end=YYYY-MM] [--load]` | Generates synthetic incidents and answer-key files;`--load` imports them                                                                                           | Yes with`--load`  | Demo data; evaluation runs                                    |
| `php artisan sample:evaluate <dir> <batch-id>`                             | Scores a loaded sample against its answer key: rejection recall per rule, false rejections, duplicate precision and recall, and why each missed duplicate was missed | No                  | Evaluation (Chapter 5 figures)                                |
| `php artisan sample:clear`                                                 | Deletes all synthetic incidents and their batches (asks first)                                                                                                       | Yes                 | Starting the demo data over                                   |
| `php artisan imports:prune`                                                | Closes dry runs older than 24 hours and deletes their files                                                                                                          | Yes                 | Runs hourly with`php artisan schedule:work`, or by hand     |
| `php artisan db:backup`                                                    | Writes a compressed dump of`crime_dashboard` to `storage/app/private/backups/`, readable by your account only                                                    | Adds an audit entry | Before a demo or big change                                   |
| `php artisan db:restore <file> --target=<schema>`                          | Loads a dump into the named schema (local only; asks first; refuses the live schema unless`--replace-live`)                                                        | Yes, in the target  | Testing a backup, or recovering                               |
| `php artisan e2e:prepare`                                                  | Rebuilds`crime_dashboard_e2e` with test accounts                                                                                                                   | Yes, e2e only       | Run automatically by Playwright                               |
| `php artisan key:generate`                                                 | Creates`APP_KEY`                                                                                                                                                   | `.env`            | Once, at setup                                                |
| `npm run build`                                                            | Builds styles and scripts                                                                                                                                            | `public/build/`   | Setup, and after pulling new code                             |
| `npm run dev`                                                              | Live-rebuilds styles and scripts while you edit                                                                                                                      | No                  | Only while changing the front end                             |
| `node scripts/progress-report.cjs`                                         | Rebuilds the Word progress report from`docs/reports/PROGRESS_REPORT.md`                                                                                            | The`.docx`        | After editing the report                                      |

In PowerShell, a command written `NAME=value command` becomes `$env:NAME='value'; command; Remove-Item Env:NAME`.

---

## 8. Testing: what each command proves

| Command                                          | Database used                    | Proves                                                |
| ------------------------------------------------ | -------------------------------- | ----------------------------------------------------- |
| `php artisan test`                             | `crime_dashboard_test` (wiped) | 310 tests covering:                                   |
| `npx playwright test`                          | `crime_dashboard_e2e` (wiped)  | A real browser, at laptop and phone widths, performs: |
| `vendor/bin/pint --test`                       | none                             | Code style is consistent                              |
| `vendor/bin/phpstan analyse --memory-limit=1G` | none                             | Static analysis, level 8: no type errors              |

Results, dates and commits are recorded in `docs/logs/TEST_LOG.md`, and evaluation results in `docs/logs/EVAL_LOG.md`.

---

## 9. Where everything is stored

| Location                         | Contents                                                                           | Committed to Git?                |
| -------------------------------- | ---------------------------------------------------------------------------------- | -------------------------------- |
| `crime_dashboard` database     | Users, reference data, incidents, import batches, exports log, audit log, sessions | No                               |
| `.env`                         | Your settings and secrets                                                          | Never                            |
| `storage/app/sample/`          | Generated sample files and answer keys                                             | No                               |
| `storage/app/private/imports/` | Uploaded CSVs, only until commit or discard                                        | No                               |
| `storage/app/private/backups/` | `db:backup` dumps                                                                | No                               |
| `storage/logs/laravel.log`     | Application errors (never passwords or personal data)                              | No                               |
| `public/build/`                | Built styles and scripts                                                           | No (rebuilt by`npm run build`) |
| `database/seeders/data/`       | Reference lists                                                                    | Yes                              |
| `docs/`                        | Requirements, design, test and evaluation logs, evidence, progress report          | Yes (except private notes)       |

---

## 10. Starting over

| Goal                                                                                                                            | Commands                                                                                                                |
| ------------------------------------------------------------------------------------------------------------------------------- | ----------------------------------------------------------------------------------------------------------------------- |
| Remove the demo incidents only                                                                                                  | `php artisan sample:clear`                                                                                            |
| Reload the demo incidents                                                                                                       | `php artisan sample:clear`, then `php artisan sample:generate --size=20000 --load`                                  |
| Rebuild the whole database from nothing (**deletes everything, including users, with no confirmation question in local**) | `php artisan db:backup` first, then `php artisan migrate:fresh` and `php artisan db:seed` (never add `--force`) |
| Return to a saved state                                                                                                         | `php artisan db:restore <file> --target=crime_dashboard --replace-live`                                               |
