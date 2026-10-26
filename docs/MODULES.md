# Modules

DentalCare has two user interfaces that share the same database and auth system:

1. **Filament admin panel** (`/admin/{practice}`) — the real product. Almost all CRUD, workflows, widgets, and tenant scoping live here.
2. **Inertia + React pages** (`/dashboard`, `/calendar`, `/finance`, `/analytics`, `/onboarding`, `/support`, `/…`) — a partially finished SPA. Several pages are empty or placeholders, and most navigation still points at Filament. Treat these as an in-progress second front end, not the primary one.

There is also a **system panel** (`/system`) for developers/super admins to manage practices and support tickets.

## 1. Roles and access

Roles come from Spatie Permission (`RoleSeeder`) and are also duplicated in `users.role` as a plain string. Policies and widgets mostly use the Spatie role.

| Role | Typical user | Panel | Main capabilities |
| --- | --- | --- | --- |
| `developer` | Vendor engineer | `/system` | Everything, including all tenants. No practice assigned. |
| `super_admin` | Vendor/owner admin | `/system` + `/admin` | Everything; can see all practices as tenants. |
| `clinic_admin` | Practice owner/manager | `/admin` | All practice modules, staff, finance, settings. |
| `doctor` | Dentist | `/admin` | Patients, clinical charts, treatment plans, own schedule/earnings/labs; cannot manage staff or full finance. |
| `secretary` | Receptionist | `/admin` | Patients, appointments, reception queue, basic billing. |

`Gate::before` in `AppServiceProvider` grants every ability to `developer` and `super_admin`. Everyone else is evaluated against policies.

**Important:** `users.role` (string) and Spatie roles can disagree. `User::canAccessPanel()` and most widgets use Spatie roles; some code (e.g. `FeatureManager`, seeders) checks `$user->role`. When adding a role check, use `hasRole()` and keep both columns in sync in the admin form.

## 2. Dashboard and widgets

`/admin/{practice}` renders role-aware Filament widgets discovered from `app/Filament/Widgets`:

| Widget | Visible to | Purpose |
| --- | --- | --- |
| `AdminExecutiveSummaryWidget` | admin, super, dev | Production, collections, AR, expenses |
| `AdminRevenueExpenseChartWidget` | admin, super, dev | Revenue vs expense trend |
| `AdminLabDebtsWidget` | admin, super, dev | Outstanding lab balances |
| `AdminTopTreatmentsWidget` | admin, super, dev | Top procedures by volume/revenue |
| `InventoryOverviewWidget` | admin, super, dev | Low stock, expiring, valuation |
| `DoctorCommissionsOverviewWidget` | admin, super, dev | Commission accruals |
| `ActionRequiredWidget`, `PendingTasksWidget`, `SecretaryStatsWidget`, `LiveReceptionQueueWidget` | secretary, admin | Front-desk work queue |
| `DoctorScheduleWidget`, `DoctorEarningsWidget`, `DoctorPendingLabsWidget` | doctor | Personal schedule, earnings, lab cases |
| `WhatsAppPendingRequestsWidget`, `FinanceOverviewWidget` | nobody (`canView` returns false) | Disabled/replaced |
| `TestPushNotificationWidget` | super, dev | Push test harness |

There is a second, parallel widget system for the React dashboard: `DashboardWidgetEngine` + `DashboardWidgetController` expose `gross_production`, `collections_this_month`, `outstanding_balance`, `doctor_commissions`, `low_stock`, `expiring_soon`, `consumption_rate`, `inventory_valuation`. User preferences are stored in `users.dashboard_widgets`. The React dashboard page itself is empty, so this API is currently unused by the UI.

## 3. Patients

**Filament:** `PatientResource` (Clinical Management group) with list/create/edit/view pages, plus relation managers for appointments, treatment plans, prescriptions, files, external treatments, and communications. The view page assembles several widgets:

- `PatientAlertWidget` — medical/allergy alerts.
- `PatientMedicalHistory360Widget` — the 360° clinical profile: medical history, odontogram, perio, treatment history. It writes to `patient_teeth` and is the largest custom widget in the app.
- `PatientFinanceWidget`, `PatientLabOrdersWidget`, `PatientFilesWidget`.

**Inertia:** `/patients` renders `patiants.jsx`, which is an empty shell. The real patient UI is Filament.

**Data:** `patients` + `medical_histories` (one-to-one). File numbers auto-generate as `PAT-{year}-{seq}`. `Patient::creating` only fills it when empty, so imports can supply their own.

**Workflows:**

1. Create patient (receptionist/admin) → optional medical history → optional insurance policy.
2. Add appointments, exams, files, prescriptions, treatment plans from the patient view.
3. Use the 360° widget to review risk flags before treatment.

**Limitations:**

- No soft deletes: deleting a patient cascades through clinical and financial records.
- `branch_id` is absent from `patients`; branch scoping must go through appointments.
- Patient merge/deduplication does not exist. `national_id` and `file_number` are not unique.
- The React patient page is a placeholder.

## 4. Appointments, calendar, operations

**Filament:** `AppointmentResource` covers booking and status changes. `OperationResource` is a filtered view of completed treatment procedures (the "operations log").

**Inertia:** `/calendar` is a working React calendar backed by `Api\CalendarController` (list, create, reschedule, status). `/operations` is a placeholder page.

**Data:** `appointments` requires practice, branch, operatory, patient, doctor, start and end. Statuses: booked → arrived → in_chair → completed, or no_show/cancelled. Supports `new_visit`/`follow_up` and self-referential `parent_appointment_id`.

**Business rules:**

- `AppointmentObserver` auto-creates an invoice when `consultation_fee > 0`, using an `invoiceable` invoice item. Editing the fee updates the invoice (or creates one if missing).
- Overlap detection is not enforced at the database level. The calendar controller may check conflicts; direct Filament edits can double-book an operatory or doctor.
- The reception queue widget uses today's appointments and statuses.

**Limitations:**

- Operatory is mandatory, so a clinic with no operatories cannot book. Onboarding creates at least one.
- Time zones: appointments are stored naive; the practice timezone is not applied per row.
- No recurring appointments, waitlist, or recall automation.

## 5. Examinations and odontogram

**Filament:** examination UI is embedded in the patient 360° widget and `DentalExamination`-backed APIs. There is no standalone `ExaminationResource`.

**Inertia/3D:** `/patients/{patient}/odontogram` and `/odontogram-details` render `Dental/Test3DOdontogram.tsx`; the 3D viewer loads GLB tooth models from `public/models` (large assets, ~263 MB total). `feature:3d_model` gates these routes.

**Data:** see [DATA_MODEL §4](DATA_MODEL.md#4-odontogram-data-duplication-important). Three representations coexist:

- `patient_teeth` (legacy, React + 360° widget).
- `tooth_records` / `surface_conditions` (structured, lightly used).
- `dental_examinations` / `tooth_findings` / `tooth_surface_findings` (visit snapshots, used by APIs).

**Workflow (new examination API):**

1. `POST /api/patients/{patient}/examinations` creates a visit header.
2. `POST /api/examinations/{examination}/findings` upserts a finding for a tooth/surface.
3. Completing a treatment procedure with a tooth number updates both `patient_teeth` and the latest `dental_examinations` finding (route closure in `routes/web.php`).
4. `php artisan odontogram:migrate` copies legacy `patient_teeth` rows into examinations once.

**Limitations:**

- No perio charting UI despite the `perio_chartings` table.
- The 3D viewer is asset-heavy and only works when `3d_model` is enabled.
- The APIs are not authenticated (see [SECURITY](SECURITY.md)); authorization is conditional on `auth()->check()`.
- Condition vocabularies differ between tables (`condition` strings vs `finding_type`).

## 6. Treatment planning

**Filament:** plans are managed from the patient relation manager; procedures are edited inline. There is no dedicated `TreatmentPlanResource`, so plan-level operations happen through patient pages and APIs.

**Inertia:** no dedicated page.

**Data:** plan → phases → procedures. Each procedure has a procedure code, optional tooth/surface, doctor, fee, discount, net, status, and execution fields (anesthetic, tools, materials, hemostasis, chairside notes).

**Workflows:**

1. Create a plan for the patient (doctor).
2. Add phases in sequence (urgent, restorative, prosthetic…).
3. Add procedures from the catalog with fees.
4. `recalculateTotals()` updates plan totals whenever procedures change (`TreatmentProcedureObserver`).
5. Mark procedures in progress/completed. Completing triggers the post-op WhatsApp logic (see §11) and the odontogram sync.
6. `InvoiceGenerationService::generateInvoiceFromPlan()` creates an invoice from selected billable procedures in a transaction, refusing procedures already invoiced.

**Limitations:**

- Approval workflow is just a status value; no signatures or patient acceptance capture.
- Plan totals ignore lab costs unless the caller subtracts them.
- No versioning: editing a plan changes history in place.

## 7. Billing, installments, commissions, expenses

**Filament resources (Finance & Treasury):**

- `InvoiceResource` — header, items relation manager, payments relation manager, finance stats widget. Actions include generating from a treatment plan and printing.
- `PaymentResource` — cash register; payment methods cash, InstaPay, card POS, insurance TPA, bank transfer.
- `InstallmentPlanResource` — financing with schedule relation manager.
- `DoctorCommissionResource` — accrual/settlement; bulk "settle" action.
- `ClinicExpenseResource` — expenses and overhead, with recurring metadata.

**Inertia:** `/finance` (feature `finance`) is a real React page showing recent invoices, payments, installment plans, commissions, expenses, and headline stats. It is read-mostly; CRUD is in Filament.

**Rules:**

- `Payment::saved` recalculates the invoice and updates the linked installment schedule.
- Invoice status derives from paid amount vs total.
- Commissions = `(gross − lab deduction) × percentage`, per payment/procedure.
- `AppointmentObserver` invoices consultation fees automatically.
- Invoice numbers are `INV-{year}-{seq}`; appointment invoices use `INV-{uniqid}` — two different formats coexist.

**Limitations:**

- No refunds/credit notes; deleting a payment recalculates but leaves no audit trail.
- Multi-currency: `CurrencyHelper` and practice currency exist, but invoices do not store currency or FX rate. Changing practice currency re-labels history.
- Expense receipts are URL strings, not managed uploads.
- Commission settlement does not create a payable/ledger entry.
- Invoice `terms_and_conditions` are free text.

## 8. Insurance and claims

**Filament resources (Insurance & Claims):** providers/TPAs, patient policies, claims.

**Inertia:** `/insurance` is a placeholder gated by the `insurance` feature flag.

**Workflow:** provider → patient policy (coverage percentages, maximums, deductibles) → claim (draft → submitted → under review → approved/denied/appealed) with invoice, doctor, estimated insurance, copay, actual paid, EOB reference.

**Limitations:**

- No EDI/837/835 generation or clearinghouse integration; statuses are manual.
- Claim numbers are auto-generated but there is no per-payer sequence.
- Policy annual accumulators (used vs remaining maximum) are not tracked; `annual_maximum` is static.
- Coverage calculation is not automated from procedure category.

## 9. Dental labs

**Filament resources (Lab & Prosthetics):** lab partners and lab orders, plus a lab-orders relation manager on labs and a stats widget. Status pipeline: impression sent → in production → shipped → received → delivered → fitted, with redo loop.

**Inertia:** `/labs` is a placeholder gated by `labs`.

**Workflow:**

1. Maintain lab directory (turnaround, pricing tier, courier, rating).
2. Create a case from a patient/treatment plan: tooth/teeth, shade system, material, impression type, scan URL, cost, patient charge, expected delivery.
3. Update status as the case moves; link a fitting appointment.
4. Record redo reason/count, QC, warranty, box number.
5. Lab debt widget and stats use outstanding `cost` vs `payment_status`.

**Limitations:**

- No lab portal or automated status updates; everything is manual.
- `tracking_number` is unique but generated from `max(id)`; concurrency risk.
- Costs are informational; they are not posted to `clinic_expenses` automatically.

## 10. Inventory and suppliers

**Filament resources (Clinic Operations):** `InventoryResource` with batch and movement relation managers and a stats widget; `SupplierResource` with an inventory-items relation manager.

**Inertia:** `/inventory` is a placeholder gated by `inventory`.

**Data:** item → batches → movements; `procedure_consumptions` links a procedure to a batch.

**Workflows:**

- Receive stock: create a batch with quantity and unit cost.
- Adjust stock: `InventoryStockService::adjustStock()` writes a movement row and updates the batch in a transaction.
- Consume materials during a procedure (table exists; UI usage is limited).
- Low-stock and expiring-soon widgets read batch sums.

**Limitations:**

- No purchase orders or goods-received notes; suppliers are informational.
- No FIFO/FEFO enforcement or cost posting to procedures.
- Stock is not branch-specific.
- `InventoryStockService` is the only safe write path; direct batch edits bypass the audit trail.

## 11. WhatsApp bot, messaging, voice notes

**Feature flag:** `whatsapp` (system-only, default off).

**Components:**

- `WhatsAppWebhookController` — receives Evolution API `messages.upsert` events, resolves the practice from the instance name `clinic_{id}` (defaults to `1`), finds/creates the patient, logs the message, and runs a state machine.
- `bot_state`: `menu`, `registering`, `booking`, `rescheduling`, `human`. Registration asks name then age; booking collects a preferred slot and creates a `whatsapp_appointment_requests` row; human mode stops automated replies.
- `WhatsAppService` — a **mock** that logs and returns `true`. It does not call the API.
- `SendWhatsAppMessageJob` / `SendWhatsAppListMessageJob` — queue jobs that call Evolution API directly.
- `GeminiService` — audio transcription/understanding for voice notes (requires `GEMINI_API_KEY`).
- `CommunicationLog` — inbound/outbound message history, shown in the patient relation manager.
- `WhatsAppSettings` page — connect/reset instance, show QR, register webhook. Not in navigation (`shouldRegisterNavigation = false`); reachable by URL.
- `VoiceNote` — staff-recorded audio, streamed via signed share tokens (`share_token`, expiry, revocation) for WhatsApp links.

**Limitations:**

- Webhook has no signature/shared-secret check; anyone who can reach it can inject messages.
- Practice resolution trusts the instance name; `clinic_1` is the default when the pattern does not match.
- The webhook URL is hardcoded to a developer WSL IP.
- `WhatsAppService` is a stub, so any code path that relies on it silently pretends to send.
- Voice-note transcription depends on an external key that is not in `.env.example`.

## 12. Notifications, PWA, localization

**Notifications:** Laravel database notifications (`notifications` table) plus Web Push (`push_subscriptions`). `NotificationService` provides deduplicated sends for appointment reminders, overdue invoices, low inventory, completed procedures, and system alerts. Polling is 3 seconds in the admin panel. `NotificationCenter.jsx` and `NotificationController` serve the React side. `TestPushNotificationWidget` is a developer harness.

**PWA:** `public/manifest.json`, service worker registration, VAPID keys (read from env; not in `.env.example`), and a push-subscription prompt rendered in the Filament body.

**Localization:** `en`, `ar`, `fr`. `SetLocale` middleware resolves session → user → practice → app locale. Filament has a language switcher; the React app uses `LocaleContext`. Arabic is RTL-aware in the React UI but translation coverage is partial. `LanguageHelper` lists options.

**Limitations:**

- VAPID keys missing from `.env.example`; push silently fails on fresh installs.
- No email/SMS channel despite `MAIL_*` config; WhatsApp is the only outbound channel.
- Notification preferences per user are minimal (only dashboard widget preferences).

## 13. Analytics

**Inertia:** `/analytics` is a real React page consuming `GET /api/analytics`.

**Service:** `AnalyticsService` computes gross production, collections, outstanding AR, production-vs-collections trend, no-show rate, operatory utilization, top procedures by volume/revenue, doctor leaderboard, and patient acquisition. All methods accept a date range and optionally a branch.

**Limitations:**

- Branch filtering references `patients.branch_id`, which does not exist. On SQLite this silently returns zero rows; on MySQL it errors. Branch analytics is effectively broken.
- "Production" is invoice total, not completed procedure value, so it can include planned work.
- No caching; each page load runs many aggregate queries.
- No export (CSV/PDF) for analytics.

## 14. Support tickets

**Filament (system panel):** `SupportTicketResource` for vendor staff.
**Inertia:** `/support` for clinic users, backed by `Api\SupportTicketController` (list, create, show, reply, status).
**Data:** ticket + replies (`is_staff_reply`).

**Limitations:**

- No attachments, SLA timers, or assignment.
- Status changes by clinic users are allowed; there is no policy separating staff and customer actions.
- `SupportTicket` tenant scoping is by `practice_id`; the system panel sees all.

## 15. Onboarding

**Inertia:** `/onboarding` wizard (`onboarding.jsx`, `OnboardingController`). Middleware `onboarding` forces incomplete practices there.

Steps:

1. Practice identity (name, tax ID).
2. Primary branch (name, code, address, phone).
3. Operatories (at least one).
4. Doctor profile (specialty, license, default commission).
5. Locale/currency/timezone.
6. Complete → sets `onboarding_completed_at`.

If a user has no practice, `show()` auto-creates "New Dental Clinic". `complete()` ensures a branch and at least one operatory exist.

**Limitations:**

- The wizard is only for the logged-in user's practice; it does not create additional branches after completion.
- `Practice::creating` marks practices as onboarded by default, so programmatically created practices skip the wizard unless the caller explicitly sets `onboarding_step = null` (the observer checks both fields).
- No invitation flow for staff; admins create users directly.

## 16. Licensing and the system panel

**Licensing:** `LicenseManager` + `TamperProofClock` + `HardwareFingerprint` + `license_clock_ticks`.

- State is stored in an AES-256-GCM encrypted file (`storage/app/license.enc`), including a hashed key.
- Clock ticks every 10 minutes (`license:tick`) and heartbeat every 4 hours (`license:heartbeat`).
- Grace: warning at 48 h offline, urgent at 66 h, lockout after 72 h.
- `APP_ENV=local` and `testing` bypass licensing entirely.
- `CheckLicenseStatus` middleware is attached to the `/admin` tenant middleware stack only.

**System panel (`/system`):**

- `PracticeResource` — create/edit practices, manage users per practice. Creating a practice triggers `PracticeObserver`, which seeds the full dental catalog (or eye catalog for `type = ophthalmology`).
- `SupportTicketResource` — all tickets.
- No license middleware, so the system panel remains reachable even when a tenant is locked out.

**Gaps:**

- Nothing calls `LicenseManager::activate()` — there is no UI or command to enter a license key. A production install will stay `inactive` and `/admin` will return 403.
- `deploy/install.ps1` writes a `license.json` that `LicenseManager` does not read (it reads `license.enc`).
- The license is machine-wide, not per-practice, despite `practices.license_key` existing.

## 17. Settings

- `app/Filament/Pages/Settings.php` is **0 bytes** — the Settings page is not implemented. The `Settings` navigation group exists.
- `WhatsAppSettings` (not in navigation) is functional.
- Practice identity, currency, locale, timezone, and prescription template are edited through onboarding or the system panel, not by clinic admins.
- User accounts and roles are managed in `UserResource` (Staff & HR).
- Doctor profiles are created via onboarding; there is no standalone doctor-profile resource.

## 18. Limitations and placeholders

These are the parts of the product that look finished from the navigation but are not. Treat this table as the honest feature-status list when demoing or planning work.

| Area | Current state | Impact | Fix |
| --- | --- | --- | --- |
| Inertia pages | `dashboard.jsx`, `auth/Login.jsx`, `Regester.jsx`, `patiants.jsx`, `operations.jsx`, `inventory.jsx`, `insurance.jsx`, `labs.jsx` are empty or placeholders | The React routes render blank/partial screens; the real workflows live in Filament | [P1-11](RECOMMENDATIONS.md#p1-11-finish-or-remove-placeholder-screens) |
| Settings | `app/Filament/Pages/Settings.php` is 0 bytes | No clinic-level settings page for admins | [P1-11](RECOMMENDATIONS.md#p1-11-finish-or-remove-placeholder-screens) |
| WhatsApp service | `WhatsAppService` is a mock; the webhook bot and the queue jobs are the real integration | Outbound sends depend on Evolution API configuration; some code paths are untested | [P1-12](RECOMMENDATIONS.md#p1-12-make-the-whatsapp-integration-real) |
| Analytics | Branch filter queries `patients.branch_id`, which does not exist | Zero/incorrect results with a branch selected | [P1-3](RECOMMENDATIONS.md#p1-3-fix-the-schema-gaps-that-break-features) |
| Licensing | No activation UI/command; installer writes the wrong file; `/system` is outside the license gate | A fresh production install cannot be activated through the product | [P0-6](RECOMMENDATIONS.md#p0-6-remove-public-license-mocks-and-fix-license-activation) |
| Test suite | Three widget tests fail; full suite OOMs at 128 MB | No trustworthy release gate | [P0-12](RECOMMENDATIONS.md#p0-12-make-the-test-suite-deterministic-and-green) |
| Dev script | `composer dev` runs `php artisan dev`, which does not exist | The documented convenience command fails | [P1-11](RECOMMENDATIONS.md#p1-11-finish-or-remove-placeholder-screens) |
| 3D assets | `public/models` is ~263 MB; one GLB is 32 MB | Slow first load on clinic hardware | [P2-2](RECOMMENDATIONS.md#p2-2-optimize-the-3d-assets) |
| HTTP API | No `routes/api.php`; Sanctum is installed but unused; JSON routes live in `web.php` | Session/CSRF/token concerns are conflated | [P1-1](RECOMMENDATIONS.md#p1-1-introduce-a-real-api-layer) |
| Production Docker | nginx listens on 80 only while compose publishes 443; no TLS | No HTTPS despite the exposed port | [P1-7](RECOMMENDATIONS.md#p1-7-harden-the-web-tier) |
| On-prem installer | No worker/scheduler services; seeds demo data; writes `license.json` | WhatsApp jobs/license ticks do not run; demo accounts in production | [P0-6](RECOMMENDATIONS.md#p0-6-remove-public-license-mocks-and-fix-license-activation), [P0-11](RECOMMENDATIONS.md#p0-11-remove-committed-secrets-and-production-seeding) |
| Post-op job | `SendPostOpCheckupJob` and one prescription view use `Practice::find(1)` | Wrong clinic branding for other practices | [P1-5](RECOMMENDATIONS.md#p1-5-make-observers-idempotent-and-explicit) |
| `agents/` | Markdown specifications, not implemented behaviour | A reader may mistake them for current requirements | Keep them clearly labelled as design notes |

