# Farmwise

Farmwise will help farmers record farm work, costs, harvests and sales, and compare seasons. The React JavaScript/JSX frontend and Laravel REST API now support registration, sign in, sign out, account restoration and owner-isolated farms. Other farming modules are planned.

## Requirements

- Node.js 24 LTS and npm (Node 22.12+ also meets Vite's minimum)
- PHP 8.2–8.4 and Composer 2.8+; PHP extensions: ctype, curl, dom, fileinfo, filter, hash, mbstring, openssl, PDO, pdo_mysql, session, tokenizer, xml, xmlwriter. Tests also need pdo_sqlite/sqlite3.
- MySQL 8.4 LTS recommended. XAMPP's MySQL button commonly runs MariaDB; Laravel also supports MariaDB 10.3+, though this project was verified against MySQL 8.4.

The frontend uses React 19, JavaScript and Vite 8. The backend uses Laravel 12. Commit both lockfiles; use `npm ci` and `composer install` rather than updating dependencies during setup. Composer resolves dependencies for PHP 8.2 so an XAMPP PHP 8.2 installation can use the same lockfile.

## Windows setup with XAMPP

Install Node.js, XAMPP with PHP 8.2 or newer, and Composer. During Composer installation select `C:\xampp\php\php.exe`. Add `C:\xampp\php` to your PATH if `php` is not recognized. Reopen PowerShell and check:

```powershell
node --version
npm --version
php --version
composer --version
php -m
```

In `C:\xampp\php\php.ini`, enable the required extensions (remove a leading `;` from their extension lines). `pdo_mysql` and `mbstring` are especially important. For tests, enable `pdo_sqlite` and `sqlite3`. Restart any running PHP server after editing this file. If PowerShell blocks `npm.ps1`, use `npm.cmd` in the commands below.

Start **MySQL** in the XAMPP Control Panel. Apache is not needed for `php artisan serve`. Open PowerShell in the Farmwise repository folder. Create only the database; Laravel migrations create the tables:

```powershell
& C:\xampp\mysql\bin\mysql.exe -u root -p -e "CREATE DATABASE IF NOT EXISTS farmwise CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

Enter your existing local MySQL password when prompted. On a default local XAMPP installation with no root password, press Enter. Do not change another database or its user accounts. Alternatively, phpMyAdmin can create an empty database named `farmwise`; do not manually create tables.

Prepare the backend:

```powershell
cd backend
composer install
Copy-Item .env.example .env
php artisan key:generate
```

Use `Copy-Item` only the first time: keep an existing `.env`. Edit `backend/.env` to match your own database settings:

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=farmwise
DB_USERNAME=root
DB_PASSWORD=
```

If your MySQL user has a password, enter it only in this ignored local `.env`. Quote values containing spaces or `#`. If XAMPP uses a different port, change `DB_PORT`. The empty password in the example describes a local default, not a production recommendation. For a deployed app, use a dedicated database user, a strong password and `APP_DEBUG=false`.

```powershell
php artisan config:clear
php artisan migrate
php artisan migrate:status
php artisan serve --host=127.0.0.1 --port=8000
```

Leave that terminal running. In a **second PowerShell terminal**, from the repository folder:

```powershell
cd frontend
npm ci
Copy-Item .env.example .env
npm run dev
```

Again, copy `.env.example` only on first setup. On your laptop open `http://localhost:5173`. The page should say **Connected — the Farmwise API is reachable.** The backend health endpoint is `http://localhost:8000/api/health` and returns `{"status":"ok","application":"Farmwise"}`. The health endpoint checks API availability, not database health; migrations and `migrate:status` verify the database.

For macOS/Linux, use `cp .env.example .env` instead of `Copy-Item`; the Composer, Artisan and npm commands are otherwise the same.

## API communication and CORS

`frontend/src/lib/api.js` uses `VITE_API_BASE_URL` (default `http://localhost:8000/api`). Public Vite variables are visible in the browser: never put secrets in them. Restart Vite after changing frontend `.env`.

The backend accepts origins listed in `FRONTEND_URL`, separated by commas, and allows credentialed requests. Use the same hostname for frontend and backend: `localhost:5173` with `localhost:8000`, or `127.0.0.1:5173` with `127.0.0.1:8000`. Vite uses port 5173 with `strictPort`. CORS is not authentication. Auth and farms routes use Laravel's web session and CSRF middleware; the frontend sends cookies with every account request and fetches a fresh CSRF token before mutations. Deploy both applications on the same site over HTTPS with `SESSION_SECURE_COOKIE=true`, `APP_DEBUG=false` and explicitly trusted frontend origins. Never store session credentials in browser storage.

If the page reports unavailable, check that both servers are running, then check the API URL, ports and frontend origin. After editing backend `.env`, run `php artisan config:clear`; restart the backend if necessary.

## Checks

Backend (from `backend`):

```powershell
composer validate --strict
php artisan test
php vendor/bin/pint --test
php artisan migrate:status
```

Frontend (from `frontend`):

```powershell
npm run lint
npm run build
```

The backend tests cover health JSON, allowed/rejected CORS origins, authentication, CSRF, throttling and farmer isolation. Tests use in-memory SQLite and do not touch `farmwise`. A successful migration run against MySQL separately verifies the database. The frontend build bundles the JavaScript and JSX application.

## Folder structure and next modules

```text
frontend/src/
  components/     shared UI components
  features/       future auth, farms, seasons, activities, expenses, harvests, sales, reports
  lib/api.js      shared API communication
backend/app/
  Http/Controllers/Api/   REST API controllers (HealthController exists)
  Http/Requests/          future validation and authorization
  Http/Resources/         future JSON response shapes
  Models/                Eloquent models (Laravel User scaffold exists)
  Services/              future domain logic and reports
backend/routes/api.php   API routes, automatically prefixed /api
backend/database/migrations/  versioned table definitions
backend/tests/           unit and API feature tests
```

Laravel's starter tables and the owner-indexed `farms` table are defined by migrations. Run `php artisan migrate` after configuring your own MySQL credentials. Existing databases and credentials are not replaced.

## Authentication and farm isolation

- `GET /api/auth/csrf` returns a session CSRF token. Send it as `X-CSRF-TOKEN` on mutations.
- `POST /api/auth/register` accepts name, email, password and password_confirmation; passwords require 12 characters. Email is normalized to lowercase.
- `POST /api/auth/login` accepts email and password. Registration and login are limited to five requests per minute per IP.
- `GET /api/auth/user` restores the current account; `POST /api/auth/logout` invalidates the session. Both require authentication.
- `/api/farms` supports authenticated list/create and `/api/farms/{id}` supports read/update/delete. Only name is writable. Lists paginate at 25 records. Ownership comes from the authenticated user, and submitted `user_id` values are rejected. Foreign farm IDs return 404; policies enforce ownership too.

The JSX account panel supports registration, sign in/out, and listing/adding farms. The original health check remains available. Tests use isolated SQLite and cover two-farmer isolation, owner spoofing, authentication, logout, CSRF and throttling. Plots, crops, seasons, financial records and reports are not exposed yet; they must enforce the relationship requirements in the data model before adding routes.

Verification on this Windows environment: PHP 8.2.12 and Composer 2.8.9 are available. System Node 20.11.1 is too old for the locked Vite version; install Node 24 LTS. MySQL responds on port 3306 but rejects the example root credentials. Enter valid credentials in ignored `backend/.env`, then run migrations; no database credentials or accounts have been changed.

The planned ownership and relationships are documented in [docs/data-model.md](docs/data-model.md). Implement them through migrations, policies and authenticated API tests as each module is added. Never return another farmer's records, and never trust a submitted owner ID.

## Cloud verification

This workspace originally had no PHP, Composer or MySQL. PHP 8.4 and Composer were prepared outside the repository, and a fresh isolated Docker MySQL 8.4 development instance was started on loopback port 3306 with a `farmwise` database. No existing databases were modified. Migrations ran successfully here. This did not create anything on your Windows laptop: run the Windows steps above there.

The local development database has no password and is bound only to loopback. It is disposable development infrastructure, not a production database. Cloud PHP activation uses `/workspace/tooling/bin`; it is not a required laptop path. Live processes must be started again in future cloud sessions.

Dependencies, local environment files, application keys, build outputs and logs are ignored by Git. Only `.env.example` templates and lockfiles belong in version control.
