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

## 2. Databases (two schemas on XAMPP MariaDB)

```bash
/opt/lampp/bin/mysql -h 127.0.0.1 -P 3306 -u root -e "
CREATE DATABASE IF NOT EXISTS crime_dashboard      CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE IF NOT EXISTS crime_dashboard_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
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
php artisan test
```
