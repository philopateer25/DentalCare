# Development guide

## 1. Prerequisites

| Tool | Version used | Notes |
| --- | --- | --- |
| PHP | 8.3+ (local audit on 8.5.9) | Needs `pdo_sqlite`/`pdo_mysql`, `mbstring`, `intl`, `gd`, `zip`, `bcmath`, `xml`, `pcntl` |
| Composer | 2.x | |
| Node.js | 20+ | Vite 8 / React 19; no `engines` field is declared |
| npm | 10+ | A `package-lock.json` exists |
| SQLite | 3.35+ | Default local database |
| MySQL | 8.0 | Production/Docker |
| Redis | 7 | Production cache/queue (optional locally) |

Optional: Docker Desktop (compose), Windows for `deploy/install.ps1`.

## 2. First-time setup (local)

```bash
# 1. PHP dependencies
composer install

# 2. Environment
cp .env.example .env
php artisan key:generate

# 3. SQLite database
touch database/database.sqlite

# 4. Schema (and demo data, see warning below)
php artisan migrate

# Optional: full demo dataset
php artisan db:seed

# 5. Frontend
npm install
npm run build        # or: npm run dev (Vite HMR on :5173)

# 6. Storage link (patient files / public uploads)
php artisan storage:link

# 7. Run
php artisan serve    # http://localhost:8000
php artisan queue:work --sleep=3 --tries=3
php artisan schedule:work
```

Then open:

- App/admin: <http://localhost:8000/admin> → log in with a seeded account.
- System panel: <http://localhost:8000/system> (developer/super admin only).
- Inertia app: <http://localhost:8000/dashboard> (empty page, see below).

### Seeding warning

`php artisan db:seed` is **heavy**. `DatabaseSeeder` runs `PracticeObserver` indirectly through `ClinicDataSeeder`/`MultiClinicSeeder`, and the observer seeds the entire dental catalog and ~5,000 inventory rows per practice. It can exhaust the default 128 MB PHP memory limit. If it fails:

```bash
php -d memory_limit=1G artisan db:seed
```

Or seed selectively:

```bash
php artisan db:seed --class=RoleSeeder
php artisan db:seed --class=ClinicDataSeeder
```

`DatabaseSeeder` uses `WithoutModelEvents`, but child seeders that create practices still trigger the observer because the trait only applies to the seeder's own model events in some Laravel versions. Verify behavior before relying on it.

### Broken convenience script

`composer dev` runs `php artisan dev`, but there is no `dev` command. Use `php artisan serve` + `npm run dev` + `php artisan queue:work` in separate terminals, or run the Docker stack.

## 3. Environment variables

`.env.example` only contains stock Laravel keys. These are read at runtime but **missing from it**:

| Key | Used by | Default |
| --- | --- | --- |
| `EVOLUTION_API_URL` | WhatsApp webhook/jobs/settings | `http://evolution_api:8080` |
| `EVOLUTION_API_KEY` | Evolution API auth | empty |
| `GEMINI_API_KEY` | Voice-note transcription | none |
| `VAPID_PUBLIC_KEY`, `VAPID_PRIVATE_KEY`, `VAPID_SUBJECT` | Web Push | none |
| `LICENSE_SERVER_URL`, `LICENSE_API_KEY` | `config/license.php` | `https://auth.dentalcare.app` |
| `LICENSE_GRACE_PERIOD_HOURS`, `LICENSE_ALERT_WARNING_HOURS`, `LICENSE_ALERT_URGENT_HOURS` | License policy | 72 / 48 / 66 |
| `LICENSE_TICK_INTERVAL`, `LICENSE_HEARTBEAT_INTERVAL` | Scheduler | 10 min / 4 h |

Because several services call `env()` at runtime instead of `config()`, `php artisan config:cache` will make those values `null` in production. Move them into config files before caching.

Local license bypass: `APP_ENV=local` or `testing` makes `LicenseManager` always report active. In `production`, a fresh install with no `storage/app/license.enc` reports `inactive`, and `CheckLicenseStatus` blocks `/admin` with 403. There is no activation UI/command yet (see [DEPLOYMENT §5](DEPLOYMENT.md#5-license-activation)).

## 4. Seeded accounts

All seeded users use password `password`.

| Email | Role | Practice |
| --- | --- | --- |
| `dev@clinic.com` | developer | none (system panel) |
| `super@clinic.com` | super_admin | 1 |
| `admin@clinic.com` | clinic_admin | 1 |
| `dr@clinic.com` | doctor | 1 |
| `secretary@clinic.com` | secretary | 1 |
| `dr.ahmed@clinic.com` | doctor | 1 |
| `dr.sara@clinic.com` | doctor | 1 |
| `reception@clinic.com` | secretary | 1 |
| `dr@advanced.com`, `admin@advanced.com` | doctor / clinic_admin | Advanced practice |
| `dr@smilecare.com`, `admin@smilecare.com` | doctor / clinic_admin | SmileCare |
| `dr@clearvision.com`, `admin@clearvision.com` | doctor / clinic_admin | ClearVision (ophthalmology) |
| `dr@aesthetics.com`, `admin@aesthetics.com` | doctor / clinic_admin | Aesthetics |
| `admin@dentalcare.com`, `doctor@dentalcare.com` | none | no `practice_id`, no role — cannot access either panel |

`MultiClinicSeeder` creates the extra practices and their catalogs. `RoleSeeder` creates the five roles. Note that `/quick-login` prefers `admin@clinic.com` and lands on `/admin/1/patients/1`.

## 5. Demo data

| Seeder | What it adds |
| --- | --- |
| `ClinicDataSeeder` | Practice 1, branches, operatories, users, patients, appointments |
| `MultiClinicSeeder` | Four additional practices with users and data |
| `RoleSeeder` | Roles + core accounts |
| `DentalCatalogSeeder` | Procedure categories and CDT-style codes |
| `PatientDataSeeder` | Patients, histories, plans, invoices, payments |
| `DentalInventorySeeder` | Suppliers, items, batches, movements (~5k rows) |
| `DentalLabSeeder` | Labs and orders |
| `DentalFinanceSeeder` | Invoices, installments, payments, commissions, expenses |
| `DentalStaffAndInsuranceSeeder` | Staff, payroll, providers, policies, claims |
| `OperatorySeeder` | Extra chairs |
| `Eye*Seeder` | Ophthalmology catalog/inventory/labs for `type = ophthalmology` |

The seeded database file `database/database.sqlite` (25 MB) is committed. It may already contain data; `migrate` is safe, `migrate:fresh` is destructive.

## 6. Front-end architecture

- Inertia entry: `resources/js/app.jsx`. Pages live in `resources/js/Pages/**` as `.jsx` or `.tsx`.
- Layout: `resources/js/Layouts/AppLayout.jsx` (dark theme, navbar, footer). `LocaleProvider` wraps every page.
- Styling: Tailwind CSS 4 via `@tailwindcss/vite`; no `tailwind.config.js`, theme tokens live in `resources/css/app.css` under `@theme`.
- 3D: `@react-three/fiber` + `drei` + `three`; models in `public/models` (large; `teeth-seperated*.glb` are ~31 MB each). `public/draco` holds the decoder.
- There is **no `tsconfig.json`**, so `.tsx` files are transpiled but not type-checked. `npm run build` will not catch type errors. If you want type safety, add a `tsconfig.json` and a `tsc --noEmit` script.
- Page states as of the audit:

| Page | State |
| --- | --- |
| `Welcome/Welcome.jsx` | Complete marketing page |
| `onboarding.jsx` | Complete 5-step wizard |
| `calendar.jsx` | Working calendar |
| `finance.jsx` | Working read-mostly finance view |
| `analytics.jsx` | Working analytics view |
| `support.jsx` | Working ticket UI |
| `Error.jsx` | Error page |
| `Dental/Test3DOdontogram.tsx` | 3D odontogram |
| `dashboard.jsx`, `auth/Login.jsx`, `Regester.jsx` | **0 bytes** |
| `patiants.jsx`, `operations.jsx`, `inventory.jsx`, `insurance.jsx`, `labs.jsx` | Placeholder shells |

Most product work happens in Filament, not these pages.

## 7. Back-end conventions

- Laravel 11 slim skeleton: no `app/Http/Kernel.php`; middleware is configured in `bootstrap/app.php`.
- Filament v3 resources are auto-discovered from `app/Filament/Resources`; the system panel from `app/Filament/System/Resources`. Navigation groups are declared in the panel provider.
- Policies are auto-discovered by naming convention (`App\Models\X` → `App\Policies\XPolicy`). `Gate::before` grants all abilities to `developer`/`super_admin`.
- Models use `$fillable` + `$casts`; several generate identifiers in `booted()`/`boot()`.
- Observers are registered explicitly in `AppServiceProvider`: `TreatmentProcedureObserver`, `AppointmentObserver`, `PracticeObserver`.
- Services live in `app/Services`, with widget services under `app/Services/Widgets`. Controllers stay thin; business rules are in services/models.
- Money is `decimal(10,2)`; use `brick/math` or integer minor units if you need exact arithmetic. The code currently uses floats in several places (`(float)` casts, `sum()`).
- Dates use Carbon; practice timezone is stored but not consistently applied.
- Localization strings are plain arrays in `lang/en`, `lang/ar`, `lang/fr` (check coverage before adding user-facing text).

### Adding a Filament resource

1. `php artisan make:filament-resource Foo --generate` (or hand-write under `app/Filament/Resources`).
2. Set `$navigationGroup` to one of the existing groups.
3. If the model has `practice_id`, ensure tenant scoping works (Filament does this for tenant-owned resources; verify with two practices).
4. Add a policy for non-super-admin roles.
5. Add the relation manager to the relevant parent resource if it is a child record.
6. Add a widget only if it is role-gated via `canView()`.

### Adding a feature flag

1. Add an entry to `FeatureManager::FEATURES` with `key`, `name`, `category`, `system_only`, `default`.
2. Gate routes with `->middleware('feature:key')`.
3. Gate Filament resources/pages with `FeatureManager::isEnabled()` or `->visible()`.
4. Update the React feature context if the page is Inertia-rendered.
5. Remember: clinic admins cannot toggle `system_only` features; only super admins/developers can.

### Adding a migration

Follow [DATA_MODEL §7](DATA_MODEL.md#7-adding-a-table-or-column). Key rules: add `practice_id` to new top-level tables, do not rely on implicit tenancy for clinical children, and review `down()` for data loss.

### Adding an API endpoint

- Put it in `routes/web.php` (there is no `routes/api.php`).
- Add `auth` middleware and an explicit tenant check. Do not copy the conditional `if (auth()->check() && practice_id)` pattern.
- Prefer a Form Request or inline `validate()` with explicit `in:` rules.
- Return a consistent JSON shape; use 201 for creates, 422 for validation, 403 for tenancy.
- If it mutates clinical data, run the odontogram compatibility sync and touch the patient.

## 8. Testing

### Commands

```bash
php artisan test                       # all suites (see caveat)
php artisan test --testsuite=Unit      # unit only (passes)
php artisan test --filter=TenantIsolationTest
vendor/bin/phpunit                     # bypasses artisan's memory handling
vendor/bin/pint --test                 # style check
vendor/bin/pint                        # auto-fix
```

### Current status (audit date)

- **Unit suite:** 16 tests, 68 assertions, passing.
- **`DashboardWidgetSystemTest`:** 3 failures. `Practice::create()` fires `PracticeObserver`, which seeds demo inventory into the test database. The low-stock assertion expects 1 item but sees 78. The tests are not isolated from observer side effects.
- **Full suite:** exhausts the 128 MB memory limit inside `DentalInventorySeeder` (~line 680). `php artisan test` appears to reset the limit even when invoked with `-d memory_limit=1G`; use `vendor/bin/phpunit` or add `<ini name="memory_limit" value="1G"/>` to `phpunit.xml`.
- PHPUnit 11 emits doc-comment metadata deprecation warnings; convert `@test` annotations to attributes or rename methods with the `test_` prefix.

### Recommended fixes before adding tests

1. Guard `PracticeObserver` with `if (app()->runningUnitTests()) return;` **or** use `Practice::withoutEvents()` in factories/tests. Better: replace the observer with an explicit onboarding/seed action so creating a practice is cheap and predictable.
2. Add a `PracticeFactory` that does not seed catalogs.
3. Keep tests on SQLite `:memory:` (already configured) and avoid MySQL-only SQL.
4. Add feature tests for the API auth boundary before changing those routes.

### Test map

| Test | Focus |
| --- | --- |
| `AnalyticsModuleTest` | Metrics and branch filtering |
| `ClinicalUpgradesAndVoiceNotesTest` | Exams, findings, voice notes, share tokens |
| `DashboardWidgetSystemTest` | Widget engine, finance/inventory widget services |
| `DetailedCalendarTest` | Calendar CRUD, conflicts, status |
| `EndToEndWorkflowAndSecurityTest` | Cross-module workflow + tenancy |
| `InventoryCostingAndAdjustmentTest` | Batch costing and stock movements |
| `NotificationsSystemTest` | Notification dedupe/channels |
| `PracticeFeatureFlagsAndOnboardingTest` | Feature toggles and onboarding |
| `RbacUserManagementTest` | Roles/policies |
| `SupportTicketModuleTest` | Tickets and replies |
| `TenantIsolationTest` | Cross-practice access |
| `TreatmentPlanInvoiceIntegrationTest` | Plan → invoice generation |
| `Unit/ToothRecordTest` | FDI validation/relations |
| `Unit/DoctorCommissionTest` | Commission math |
| `Unit/CurrencyHelperTest`, `Unit/LanguageHelperTest` | Helper behavior |

## 9. Common workflows

### Reset the local database

```bash
php artisan migrate:fresh          # no seed (fast)
php artisan migrate:fresh --seed   # full demo dataset (slow, memory-heavy)
```

### Clear caches after changing env/config

```bash
php artisan optimize:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear
```

### Run the license scheduler locally

```bash
php artisan license:tick
php artisan license:heartbeat
```

In local env the state is always active, so these mostly exercise the clock/file code.

### Migrate legacy odontogram data

```bash
php artisan odontogram:migrate-data
```

Copies `patient_teeth` rows into `dental_examinations`/`tooth_findings`. Run once after importing legacy data.

### Inspect the WhatsApp bot

```bash
php artisan tinker
>>> App\Models\Patient::whereNotNull('bot_state')->get(['id','phone','bot_state','bot_step','bot_data']);
```

To exercise the webhook locally, POST a fixture to `/api/whatsapp/webhook` (no signature needed). Remember it writes real patient/message rows.

## 10. Troubleshooting

| Symptom | Cause / fix |
| --- | --- |
| `/admin` returns 403 "license inactive" | `APP_ENV=production` with no `storage/app/license.enc`. Use `APP_ENV=local` for development, or implement activation. |
| `/admin` redirects to onboarding | Practice `onboarding_completed_at` is null. Complete `/onboarding` or set the column. |
| Login loops or 419 | Session driver/table mismatch; ensure `sessions` table exists and `SESSION_DRIVER=database` is migrated. Clear cookies. |
| `Vite manifest not found` | Run `npm run build` (production) or `npm run dev` (development). |
| 3D viewer blank | `3d_model` feature disabled, or GLB/Draco assets missing under `public/models` / `public/draco`. Check browser console and the `/patients/{id}/odontogram` route. |
| WhatsApp never sends | `WhatsAppService` is a stub; queue jobs call Evolution directly. Check `EVOLUTION_API_*`, queue worker, and `failed_jobs`. |
| Push notifications fail | VAPID keys missing; run `php artisan webpush:vapid` (if available) and add keys to `.env`. |
| Analytics branch filter returns zeros | Missing `patients.branch_id` (SQLite silently returns no rows). See [DATA_MODEL §6.1](DATA_MODEL.md#61-patientsbranch_id-does-not-exist). |
| Tests OOM | See §8; use `vendor/bin/phpunit` with a higher memory limit and isolate `PracticeObserver`. |
| `composer dev` fails | No `dev` artisan command. Run the individual processes. |
| Config cache breaks integrations | Runtime `env()` calls return null after `config:cache`. Move values into `config/*.php`. |

## 11. About the `agents/` directory

`agents/*.md` are **aspirational product/agent specifications**, not descriptions of implemented behavior. They mention Reverb/WebSockets, Framer Motion, Universal/FDI conversion, tiered commission rules, ETA e-invoicing, and drag-and-drop scheduling that are **not** in the codebase. Use them for domain vocabulary, but verify every claim against code before building on it.
