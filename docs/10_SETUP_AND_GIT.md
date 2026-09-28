# 10. Setup

How to run the project on a fresh machine.

## 1. Machine prerequisites (verify first; install what is missing)

| Tool | Needed | Check |
|---|---|---|
| PHP | 8.4 (D-22) with mbstring, xml, curl, zip, mysql, bcmath, intl, gd | `php -v`, `php -m` |
| Composer | 2.x | `composer --version` |
| MariaDB | XAMPP's bundled MariaDB 10.4 on 127.0.0.1:3306, root with a blank password (D-04, D-25) | `/opt/lampp/bin/mysql --version` |
| Node and npm | 20 or newer | `node -v`, `npm -v` |
| Git | any recent | `git --version` |

Ubuntu 24.04 (run only what is missing):

```bash
sudo apt install -y php8.4-bcmath
sudo /opt/lampp/lampp startmysql
ss -ltnp | grep ':3306'                    # MariaDB must be listening
```

If Ubuntu's own MySQL server is installed, it starts at boot and takes port 3306 first (symptom: `Access denied for user 'root'@'localhost'`, error 1698). Stop it and keep it off: `sudo systemctl stop mysql && sudo systemctl disable mysql`, then start XAMPP's MariaDB again. MySQL cannot replace MariaDB here: analytics A10 uses `PERCENTILE_DISC`, which MySQL lacks.

## 2. Databases (two schemas on XAMPP MariaDB)

```bash
/opt/lampp/bin/mysql -h 127.0.0.1 -P 3306 -u root -e "
CREATE DATABASE IF NOT EXISTS crime_dashboard      CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE IF NOT EXISTS crime_dashboard_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE IF NOT EXISTS crime_dashboard_e2e  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
SHOW DATABASES LIKE 'crime%';"
```

The test schema must end in `_test`; the test suite refuses to run otherwise and wipes that schema on every run.

Recommended (R-18): XAMPP's MariaDB listens on every network interface. Add `bind-address=127.0.0.1` under `[mysqld]` in `/opt/lampp/etc/my.cnf`, then `sudo /opt/lampp/lampp restartmysql`.

## 2a. First run of the application

```bash
composer install && npm install && npm run build
cp .env.example .env            # then edit .env yourself: ADMIN_NAME, ADMIN_EMAIL, ADMIN_INITIAL_PASSWORD
php artisan key:generate
php artisan app:preflight       # must print "Preflight passed."
php artisan migrate             # never with --force
php artisan db:seed             # reference data and the first administrator from ADMIN_* in .env
php artisan sample:generate --size=20000 --load   # optional demo data (run once)
php artisan serve               # http://127.0.0.1:8000
php artisan test
```

The step-by-step guide with every setting and a troubleshooting table is in the project README.

## 3. Browser (end-to-end) tests

Playwright runs against a separate schema, `crime_dashboard_e2e`, which it wipes and rebuilds on every run (`php artisan e2e:prepare` refuses any database whose name does not end in `_e2e`). Test accounts get a random password generated for each run; nothing is stored.

```bash
/opt/lampp/bin/mysql -h 127.0.0.1 -P 3306 -u root -e "CREATE DATABASE IF NOT EXISTS crime_dashboard_e2e CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
npx playwright install chromium    # once
npm run build
npx playwright test                # desktop (1366 px) and mobile (360 px) projects
```

