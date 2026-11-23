# DentalCare — Claude Code Development Guide

DentalCare (DOXamigo) is a multi-tenant Dental Clinic Management System built with Laravel 11, Filament v3 (admin back-office), and Inertia.js + React 19 (interactive chairside interface with 3D/2D Odontograms). It handles clinical odontogram/perio charting, patient treatment plans, chairside invoicing, InstaPay/cash installment plans, specialist commission splits, and automated WhatsApp communication.

## Exact Commands

```bash
# Dependency Installation
composer install
npm install

# Local Development Servers
php artisan serve                      # Start Laravel HTTP server (http://127.0.0.1:8000)
npm run dev                            # Start Vite React HMR bundler
php artisan queue:work                 # (Optional) Process background jobs/notifications

# Build
npm run build                          # Compile production frontend assets

# Code Style & Linting
vendor/bin/pint                        # Run Laravel Pint PHP code fixer
vendor/bin/pint --test                 # Check PHP code style without modifying

# Typechecking
npx tsc --noEmit                       # Validate TypeScript types (Dental3D components)

# Testing
php artisan test                       # Run complete PHPUnit test suite (in-memory SQLite)
php artisan test tests/Feature/EndToEndWorkflowAndSecurityTest.php  # Single feature test file
php artisan test tests/Unit/ToothRecordTest.php                      # Single unit test file
php artisan test --filter=workflow_01_login_to_dashboard            # Single test method
```

## Architecture & Directory Boundaries

```
DentalCare/
├── app/
│   ├── Filament/                      # Filament v3 tenant back-office (/admin/{tenant})
│   │   ├── Resources/                 # Patient, Appointment, Invoice, Installment, etc.
│   │   ├── Widgets/                   # DashboardCalendarWidget, LiveReceptionQueueWidget, etc.
│   │   └── System/                    # Super-Admin panel (/system) for practices & licenses
│   ├── Http/Controllers/              # Inertia page controllers and REST APIs
│   ├── Models/                        # Eloquent models (scoped to practice_id)
│   └── Services/                      # Domain services:
│       ├── FeatureManager.php         # Package tier enforcement (amigo, amigo_pro, amigo_ultra)
│       ├── LicenseManager.php         # Tamper-proof clock & offline grace period (bypassed in local)
│       ├── GeminiService.php          # AI NLP parsing for appointment requests (DO NOT BREAK)
│       ├── WhatsAppService.php        # Evolution API / Meta WhatsApp Cloud communication
│       ├── CurrencyHelper.php         # Multi-currency (EGP base, SAR, AED, USD, EUR)
│       └── Backup/                    # AES-256-CBC encrypted database & storage backup system
├── resources/
│   ├── js/
│   │   ├── Components/Dental3D/       # Three.js / React Three Fiber 3D Odontogram & raycasting
│   │   ├── Pages/                     # Inertia React chairside screens (operations, finance, etc.)
│   │   └── Layouts/                   # Chairside app shell and navigation
│   └── views/filament/                # Blade views, custom tabs, 360 medical history widgets
├── scratch/ & testing_scripts/        # Legacy / diagnostic scripts (DO NOT rely on for production)
└── tests/                             # Feature & Unit tests with strict tenant isolation
```

### Key Architectural Boundaries & Patterns
- **Multi-Tenancy:** `App\Models\Practice` is the tenant root (`Filament::tenant()`). All business data belongs to `practice_id`. Always enforce tenancy checks (`abort_unless($record->practice_id === auth()->user()->practice_id, 403)`).
- **Two Filament Panels:** `/admin/{tenant}` (`AdminPanelProvider`) for clinic staff; `/system` (`SystemPanelProvider`) for platform super admins.
- **Odontogram Sync:** Tooth procedure completion in treatment plans automatically creates/updates `patient_teeth` and `dental_examinations`/`tooth_findings` records.
- **Financial Integrity:** Invoices calculate balances automatically (`balance_due = total_amount - paid_amount`). Doctor commissions can link to `PayrollSlip` for combined payouts.
- **Code Preservation:** Do not delete or break working application code, especially third-party service wrappers (`GeminiService.php`, `WhatsAppService.php`).

## Local & Environment Setup

### Required Environment Variables (`.env`)
- `APP_KEY`: Base64 encryption key (required for session, backup, and license cryptography).
- `APP_ENV`: Set to `local` for local development. **Gotcha:** `LicenseManager` automatically bypasses hardware lockouts and remote heartbeat checks when `APP_ENV=local`.
- `DB_CONNECTION`: `sqlite` (default for local and tests) or `mysql`.
- `BACKUP_DELETE_PASSWORD`: Security password required before deleting any encrypted backup archive.
- `GEMINI_API_KEY`: Key for Gemini NLP parsing of patient appointment requests from text/voice notes.
- `WHATSAPP_API_URL` & `WHATSAPP_TOKEN`: Evolution API / Meta WhatsApp credentials.
- `VAPID_PUBLIC_KEY` & `VAPID_PRIVATE_KEY`: Web push notification keys.

### Local Services & Quick Access
- **Database:** SQLite is pre-configured with WAL mode (`busy_timeout: 5000`). For MySQL, use the provided `docker-compose.yml` (`clinic_db`, `clinic_redis`).
- **Default Seeded Admin:** `admin@dentalcare.com` / `password`.
- **Quick Dev Login:** Navigating to `/quick-login` in a browser logs in as the default admin and redirects to the patient dashboard.

## References
- `@AGENTS.md`: Original multi-agent architecture and domain agent specs.
- `.claude/rules/`: Modular, path-scoped domain guidelines for Claude Code:
  - `clinical-odontogram.md`: FDI/Universal tooth numbering, 5-surface mapping, periodontal probing.
  - `billing-finance.md`: Chairside invoicing, InstaPay receipts, tiered commissions.
  - `filament-resource.md`: Filament v3 panels, relation managers, role-scoped widgets.
  - `inertia-react.md`: Inertia React, Three.js 3D odontogram, touch ergonomics.
  - `engineering-standards.md`: TDD, tenant security, Spatie RBAC, GitHub issue tracking.
- `docs/status.md`: Detailed project status, completed features, in-progress items, and immediate roadmap.
- `docs/decisions.md`: Non-obvious architectural decisions, custom workarounds, and intentional patterns.
