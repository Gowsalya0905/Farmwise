# Farmwise

## Dashboard and workspace controls

The Dashboard replaces Home and shows farmer-scoped farm/plot/crop counts, active/planned/completed season counts, monthly activity and harvest counts, and monthly financial totals separated by currency. It no longer duplicates the farm list or creation form. `GET /api/dashboard` requires the same authenticated session as other protected APIs. Monthly cards use India calendar dates (Asia/Kolkata).

The header, sidebar and beta footer stay in place; only the right-side main content scrolls. Compact sidebar layouts keep all modules available at shorter viewport heights. The footer displays Farmwise, all rights reserved and Version beta.

The Farms page exposes edit and delete actions using the existing API, with explicit deletion confirmation and protection for farms containing plots. The only Add farm action remains beneath the farm-name input. Shared searchable dropdowns provide list filtering, keyboard selection, focus styling and accessible combobox/listbox semantics without adding Bootstrap or jQuery. Season crop choices display crop name and variety, with an explicit fallback when no variety is specified. Reports includes Clear filters, which resets every filter and reloads the unfiltered report.

Expenses, harvests and sales are actual records: create/update dates cannot be later than today in India, enforced by the API and date picker limits. Sales must also occur on or after their harvest date. Season planning dates and activity dates still allow future values. Existing stored records are not rewritten. Automated tests cover the India/UTC date boundary and Dashboard farmer isolation.

## Activities, expenses, harvests, sales and reports

These dashboard modules are now available. Run `php artisan migrate` from `backend` to create their tables. Start with a season, record activities and expenses, add harvests, then add sales against a harvest. Each record page supports paginated listing, creation, editing and deletion.

Activities store date, type and optional notes. Expenses store date, category, positive amount, currency and optional notes. Harvests store date, positive quantity, unit and optional notes. Sales store harvest, date, quantity, matching unit, unit price, currency and optional notes. Supported currencies are INR/USD/EUR/GBP; units are kg/tonne/litre/piece. Quantities allow four decimal places and money two. Sales totals are calculated on the server with half-up rounding; clients cannot supply totals. Stock writes are serialized with a harvest row lock. Sales cannot exceed remaining stock or precede the harvest date.

Authenticated `/api/activities`, `/api/expenses`, `/api/harvests` and `/api/sales` resources support CRUD with session CSRF protection, farmer-scoped queries and ownership policies. Related season/harvest IDs must belong to the current farmer. Seasons with records and harvests with sales cannot be deleted. Season plot/crop links freeze once records exist. Sold harvests cannot change season/unit or shrink below sold quantity, and a sale cannot move to another harvest.

`GET /api/reports` accepts optional `year`, `farm_id` and `season_id`. It returns financial rows by season/farm/year/currency, separate currency totals and harvest quantities grouped by unit. The Reports page provides those filters. Net is recorded sales minus recorded expenses, excluding unrecorded costs and unsold inventory. Area is the current plot area; normalized yield and currency/unit conversion are not included. Updates and explicit deletions are permitted; these modules do not provide an accounting audit trail. Farm overhead allocation and advanced comparisons remain future work.

`RecordsTest` covers CRUD, guests, CSRF, cross-farmer records and foreign relationship IDs, decimal validation, exact totals, stock limits, sale dates, deletion restrictions and separated report currencies/units/year filters.

## Plots, crops and seasons

The dashboard now provides paginated listing, creation, editing and deletion for plots, crops and seasons. Run `php artisan migrate` from `backend` to create the new tables before opening these pages.

Create a farm, then a plot under that farm; add your crop definitions, then create seasons linking a plot and crop. Plot area is optional and accepts up to four decimal places in acres, hectares or square metres. Crops have a name and optional variety. Seasons have a name, required planting/start date, optional planned/end date, and planned, active, completed or cancelled status. End dates cannot precede start dates. Multiple seasons may use the same plot; overlapping dates are permitted.

Authenticated `/api/plots`, `/api/crops` and `/api/seasons` resources support list/create/read/update/delete with session CSRF protection, owner-scoped queries and policies. Related IDs must belong to the current farmer; clients cannot assign owner IDs. Lists paginate at 25 records. Update requests supply the full form. Farms containing plots, and plots/crops containing seasons, return 409 on deletion. Plots with seasons cannot move to another farm. Foreign keys restrict deletion rather than cascading cultivation history. Future activities and financial modules must add equivalent history protection before exposing their routes.

`CultivationTest` covers guest access, farmer isolation, foreign relationship assignment, owner spoofing, CRUD, area/date/status validation, deletion restrictions and CSRF.

Farmwise helps farmers record farm work, costs, harvests and sales. The React JavaScript/JSX frontend and Laravel REST API support account management, owner-isolated farming records and basic reports.

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

Again, copy `.env.example` only on first setup. On your laptop open `http://localhost:5173`. Use **Check API connection** on the sign-in page; it should say **Connected — the Farmwise API is reachable.** The backend health endpoint is `http://localhost:8000/api/health` and returns `{"status":"ok","application":"Farmwise"}`. The health endpoint checks API availability, not database health; migrations and `migrate:status` verify the database.

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

The JSX frontend supports registration at `/register` and sign in at `/login`, then navigates to the protected `/dashboard`. Refresh restores the Laravel cookie session before rendering protected content. The dashboard has responsive navigation, the farmer's name and logout, an API-backed farm count, and paginated farm listing and creation. `/dashboard/farms` uses the same farm component. The remaining sidebar modules have clear Coming soon pages. Account changes clear farm state and cancel pending farm requests; no session credentials are stored in browser storage. The original health check remains available through the sign-in page's Check API connection button.

Routing uses the browser History API with React's external-store subscription; links support browser back/forward and opening in a new tab. Vite provides development SPA fallback. When hosting the frontend, configure the web server to serve `frontend/dist/index.html` for frontend routes such as `/login` and `/dashboard/farms` so direct links and refresh work. Keep Laravel API routes served by the backend. The dashboard, profile and password recovery use the existing dependencies and migrations.

Tests use isolated SQLite and cover two-farmer isolation, owner spoofing, authentication, logout, CSRF and throttling. Cultivation records, financial records and reports enforce the ownership and relationship requirements described above.

Verification on this Windows environment: PHP 8.2.12 and Composer 2.8.9 are available. System Node 20.11.1 is too old for the locked Vite version; install Node 24 LTS. MySQL responds on port 3306 but rejects the example root credentials. Enter valid credentials in ignored `backend/.env`, then run migrations; no database credentials or accounts have been changed.

The planned ownership and relationships are documented in [docs/data-model.md](docs/data-model.md). Implement them through migrations, policies and authenticated API tests as each module is added. Never return another farmer's records, and never trust a submitted owner ID.

## Cloud verification

This workspace originally had no PHP, Composer or MySQL. PHP 8.4 and Composer were prepared outside the repository, and a fresh isolated Docker MySQL 8.4 development instance was started on loopback port 3306 with a `farmwise` database. No existing databases were modified. Migrations ran successfully here. This did not create anything on your Windows laptop: run the Windows steps above there.

The local development database has no password and is bound only to loopback. It is disposable development infrastructure, not a production database. Cloud PHP activation uses `/workspace/tooling/bin`; it is not a required laptop path. Live processes must be started again in future cloud sessions.

Dependencies, local environment files, application keys, build outputs and logs are ignored by Git. Only `.env.example` templates and lockfiles belong in version control.

## Profile and password recovery

Protected `/profile` is part of the dashboard, available from the initials avatar account dropdown. It shows and updates the signed-in farmer's name and email. Changing email requires the current password; unchanged email/name updates do not. A separate change-password form requires the current password and a confirmed new password of 12 to 72 characters. Server validation normalizes email to lowercase, enforces uniqueness, derives the account from the session and rejects `id`/`user_id` account selectors. Password changes keep the current session and invalidate other sessions. Email or password changes revoke outstanding reset links.

The compact dashboard header includes the page title and account dropdown. Add farm appears only beneath the farm-name input in the Farms module. The sidebar retains every module, adds consistent icons and prominent Farmwise branding, and becomes a mobile drawer with focus containment, Escape-to-close and a close button. Keyboard users can tab through account actions and close the dropdown with Escape.

Frontend routes: `/login`, `/register`, `/forgot-password`, `/reset-password?token=...&email=...`, protected `/dashboard`, `/dashboard/farms`, the remaining dashboard module routes, and protected `/profile`. Configure frontend SPA fallback for all these paths, and serve API requests through Laravel. Reset links contain a temporary credential; the reset page removes it from browser history after success and does not save it in browser storage. The frontend uses a no-referrer policy to keep reset credentials out of outgoing referrer headers. Host over HTTPS and avoid analytics or third-party scripts on recovery pages.

API endpoints (all mutations retain session CSRF protection):

- `PATCH /api/auth/profile`: name, email and current_password when email changes. Returns the updated current farmer.
- `PUT /api/auth/password`: current_password, password and password_confirmation.
- `POST /api/auth/forgot-password`: email. Returns the identical confirmation for known, unknown and broker-throttled addresses. Reset tokens never appear in API responses. Mail transport failures are reported to server logs and also return the generic confirmation.
- `POST /api/auth/reset-password`: email, token, password and password_confirmation. Invalid, expired and reused tokens receive validation errors. A successful reset requires signing in again.

Recovery requests and reset attempts are limited to five per minute per IP; password changes use the same limit, and profile updates allow ten per minute. Laravel's current unnamed limiters share an IP bucket across endpoints with the same limit, so repeated authentication attempts can temporarily limit recovery too. The password broker additionally limits sending to one reset email per account per 60 seconds. Tokens expire after 60 minutes, are stored hashed, and are deleted after successful use. Resets are serialized per account in a database transaction to prevent concurrent token reuse. Password reset rotates the remember token and rejects every existing authenticated session on its next protected request, for both file and database session drivers. Sessions established before this update have no password-hash marker and will need to sign in again. No new packages or database migrations are required; use the existing users and password_reset_tokens migrations.

### Local email testing

Keep `MAIL_MAILER=log` in your ignored `backend/.env`, with `LOG_CHANNEL=stack` and `LOG_STACK=single`. Submit Forgot password using a registered farmer's email. No actual email is sent: the message, including the React reset link, appears in `backend/storage/logs/laravel.log`. Open that link in your browser and set a new password. Unknown addresses receive the same confirmation but produce no reset email. The log includes sensitive reset links: keep it local and private, and never commit or share it. Tests fake notifications and use isolated SQLite, so they do not send mail or change your local database.

`FRONTEND_URL` controls the destination: reset mail uses the **first** comma-separated URL, while CORS trusts every listed origin. Put your preferred local origin first (normally `http://localhost:5173`) and use the same hostname for frontend and API. After changing `.env`, run `php artisan config:clear` and restart the backend.

The link customization uses Laravel's [ResetPassword::createUrlUsing API](https://api.laravel.com/docs/12.x/Illuminate/Auth/Notifications/ResetPassword.html#method_createUrlUsing).

To view messages in a local SMTP inbox such as Mailpit, start your local inbox separately and use `MAIL_MAILER=smtp`, `MAIL_HOST=127.0.0.1`, `MAIL_PORT=1025`, blank `MAIL_USERNAME`/`MAIL_PASSWORD` and blank `MAIL_SCHEME`. Open the inbox's web interface to read the link. No inbox service is installed by this project.

### Production SMTP

Set these values in the deployment's private environment, using your SMTP provider's actual settings:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://api.your-domain.example
FRONTEND_URL=https://your-domain.example
SESSION_SECURE_COOKIE=true
MAIL_MAILER=smtp
MAIL_SCHEME=smtp
MAIL_HOST=smtp.your-provider.example
MAIL_PORT=587
MAIL_USERNAME=your-smtp-username
MAIL_PASSWORD=your-smtp-password
MAIL_FROM_ADDRESS=farms@your-domain.example
MAIL_FROM_NAME="Farmwise"
```

Port 587 uses STARTTLS when supported by the SMTP server. If your provider requires implicit TLS on port 465, use `MAIL_SCHEME=smtps` and `MAIL_PORT=465`. Match the provider's requirements and verify an actual reset email in staging before deployment. Keep credentials out of Git and frontend variables. Run `php artisan config:cache` after configuring production, restart PHP workers, and monitor server logs for delivery failures. Notifications send synchronously with the existing configuration; no queue worker is required. Use `php artisan auth:clear-resets` periodically to remove expired token rows (expiry enforcement does not depend on cleanup).

The backend suite covers profile authentication and account-selection rejection, duplicate and invalid emails, current-password validation, password confirmation, session invalidation, identical reset request responses, rate limits, hashed token storage and frontend link generation, invalid/expired/reused tokens, and CSRF. Existing farm-isolation tests remain in place; their simulated signed-in session includes the new login marker.
