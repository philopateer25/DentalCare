# DentalCare

DentalCare is a multi-tenant practice-management platform for dental clinics, with optional support for ophthalmology, plastic-surgery, and general-medical practices. It combines a Filament admin panel for day-to-day clinic operations with an Inertia + React frontend for onboarding, finance, calendar, analytics, support, and a 3D odontogram.

> **Status:** this repository is a working application, but it contains demo/development shortcuts that must not be shipped to production unchanged. Read [docs/SECURITY.md](docs/SECURITY.md) and [docs/RECOMMENDATIONS.md](docs/RECOMMENDATIONS.md) before exposing the app to a network.

## What the application does

- **Clinical records** — patients, medical history and alerts, appointments, operatories, examinations, tooth/surface findings, treatment plans and procedures, prescriptions, patient files.
- **3D odontogram** — React Three Fiber viewer with per-tooth GLB models, surface conditions, treatment overlays, voice-note playback, and a standalone full-screen view.
- **Billing and treasury** — invoices, invoice items, payments, installment plans/schedules, doctor commissions, clinic expenses, payroll slips, currency formatting.
- **Labs and inventory** — lab partners and lab orders, suppliers, inventory items/batches, FIFO/FEFO stock movements, consumption tracking.
- **Insurance** — payers/TPAs, patient policies, claims and pre-authorizations.
- **Communication** — WhatsApp bot via Evolution API, post-op voice notes and share links, database/push notifications, support tickets.
- **SaaS plumbing** — practices as tenants, branches, feature flags, onboarding wizard, system/developer panel, license heartbeat and tamper-resistant clock.
- **Localization** — English, Arabic (RTL), and French, with per-user and per-practice locale preference.

## Stack

| Layer | Technology |
| --- | --- |
| Backend | PHP 8.3+ (local audit used PHP 8.5.9), Laravel 11 |
| Admin UI | Filament v3 (multi-tenant panel at `/admin`) |
| Frontend | Inertia.js 3, React 19, Vite 8, Tailwind CSS 4 |
| 3D | Three.js, `@react-three/fiber`, `@react-three/drei`, GLB models |
| Auth / permissions | Laravel session auth, Spatie Laravel Permission, Filament panel access |
| Database | SQLite locally; MySQL 8 in Docker/production |
| Cache / queues | Database driver locally; Redis in production Docker |
| Notifications | Laravel database notifications, Web Push (`laravel-notification-channels/webpush`) |
| PDF | `barryvdh/laravel-dompdf` |
| Licensing | Custom `LicenseManager` + `TamperProofClock` (AES-256-GCM files) |

## Quick start (local)

### 1. Prerequisites

- PHP **8.3+** with `pdo_sqlite`, `mbstring`, `openssl`, `gd`, `zip`, `intl`, `bcmath` (and `pdo_mysql` if using MySQL)
- Composer 2
- Node.js 20+ and npm
- SQLite (bundled with PHP) or MySQL 8

### 2. Install

```bash
cp .env.example .env

composer install
php artisan key:generate

# SQLite (default)
touch database/database.sqlite

npm install
npm run build

php artisan migrate --seed
```

`--seed` is optional but creates the demo clinics, users, patients, invoices, labs, and a large inventory catalog. It can take a while because `PracticeObserver` seeds roughly 5,000 inventory items per created practice.

### 3. Run

```bash
php artisan serve
# in a second terminal, for the Vite dev server / HMR:
npm run dev
```

For queued jobs (WhatsApp messages, post-op reminders):

```bash
php artisan queue:work
```

For scheduled license ticks and heartbeats:

```bash
php artisan schedule:work
```

### 4. Open

| URL | Purpose |
| --- | --- |
| `http://localhost:8000/` | Public marketing/welcome page |
| `http://localhost:8000/admin` | Main clinic admin panel (tenant-aware) |
| `http://localhost:8000/system` | Developer / super-admin system panel |
| `http://localhost:8000/onboarding` | Clinic setup wizard (authenticated users without onboarding) |
| `http://localhost:8000/calendar` | Inertia calendar page |
| `http://localhost:8000/finance` | Inertia finance dashboard (requires `finance` feature) |
| `http://localhost:8000/analytics` | Inertia analytics dashboard |
| `http://localhost:8000/support` | Clinic helpdesk |
| `http://localhost:8000/patients/{patient?}/odontogram` | 3D odontogram (requires `3d_model` feature) |

## Demo accounts

The seeders create these accounts. **Every demo account uses the password `password`.** Do not keep these credentials in a production database.

| Email | Role(s) | Practice | Notes |
| --- | --- | --- | --- |
| `admin@clinic.com` | `super_admin`, `clinic_admin` | DentalCare Main Clinic (id 1) | Can access all tenants and `/system` |
| `super@clinic.com` | `super_admin` | Practice 1 | Full cross-tenant access |
| `dev@clinic.com` | `developer` | none | `/system` access, bypasses gates |
| `dr@clinic.com` | `doctor` | Practice 1 | Clinical user |
| `secretary@clinic.com` | `secretary` | Practice 1 | Front desk |
| `dr.ahmed@clinic.com`, `dr.sara@clinic.com` | `doctor` | Practice 1 | Demo dentists |
| `reception@clinic.com` | `secretary` | Practice 1 | Demo receptionist |
| `dr@advanced.com`, `admin@advanced.com` | doctor / clinic_admin | Advanced Dental Center | Demo tenant |
| `dr@smilecare.com`, `admin@smilecare.com` | doctor / clinic_admin | Smile Care Clinic | Demo tenant with suspended license status |
| `dr@clearvision.com`, `admin@clearvision.com` | doctor / clinic_admin | ClearVision Eye Center | Ophthalmology demo |
| `dr@aesthetics.com`, `admin@aesthetics.com` | doctor / clinic_admin | Aesthetics Plastic Surgery | Plastic-surgery demo |

`admin@dentalcare.com` and `doctor@dentalcare.com` are also created by demo seeders, but they are not given a practice/role by the base `DatabaseSeeder`; use the accounts above for panel access.

## Environment variables

At minimum, copy `.env.example` and set:

```dotenv
APP_NAME=DentalCare
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:8000
APP_KEY=base64:...
DB_CONNECTION=sqlite
```

Production/Docker adds MySQL, Redis, mail, and queue settings. The integration keys below are used by code but are **not present in `.env.example`** yet:

```dotenv
# License server (LicenseManager)
LICENSE_SERVER_URL=
LICENSE_API_KEY=
LICENSE_GRACE_PERIOD_HOURS=72
LICENSE_ALERT_WARNING_HOURS=48
LICENSE_ALERT_URGENT_HOURS=66
LICENSE_TICK_INTERVAL=10
LICENSE_HEARTBEAT_INTERVAL=4

# WhatsApp / Evolution API (read via env() in jobs/pages)
EVOLUTION_API_URL=
EVOLUTION_API_KEY=

# Gemini analysis
GEMINI_API_KEY=

# Web Push
VAPID_PUBLIC_KEY=
VAPID_PRIVATE_KEY=
VAPID_SUBJECT=mailto:admin@example.com
```

Several of these are read with `env()` at runtime instead of through `config()`. That breaks configuration caching. See the recommendations for the fix.

## Tests

```bash
# Unit tests (currently green)
php artisan test --testsuite=Unit

# Feature tests
php artisan test --testsuite=Feature
```

Known issues at the time of this audit:

- The unit suite passes (16 tests / 68 assertions).
- `tests/Feature/DashboardWidgetSystemTest.php` fails in 3 tests because `PracticeObserver` seeds demo inventory and procedure catalogs whenever a test creates a `Practice`. For example, the low-stock assertion expects 1 item but receives 78.
- The full feature suite can exhaust the default 128 MB PHP memory limit inside `DentalInventorySeeder` while creating test practices.
- PHPUnit reports deprecations for doc-comment metadata under PHPUnit 11/12.

Recommended test command while the observer issue is present:

```bash
php -d memory_limit=1G vendor/bin/phpunit
```

Adding `<ini name="memory_limit" value="1G"/>` to `phpunit.xml`, and disabling `PracticeObserver` during tests, is a better long-term fix. See [docs/DEVELOPMENT.md](docs/DEVELOPMENT.md#8-testing).

## Repository map

```text
app/
  Console/Commands/          license:tick, license:heartbeat, odontogram migration
  Filament/                  Admin panel resources, widgets, pages, system panel resources
  Http/Controllers/          Inertia pages + API controllers
  Http/Middleware/           locale, license, feature flags, onboarding, Inertia shared props
  Jobs/                      queue jobs (WhatsApp, post-op checkups)
  Models/                    Eloquent domain models
  Notifications/             database / web-push notifications
  Observers/                 practice seeding, appointment invoicing, procedure completion
  Policies/                  role + tenant authorization
  Providers/Filament/        admin and system panel configuration
  Services/                  business logic (licensing, inventory, billing, analytics, widgets)
database/
  migrations/                63+ tables across tenancy, clinical, billing, labs, inventory, HR, licensing
  seeders/                   demo clinics, catalogs, inventory (~5k items), finance, labs, staff
resources/
  js/                        Inertia React app, components, 3D viewer
  views/                     Blade shells + Filament custom views/PDF templates
routes/
  web.php                    all web + API endpoints (there is no routes/api.php)
  console.php                scheduler
deploy/                      Windows on-prem installer
docker/                      PHP entrypoint + nginx config
public/models/               large GLB assets (263 MB)
```

## Documentation

| Document | Read this for |
| --- | --- |
| [docs/README.md](docs/README.md) | Documentation index and suggested reading order |
| [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md) | Runtime architecture, tenancy, request flow, panels, frontend, cross-cutting concerns |
| [docs/DATA_MODEL.md](docs/DATA_MODEL.md) | Tables, relationships, tenancy boundaries, odontogram and billing models |
| [docs/MODULES.md](docs/MODULES.md) | Functional walkthrough of every business module and user role |
| [docs/API.md](docs/API.md) | Endpoint inventory, auth requirements, payload examples, API gaps |
| [docs/DEVELOPMENT.md](docs/DEVELOPMENT.md) | Local setup, workflows, testing, conventions, troubleshooting, seeded data |
| [docs/DEPLOYMENT.md](docs/DEPLOYMENT.md) | Docker, production, Windows installer, license activation, backups |
| [docs/SECURITY.md](docs/SECURITY.md) | Security model and findings with severity and file references |
| [docs/RECOMMENDATIONS.md](docs/RECOMMENDATIONS.md) | Prioritized fixes and roadmap |

## Before production

At minimum:

1. Remove `GET /quick-login` and the fallback auto-login in `PatientOdontogramController`.
2. Put `auth` / tenant middleware on the clinical APIs and verify practice ownership inside every endpoint.
3. Remove demo users/passwords and the hardcoded Tailscale key from `deploy/install.ps1`; rotate anything that has been committed.
4. Verify WhatsApp webhook requests with a shared secret/signature.
5. Add the missing `branch_id` column or remove branch filtering from analytics.
6. Make the test suite deterministic before relying on it as a release gate.

See [docs/RECOMMENDATIONS.md](docs/RECOMMENDATIONS.md) for a fuller, ordered plan.
