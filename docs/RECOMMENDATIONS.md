# Recommendations and roadmap

This document turns the audit in [SECURITY.md](SECURITY.md), [ARCHITECTURE.md](ARCHITECTURE.md), [DATA_MODEL.md](DATA_MODEL.md), [API.md](API.md), and [DEVELOPMENT.md](DEVELOPMENT.md) into an ordered plan.

It is written for a small team (1–4 engineers) maintaining an on-premise clinical product. Estimates are rough engineer-days and assume one engineer familiar with Laravel; they are for sequencing, not for billing.

---

## 1. How to use this document

- **P0** — do before the next deployment, and before any new feature work. These are security, PHI, data-loss, or "the product does not actually work" issues.
- **P1** — do in the next 4–8 weeks. These are correctness, isolation, testability, and operability issues that will otherwise keep producing bugs.
- **P2** — do when the P0/P1 backlog is clear. These are product, UX, performance, and maintainability improvements.
- Each item has an **acceptance criterion** that can be verified. "Done" means the criterion passes in CI or in a manual checklist, not "the code was changed".
- IDs (`P0-1`, `P1-4`, …) are stable; reference them in commits and issues.

Priority is driven by four questions:

1. Can an unauthorized person read or change patient data?
2. Can a clinic lose or corrupt data?
3. Will a fresh installation work without an engineer on site?
4. Can the team change the code without breaking tenants or billing?

---

## 2. At a glance

| Phase | Theme | Items | Target |
| --- | --- | --- | --- |
| P0 | Stop the bleeding | 12 | 1–2 weeks |
| P1 | Make it safe to grow | 14 | 4–8 weeks |
| P2 | Make it good | 12 | ongoing |

If you only have **one day**, do P0-1 through P0-6. If you have **one week**, add P0-7 through P0-12 and the regression tests in section 6.

---

## 3. P0 — stop the line

### P0-1. Remove credential-free login paths

**Problem:** `GET /quick-login` logs anyone in as an admin; `/register`, `/auth/Register`, and `/auth/Login` redirect to it; `?redirect=` is an open redirect. `PatientOdontogramController::authorizePatientAccess()` also auto-logs anonymous visitors in as an admin. See [SECURITY.md](SECURITY.md#c1--credential-free-quick-login-critical) and [SECURITY.md](SECURITY.md#c2--silent-admin-login-in-the-odontogram-controller-critical).

**Actions:**

1. Delete the `/quick-login` route and the three redirects.
2. Delete the fallback block in `authorizePatientAccess()`.
3. Add `Auth::check()` / `auth` middleware and a policy to all odontogram routes.
4. Remove demo-patient creation from a request path; seeding belongs in seeders.
5. Add a route test that hits each path anonymously and asserts 302/401.

**Acceptance criteria:**

- `GET /quick-login` returns 404 in every environment.
- Anonymous `GET /patients/1/odontogram` and `POST /api/patients/1/teeth` return 401/302 and do not touch the database.
- No controller contains `auth()->login(` outside the login flow.
- `rg "quick-login|admin@clinic.com|doctor@dentalcare.com" app routes` returns only seeders and tests.

**Effort:** 0.5 day.

---

### P0-2. Close the unauthenticated clinical APIs

**Problem:** Examinations, findings, treatment plans, procedures, tooth updates, and procedure completion are registered outside `auth`. Their guards use `if (auth()->check() && auth()->user()->practice_id)`, which fails open for anonymous users and for users with `practice_id = null`. See [SECURITY.md](SECURITY.md#c3--unauthenticated-clinical-apis-critical).

**Actions:**

1. Wrap all clinical routes in `Route::middleware(['auth', 'feature:3d_model'])` (or the correct feature per route).
2. Replace conditional checks with policies:
   - `PatientPolicy@view` / `@update`
   - `TreatmentPlanPolicy@view` / `@update`
   - `DentalExaminationPolicy` (new)
3. Use route model binding scoped to the tenant, or check ownership before every write.
4. Make `practice_id = null` a denial (P0-8).
5. Add tests for anonymous, wrong-tenant, right-tenant, doctor, secretary, and practice-less users.

**Acceptance criteria:**

- Every route in the clinical group returns 401 anonymously.
- A practice A user receives 403/404 for a practice B patient on every route.
- The procedure-completion endpoint cannot mutate another tenant's procedure.
- `rg "auth\(\)->check\(\) &&" app` returns zero authorization guards.

**Effort:** 1–2 days.

---

### P0-3. Protect prescription printing

**Problem:** `/prescriptions/{prescription}/print` has no authentication and falls through when the user has no practice. Sequential IDs make enumeration trivial. See [SECURITY.md](SECURITY.md#c4--unauthenticated-prescription-printing-critical).

**Actions:**

1. Add `auth` middleware.
2. Authorize with `PrescriptionPolicy@view` (add the policy).
3. If prescriptions must be shared with patients, use `URL::temporarySignedRoute(...)` with a short expiry and a signed download controller.

**Acceptance criteria:**

- Anonymous request returns 401/302.
- A practice A user cannot print a practice B prescription.
- Generated patient links expire and cannot be replayed after expiry.

**Effort:** 0.5 day.

---

### P0-4. Authenticate and validate the WhatsApp webhook

**Problem:** The webhook is unauthenticated and unsigned, defaults the tenant to practice 1, logs full payloads, creates patients, changes bot state, triggers Gemini, and sends messages/notifications. See [SECURITY.md](SECURITY.md#c5--whatsapp-webhook-is-unauthenticated-and-untrusted-critical).

**Actions:**

1. Require a shared secret header (or HMAC) using `hash_equals`.
2. Resolve the practice from a real mapping; reject unknown/inactive practices. Do not default to 1.
3. Validate the payload shape before any write.
4. Add a unique index on `communication_logs.message_id` and ignore duplicates.
5. Remove PHI from logs.
6. Move the route to `routes/api.php` with `throttle`, or keep it in `web.php` but exempt only this route from CSRF and apply `throttle:60,1`.
7. Add a dead-letter/retry path for processing failures.

**Acceptance criteria:**

- A request without the secret returns 401/403 and writes nothing.
- An unknown instance is rejected.
- A repeated `key.id` does not create a second patient/log/message.
- Logs contain no phone number or message body.

**Effort:** 1 day.

---

### P0-5. Re-enable CSRF where it belongs

**Problem:** `bootstrap/app.php` exempts all `api/*` paths, and all API routes use session auth. See [SECURITY.md](SECURITY.md#c6--csrf-disabled-for-all-api-high).

**Actions:**

1. Remove the blanket exception.
2. Exempt only the third-party webhook (after P0-4) and any genuinely stateless endpoint.
3. If a token API is wanted, create `routes/api.php` with Sanctum and `auth:sanctum`; do not reuse the web session for it.
4. Add a test that posts to a representative mutating endpoint without a CSRF token and expects 419.

**Acceptance criteria:**

- A cross-site form POST to `/api/calendar/appointments` without a token returns 419.
- The webhook still accepts valid signed requests.
- Inertia pages continue to send `X-XSRF-TOKEN` transparently.

**Effort:** 0.5 day.

---

### P0-6. Remove public license mocks and fix license activation

**Problem:** The mock license endpoints are public and return `active`; there is no activation command or UI; the installer writes `license.json` while the manager reads `license.enc`; `/system` bypasses the license middleware. See [SECURITY.md](SECURITY.md#h3--m1--public-license-mock-endpoints) and [SECURITY.md](SECURITY.md#license-enforcement-gaps-h6).

**Actions:**

1. Remove the two `/api/internal/license/*` routes from production; register them only in `local`/`testing` if needed.
2. Add `php artisan license:activate {key}` and a small Filament activation page that call `LicenseManager::activate()`.
3. Make the installer call the command (or open the activation page) instead of writing `license.json`.
4. Apply `CheckLicenseStatus` to the system panel, or document why `/system` is exempt.
5. Replace the `APP_ENV=local` bypass with an explicit `LICENSE_BYPASS=true` flag restricted to local.
6. Stop reading/writing `practices.license_status` for enforcement, or make it the source of truth consistently.

**Acceptance criteria:**

- A fresh install reaches an activation screen and can activate a key.
- `storage/app/license.enc` is created; `license.json` is never read or written.
- `license:heartbeat` succeeds after activation.
- Suspended/revoked/tampered states lock the app; the activation screen remains reachable.
- `rg "api/internal/license" routes` is empty outside a local-only provider.

**Effort:** 2 days.

---

### P0-7. Add policies for every Filament resource

**Problem:** Filament allows actions when no policy exists. Any authenticated clinic user can reach payroll, staff, expenses, commissions, and insurance. See [SECURITY.md](SECURITY.md#h1--filament-resources-without-policies-high).

**Actions:**

1. Create policies for every model used by a resource:
   `PayrollSlip`, `StaffMember`, `ClinicExpense`, `DoctorCommission`, `InsuranceProvider`, `InsuranceClaim`, `PatientInsurancePolicy`, `Payment`, `InstallmentPlan`, `DentalLab`, `LabRequest`/`LabOrder`, `Operation`, `Appointment`, `Patient`, `Invoice`, `User`.
2. Define a role matrix first (who may view/create/update/delete), then implement it.
3. Add a test per resource for the most restricted role.
4. Add a CI check that fails when a resource model has no policy.

**Suggested matrix (adjust to the clinic's policy):**

| Resource | doctor | secretary | clinic_admin | super_admin |
| --- | --- | --- | --- | --- |
| Patients | view, update clinical | CRUD | CRUD | all |
| Appointments | view, update | CRUD | CRUD | all |
| Treatment plans | CRUD | view | CRUD | all |
| Invoices / payments | view | create/view | CRUD | all |
| Expenses / commissions / payroll / staff | none | none | CRUD | all |
| Insurance | view | CRUD | CRUD | all |
| Labs | CRUD | view | CRUD | all |
| Inventory | view | view | CRUD | all |

**Acceptance criteria:**

- A `doctor` gets 403 on payroll and staff list/create/edit pages.
- A `secretary` gets 403 on payroll and clinical finding edits.
- Every resource has a policy file and at least one negative test.
- Navigation hides forbidden resources (Filament does this automatically once policies exist).

**Effort:** 2–3 days.

---

### P0-8. Make tenancy fail closed

**Problem:** Checks of the form `if (auth()->check() && auth()->user()->practice_id)` allow users with `practice_id = null`; the dashboard falls back to practice 1; `FeatureManager` allows when no tenant is resolved. See [SECURITY.md](SECURITY.md#h7--the-practice_id--null-bypass-high).

**Actions:**

1. Add a `BelongsToPractice` trait with a global scope and an `assignPractice` creating hook.
2. Add a middleware or policy base class that denies tenant data when `practice_id` is null.
3. Replace the dashboard fallback `?? 1` with a 403/empty state.
4. Make `RequireFeature` deny when a tenant-scoped route cannot resolve a tenant.
5. Add tests for a user with no practice.

**Acceptance criteria:**

- A user without a practice sees no tenant data and receives 403/empty responses.
- No query in a tenant-scoped controller can return cross-practice rows.
- `rg "\?\? 1" app/Http/Controllers app/Services` returns only intentional global defaults.

**Effort:** 2 days.

---

### P0-9. Fix reschedule tenant validation

**Problem:** `CalendarController::reschedule()` validates `doctor_id`/`operatory_id` with `exists` but not practice ownership. See [SECURITY.md](SECURITY.md#h2--cross-tenant-doctor--operatory-injection-on-reschedule-high).

**Actions:** reuse the scoped lookups from `store()`; add a regression test.

**Acceptance criteria:** a foreign `doctor_id` or `operatory_id` returns 404/422 and changes nothing.

**Effort:** 0.25 day.

---

### P0-10. Move patient uploads off the public disk

**Problem:** Filament uploads default to the `public` disk, so X-rays, photos, and consent forms can be served without authentication. See [SECURITY.md](SECURITY.md#h5--patient-files-on-the-public-disk-high).

**Actions:**

1. Set `FILAMENT_FILESYSTEM_DISK=local` (and a private disk) in `.env`/compose.
2. Add authorized download routes that stream files through a policy check.
3. If any file is already public, migrate it and delete the public copies.
4. Add `public/storage` to `.gitignore` if it is not already.

**Acceptance criteria:**

- `/storage/...` no longer serves patient files.
- A user from another tenant cannot download a file by URL or ID.
- Development setup still works with authorized downloads.

**Effort:** 1 day.

---

### P0-11. Remove committed secrets and production seeding

**Problem:** The Tailscale key, `secret`/`root_secret` DB passwords, and demo seed run are in `deploy/install.ps1`. See [SECURITY.md](SECURITY.md#h4--installer-secrets-and-production-demo-data).

**Actions:**

1. Rotate the Tailscale key immediately; remove it from the script and from git history if the repo is shared.
2. Generate random DB passwords per install and write them to a generated `.env`.
3. Split seeders into production-safe and demo-only; make the installer run only the former.
4. Add a first-run admin creation step.
5. Stop tracking `deploy/DentalCare.exe`; build installers in CI.

**Acceptance criteria:**

- `rg "tskey-|DB_PASSWORD=secret|root_secret"` is empty.
- A fresh install has no `admin@clinic.com` / `password` account.
- The installer creates an admin with a unique password and forces a change.

**Effort:** 1 day (plus the Tailscale rotation, immediately).

---

### P0-12. Make the test suite deterministic and green

**Problem:** `PracticeObserver` seeds thousands of inventory rows whenever a test creates a `Practice`, causing `DashboardWidgetSystemTest` failures and an OOM in `DentalInventorySeeder` at the 128 MB default. See [DEVELOPMENT.md](DEVELOPMENT.md#8-testing).

**Actions:**

1. Do not run demo seeders from an observer. Move demo data to an explicit seeder/command, or guard the observer with `app()->runningUnitTests()`.
2. Reduce the seeder's default volume; make the full catalog opt-in.
3. Add `<ini name="memory_limit" value="1G"/>` to `phpunit.xml` as a temporary measure.
4. Fix or skip the three failing widget tests with a clear reason.
5. Add a smoke test that boots the app, runs migrations, and hits the login page.

**Acceptance criteria:**

- `php artisan test` completes under 512 MB in under 5 minutes with zero failures.
- Creating a `Practice` in a test creates only that practice (and whatever the test explicitly asks for).
- CI runs the suite on every push.

**Effort:** 1–2 days.

---

## 4. P1 — make it safe to grow

### P1-1. Introduce a real API layer

**Actions:**

- Create `routes/api.php`, register it in `bootstrap/app.php`, and move JSON endpoints there.
- Add Sanctum for token auth (mobile/integration) and keep session auth for first-party Inertia requests.
- Add Form Requests, API Resources, and a consistent error envelope (`{message, errors, code}`).
- Version the API (`/api/v1`).
- Add OpenAPI generation or a maintained route contract test.

**Why:** Today "API" means "web route that returns JSON". That conflates CSRF, sessions, and tokens and makes the auth matrix impossible to reason about. See [API.md](API.md#5-api-design-problems).

**Acceptance:** every JSON route is in `api.php` or explicitly documented as a web endpoint; the auth matrix in [API.md](API.md#4-authorization-expectations) matches a test.

**Effort:** 3–5 days.

---

### P1-2. Add audit logging and soft deletes

**Actions:**

- Add `owen-it/laravel-auditing` (or a small custom audit table) to patient, examination, tooth finding, treatment plan/procedure, invoice, payment, payroll, insurance, and user models.
- Add `SoftDeletes` and `deleted_by` to clinical and financial models.
- Add an admin-only audit viewer filterable by user, model, and date.
- Make audit rows append-only.

**Why:** No history exists today; disputes and insider misuse are untraceable. See [SECURITY.md](SECURITY.md#h8--no-audit-trail-or-soft-deletes).

**Acceptance:** editing a diagnosis writes an audit row with old/new values and the actor; deleting a patient is reversible; audit rows cannot be edited through the UI.

**Effort:** 3 days.

---

### P1-3. Fix the schema gaps that break features

**Actions:**

- Add `patients.branch_id` (nullable, indexed) or remove branch filtering from analytics. `AnalyticsService` currently queries a non-existent column. See [DATA_MODEL.md](DATA_MODEL.md#61-patientsbranch_id-does-not-exist).
- Add `doctor_commissions.practice_id` (or always scope through payments) and index it.
- Resolve `invoice_items`' two owner columns (`invoiceable_*` vs `treatment_procedure_id`) into one documented rule. See [DATA_MODEL.md](DATA_MODEL.md#62-invoice_items-has-two-competing-owner-columns).
- Add unique constraints where duplicates are possible (`patient_teeth` per patient+tooth, `tooth_records`, `communication_logs.message_id`).
- Add a migration test that runs `migrate:fresh` on both SQLite and MySQL.

**Why:** These are latent 500s and silent data corruption. See [DATA_MODEL.md](DATA_MODEL.md#6-schema-gaps-and-gotchas).

**Acceptance:** analytics works with and without a branch filter on SQLite and MySQL; duplicate tooth upserts are impossible; `migrate:fresh --seed` succeeds on both drivers.

**Effort:** 2–3 days.

---

### P1-4. De-duplicate the odontogram model

**Actions:**

- Pick one canonical representation for tooth state (recommend `tooth_findings` + `surface_findings`, with `patient_teeth` as a denormalized cache or drop it).
- Write a one-time migration/command to reconcile existing rows.
- Route all writes through a single service (`OdontogramService`) instead of three controllers.
- Keep the 3D viewer read model as a projection.

**Why:** `patient_teeth`, `tooth_records`, and `tooth_findings` are written in different places and can disagree. See [DATA_MODEL.md](DATA_MODEL.md#4-odontogram-data-duplication-important).

**Acceptance:** one write path; a reconciliation command reports zero drift; the frontend reads the same shape from both the page and the API.

**Effort:** 4–5 days.

---

### P1-5. Make observers idempotent and explicit

**Actions:**

- Replace hidden side effects in `AppointmentObserver` (automatic consultation invoice) and `TreatmentProcedureObserver` (invoice items) with explicit service calls or queued listeners.
- Make each observer safe to run twice (idempotency keys, `firstOrCreate`, status checks).
- Add tests that create an appointment twice/retry a job and assert one invoice.
- Remove demo seeding from `PracticeObserver` (P0-12).

**Why:** Billing created by an observer is hard to trace, retry-safe, or disable. See [ARCHITECTURE.md](ARCHITECTURE.md#observers-for-side-effects).

**Acceptance:** no duplicate invoices on retry; observer behaviour is documented and covered by tests.

**Effort:** 2–3 days.

---

### P1-6. Replace runtime `env()` with config

**Actions:**

- Add `config/whatsapp.php` (`base_url`, `api_key`, `webhook_secret`) and `config/services.php` Gemini entry.
- Replace all `env()` calls in `app/` with `config()`.
- Replace the hardcoded webhook URL `http://172.31.176.1:8000/...` with `route('whatsapp.webhook')` or a config value.
- Add `php artisan config:cache` to the deploy pipeline and a smoke test.

See [SECURITY.md](SECURITY.md#m3--runtime-env-calls).

**Acceptance:** `config:cache` + a WhatsApp send test works; `rg "env\(" app` returns zero.

**Effort:** 1 day.

---

### P1-7. Harden the web tier

**Actions:**

- Add TLS to nginx (or a reverse proxy) with HTTP→HTTPS redirect and HSTS.
- Add CSP, `X-Content-Type-Options`, `Referrer-Policy`, `Permissions-Policy`, and `X-Frame-Options`.
- Set `SESSION_ENCRYPT=true`, `SESSION_SECURE_COOKIE=true`.
- Add `USER www-data`, health checks, log rotation, and resource limits to the containers.
- Pin base image digests; use `npm ci`; drop Node from the runtime image.

See [SECURITY.md](SECURITY.md#8-infrastructure-and-deployment-security) and [DEPLOYMENT.md](DEPLOYMENT.md#6-tls-and-network).

**Acceptance:** an external scan shows HTTPS only, secure cookies, and the expected headers; containers run as non-root; `docker compose config` is clean.

**Effort:** 2 days.

---

### P1-8. Add operational observability

**Actions:**

- Structured logs with request ID, user ID, practice ID, route, status, duration.
- Error tracking (Sentry or a self-hosted alternative) with PHI scrubbing.
- Health endpoint that checks DB, Redis, queue, scheduler, and license state.
- Queue dashboards/alerts for failed WhatsApp jobs.
- A `license:status` command for support.

**Acceptance:** an operator can answer "is the queue running?", "when was the last heartbeat?", and "why did this request fail?" without SSH-ing into MySQL.

**Effort:** 2–3 days.

---

### P1-9. Automate backups and restore drills

**Actions:**

- Nightly encrypted MySQL dump + `storage/app` archive.
- Retention: 7 daily, 4 weekly, 12 monthly (adjust to law).
- Off-machine copy (clinic NAS or encrypted cloud bucket).
- Quarterly restore drill with a checklist and a timestamped result.
- Document RPO/RTO.

See [DEPLOYMENT.md](DEPLOYMENT.md#7-backups).

**Acceptance:** a restore into a clean host reproduces the clinic's data; the drill is recorded.

**Effort:** 1–2 days.

---

### P1-10. Add MFA and a password policy

**Actions:**

- Enable Filament MFA for `clinic_admin`, `super_admin`, `developer` (optionally all staff).
- Minimum length 12, breach-list check, no forced composition rules.
- Force a change for seeded/installer accounts.
- Rate-limit and alert on repeated failures.

**Acceptance:** privileged users cannot log in without a second factor; weak passwords are rejected; the policy is tested.

**Effort:** 2 days.

---

### P1-11. Finish or remove placeholder screens

**Actions:**

- Either implement or delete: `resources/js/pages/dashboard.jsx`, `auth/Login.jsx`, `Regester.jsx`, `patiants.jsx`, `operations.jsx`, `inventory.jsx`, `insurance.jsx`, `labs.jsx`, and `app/Filament/Pages/Settings.php`.
- The Inertia pages currently render empty or placeholder content; the real functionality lives in Filament. Decide which UI is canonical and remove the other to avoid duplicated, diverging work.
- Fix the `composer dev` script (it calls a non-existent `php artisan dev`).

**Why:** Empty pages erode trust and create false expectations. See [MODULES.md](MODULES.md#18-limitations-and-placeholders).

**Acceptance:** every route renders a real screen or returns 404; no empty component remains; `composer dev` works.

**Effort:** 3–5 days (or 0.5 day if deleting the Inertia duplicates).

---

### P1-12. Make the WhatsApp integration real

**Actions:**

- Replace the mock `WhatsAppService` with the Evolution API calls used by the jobs.
- Add an outbound message queue with retries and a dead-letter view.
- Add template management and opt-out handling.
- Add per-practice instance mapping (no `clinic_N` inference).
- Add delivery status callbacks and idempotency.

**Why:** The bot is a headline feature but is partly mocked and tenant-fragile. See [MODULES.md](MODULES.md#11-whatsapp-bot-messaging-voice-notes).

**Acceptance:** a message sent from the UI arrives on a real phone; failures are visible and retryable; a second practice cannot use the first practice's instance.

**Effort:** 4–6 days.

---

### P1-13. Add CI and a release gate

**Actions:**

- CI: `composer install`, `npm ci`, `npm run build`, `php artisan test`, `composer audit`, `npm audit`, `pint --test`, policy-coverage check, route-auth check, `config:cache` smoke test.
- Build and sign the Docker image; produce an SBOM.
- Tag releases; publish a changelog.
- Never ship a release with a failing test or an unauthenticated PHI route.

**Acceptance:** every merge runs the pipeline; releases are reproducible and traceable.

**Effort:** 2–3 days.

---

### P1-14. Document the operational contract

**Actions:**

- Finish the runbooks: install, upgrade, rollback, restore, rotate secrets, replace a failed node, re-activate a license, reset an admin password.
- Add an incident-response and breach-notification procedure.
- Add a `SECURITY.md` disclosure policy.
- Keep this documentation set current; add a docs check to PRs for behaviour changes.

**Acceptance:** a new operator can install, upgrade, and restore using only the docs; the security contact is monitored.

**Effort:** 2 days.

---

## 5. P2 — make it good

### P2-1. Improve clinical UX

- Real-time autosave indicators for the odontogram.
- Undo for tooth changes.
- Keyboard and screen-reader support in the 3D viewer (provide a 2D fallback table).
- Per-surface colour legend and a printable summary.
- Conflict warnings when two users edit the same examination.

**Why:** Clinical data entry is the highest-frequency, highest-risk interaction. **Effort:** 5–8 days.

### P2-2. Optimize the 3D assets

- `public/models` is ~263 MB; one GLB is 32 MB. Compress with Draco/meshopt, lazy-load per arch, and cache with hashed filenames.
- Add a low-bandwidth/2D mode.
- Serve via CDN or a static file server in production.

**Why:** First-load performance on clinic hardware is poor. **Effort:** 3–5 days.

### P2-3. Finish the analytics module

- Fix the branch dimension (P1-3).
- Add date-range presets, CSV export, and scheduled email reports.
- Add drill-down from a KPI to the underlying invoices/appointments.
- Cache expensive aggregates and move them to a nightly rollup table.

**Effort:** 5–7 days.

### P2-4. Improve billing correctness

- One source of truth for invoice totals (recalculate in a service, not in observers and controllers).
- Explicit void/refund flows with audit rows.
- Installment schedule generation with rounding rules and a reconciliation report.
- Commission rules configurable per doctor/procedure, with a month-close step.

**Why:** Money bugs destroy trust faster than UI bugs. **Effort:** 5–8 days.

### P2-5. Inventory and procurement

- Reorder-point alerts and purchase-order workflow.
- Barcode/QR scanning.
- Expiry tracking and batch recall.
- Supplier price history.

**Effort:** 5–8 days.

### P2-6. Insurance and claims

- Payer-specific fee schedules and coverage rules.
- Claim status lifecycle with remittance posting.
- Denial reasons and resubmission.
- Patient responsibility calculation shown before treatment.

**Effort:** 5–8 days.

### P2-7. Localization and accessibility

- Complete Arabic/French translations (including validation messages and Filament labels).
- RTL layout audit.
- WCAG 2.1 AA pass: focus states, contrast, labels, error announcements.
- Locale-aware dates, currencies, and phone numbers.

**Effort:** 4–6 days.

### P2-8. Mobile/PWA polish

- Offline shell for the schedule and patient lookup.
- Install prompts and version update flow.
- Push notification preferences per user and per event.
- Camera capture for documents with compression.

**Effort:** 4–6 days.

### P2-9. Performance

- Add indexes for the hottest queries (appointments by practice/date, invoices by practice/status/date, notifications by user/read_at).
- Eliminate N+1s in Filament tables and the dashboard widgets.
- Cache feature flags and practice settings per request.
- Add query-count and response-time budgets to CI.

**Effort:** 3–5 days.

### P2-10. Refactor toward services/actions

- Move business logic out of closures in `routes/web.php` and fat Filament resources into `app/Actions` or `app/Services`.
- Add Form Requests for every write endpoint.
- Add domain events (`AppointmentCompleted`, `ProcedureCompleted`, `PaymentRecorded`) and listeners.
- Keep controllers thin and testable.

**Why:** `routes/web.php` currently contains billing and clinical logic in closures; that is hard to test and reuse. **Effort:** 8–12 days, incremental.

### P2-11. Feature-flag hygiene

- Cache flags per practice.
- Add an audit row when a flag changes.
- Add a "why is this hidden?" tooltip for support.
- Make system-only flags impossible to toggle from the tenant API (already enforced in `FeatureManager`, add a test).

**Effort:** 1–2 days.

### P2-12. Product and support tooling

- In-app changelog and guided tours for new releases.
- Support ticket SLA timers and canned responses.
- Remote diagnostics bundle (logs, versions, license state) with PHI scrubbing.
- Tenant data export and deletion (GDPR-style requests).

**Effort:** 4–6 days.

---

## 6. Suggested sequencing

### Week 1 — security hotfix

| Day | Work |
| --- | --- |
| 1 | P0-1, P0-3, P0-9; rotate the Tailscale key |
| 2 | P0-2 (clinical routes + policies) |
| 3 | P0-4 (webhook), P0-5 (CSRF) |
| 4 | P0-7 (resource policies), P0-8 (fail-closed tenancy) |
| 5 | P0-6 (license), P0-10 (uploads), P0-11 (secrets/seeders) |
| 6–7 | P0-12 (tests), regression tests, deploy a release candidate |

### Weeks 2–4 — isolation and operability

- P1-1 API layer (start with the routes that matter).
- P1-2 audit/soft deletes.
- P1-3 schema fixes.
- P1-6 config, P1-7 TLS/headers, P1-9 backups.
- P1-13 CI.

### Weeks 5–8 — correctness and product

- P1-4 odontogram de-duplication.
- P1-5 observers.
- P1-8 observability.
- P1-10 MFA.
- P1-11 placeholder screens (decide the canonical UI).
- P1-12 WhatsApp integration.

### Quarter 2 — P2

Pick from P2 based on customer demand. P2-1 (clinical UX), P2-4 (billing), and P2-9 (performance) usually have the highest return.

---

## 7. Quick wins (under one day each)

1. Delete `check_user.php`, `debug.php`, and `debug2.php`; add them to `.gitignore`.
2. Remove `deploy/DentalCare.exe` from version control.
3. Add `SESSION_ENCRYPT=true` and `SESSION_SECURE_COOKIE=true` to the production env template.
4. Set `LOG_LEVEL=warning` in production.
5. Add `->disk('local')` to every `FileUpload` as an immediate stopgap (P0-10 is the real fix).
6. Add `->password()`/`revealable` review for secret fields; ensure license keys are not shown in tables.
7. Fix `composer dev`.
8. Add `<ini name="memory_limit" value="1G"/>` to `phpunit.xml`.
9. Add `noindex` to non-production deployments.
10. Add `APP_URL` validation to the installer; refuse to start with `APP_ENV=local` in the production compose.
11. Add a unique index on `communication_logs.message_id`.
12. Add a `license:status` artisan command.

---

## 8. Metrics for "are we safer?"

Track these monthly. They should move in one direction.

| Metric | Target |
| --- | --- |
| Unauthenticated routes returning PHI | 0 |
| Filament resources without a policy | 0 |
| Test suite runtime / failures | < 5 min, 0 failures |
| Routes covered by an auth test | 100% of mutating routes |
| Critical/high security findings open | 0 |
| Mean time to restore from backup (drill) | < 4 hours |
| Failed queue jobs older than 24h | 0 |
| Production `APP_DEBUG=true` | 0 |
| Secrets found by `gitleaks` | 0 |
| p95 page load on clinic hardware | < 2 s |

---

## 9. Trade-offs and decisions to make explicitly

These are product decisions, not purely technical ones. Write the answer down before starting the related work.

1. **One UI or two?** Filament and Inertia both exist. Maintaining both doubles the surface. Recommendation: Filament for staff administration, Inertia for the patient-facing/3D experience, and delete the duplicated placeholder pages.
2. **Session API or token API?** Recommendation: session for first-party Inertia, Sanctum tokens for integrations/mobile. Do not mix.
3. **Single-tenant install or multi-tenant SaaS?** The code supports multiple practices, but the installer is single-clinic. Decide whether `super_admin` is a real hosted role or a support account; the security model differs.
4. **Where does licensing live?** Local encrypted file + central heartbeat is a commercial control, not a security boundary. Do not rely on it to protect PHI. Make it reliable and supportable, not adversarial.
5. **What is the retention policy?** Clinical records often must be kept for years; "delete" may mean "soft delete + restricted access", not physical deletion. Encode this in the audit/soft-delete design.
6. **Who owns the on-prem host?** If the clinic owns it, the clinic can read the database. Encryption at rest protects against theft, not against the clinic itself. State this in the contract.
7. **How much demo data ships?** Recommendation: none in production; a separate `demo` command for sales and training.

---

## 10. Definition of done for this roadmap

The P0/P1 work is complete when:

- the security checklist in [SECURITY.md](SECURITY.md#11-verification-checklist) passes;
- a fresh install, an upgrade, and a restore have all been performed from the docs;
- the test suite is green and runs in CI on every push;
- every tenant-scoped route has an automated cross-tenant test;
- every Filament resource has a policy and a negative test;
- backups are automated, encrypted, and restore-tested;
- no secrets are in the repository;
- the team can explain, from the docs, how a request flows from browser to database and where authorization is enforced.

Until then, treat the product as **not ready for a new clinic** without an engineer performing the P0 checklist manually.
