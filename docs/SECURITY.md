# Security review

**Audit date:** 2026-10-26
**Scope:** the Laravel application, routes, controllers, Filament panels, services, observers, deployment scripts, seeders, and Docker configuration in this repository.
**Method:** source review plus `php artisan route:list`, a test run, and targeted inspection of the framework packages that make authorization decisions.

This is a security review of the code as it exists, not a penetration test. No production system was tested. Findings are ranked by **exploitability × impact** in this application's context: a multi-tenant clinical system holding PHI, usually deployed on a clinic LAN or a single on-premise Windows machine.

> The single most important finding is that **several clinical endpoints are reachable without authentication**, and the application contains **credential-free login paths**. Until those are fixed, no other security control matters much.

---

## 1. Summary of findings

| ID | Severity | Finding | Primary location |
| --- | --- | --- | --- |
| C1 | **Critical** | `GET /quick-login` logs the visitor in as an administrator, then redirects to any URL | `routes/web.php` |
| C2 | **Critical** | Odontogram controller silently logs anonymous visitors in as an admin and creates demo patients | `app/Http/Controllers/PatientOdontogramController.php` |
| C3 | **Critical** | Clinical read/write APIs are unauthenticated; authorization checks are skipped when there is no authenticated user | `routes/web.php`, `DentalExaminationController`, `Api\TreatmentPlanController` |
| C4 | **Critical** | Prescription print route exposes PHI without authentication | `routes/web.php` (`prescriptions/{prescription}/print`) |
| C5 | **Critical** | WhatsApp webhook is unauthenticated, unsigned, defaults to practice 1, and writes data / sends messages | `WhatsAppWebhookController` |
| C6 | **High** | CSRF protection is disabled for every `api/*` route while those routes use session authentication | `bootstrap/app.php` |
| H1 | **High** | Most Filament resources have no policy; any authenticated clinic user can reach payroll, staff, expenses, commissions, and insurance | `app/Filament/Resources`, `app/Policies` |
| H2 | **High** | Appointment reschedule accepts a `doctor_id` / `operatory_id` from another tenant | `Api\CalendarController::reschedule()` |
| H3 | **High** | License mock endpoints are public and always answer "active" | `Api\CentralLicenseMockController` |
| H4 | **High** | Installer ships a hardcoded Tailscale auth key, default DB passwords, and seeds demo accounts into production | `deploy/install.ps1` |
| H5 | **High** | Patient files upload to the `public` disk by default | Filament `FileUpload` components |
| H6 | **High** | License enforcement has no activation path, is bypassed in `local`/`testing`, and the system panel is outside the license middleware | `LicenseManager`, `SystemPanelProvider` |
| H7 | **High** | "Authenticated but no `practice_id`" bypasses nearly every tenant check | Clinical APIs, voice notes, dashboard widgets |
| H8 | **High** | No audit trail, no soft deletes, no clinical-record change history | whole application |
| M1 | Medium | Public license mock endpoints expose a valid enrollment code and the licensing protocol | `Api\CentralLicenseMockController` |
| M2 | Medium | No TLS in the shipped nginx config; compose publishes 443 but nothing listens on it | `docker/nginx/default.conf`, compose files |
| M3 | Medium | Runtime `env()` calls break `config:cache` and can silently change security settings | `app/**` (13 calls) |
| M4 | Medium | No rate limiting on APIs or the webhook; login is only protected by Filament's built-in 5/minute throttle | routes, `app/Providers` |
| M5 | Medium | PHI and full WhatsApp payloads are written to application logs | `WhatsAppWebhookController::handle()` |
| M6 | Medium | No MFA, password policy, or forced rotation for staff accounts | Filament auth |
| M7 | Medium | Debug scripts and a prebuilt `.exe` are tracked in the repository | repository root, `deploy/DentalCare.exe` |
| L1 | Low | Session cookies are not `Secure` or encrypted by default | `config/session.php`, `.env.example` |
| L2 | Low | Missing security headers / CSP outside the installer-generated nginx config | `docker/nginx`, middleware |
| L3 | Low | Feature-flag middleware fails open when no tenant can be resolved | `RequireFeature` |

---

## 2. Trust boundaries and threat model

```
Browser (staff) ──HTTPS?──> nginx ──> Laravel (session auth)
                                      │
                                      ├─> MySQL (PHI)
                                      ├─> storage/app (files, license.enc)
                                      └─> WhatsApp / Gemini APIs
Browser (patient) ──token URL──> /voice-notes/stream/{token}

Evolution API ──unauthenticated POST──> /api/whatsapp/webhook
```

Assets, in order of sensitivity:

1. Patient identity, contact details, clinical findings, prescriptions, X-rays, and voice notes.
2. Billing, payroll, commissions, and insurance records.
3. Staff accounts and the ability to impersonate them.
4. The license/tamper-proof-clock state (commercial control, not PHI).

Realistic attackers:

- A patient or member of the public with the clinic URL (LAN or Tailscale).
- A curious or malicious staff member with a valid low-privilege account.
- A compromised or spoofed WhatsApp integration.
- Anyone who obtains the repository or the installer script (both contain secrets).

The application is **not** designed as an internet-facing SaaS. It is an on-premise product with a central license server. That lowers some exposure but does not reduce the impact of the findings below: clinics routinely expose these systems over Tailscale, port forwards, or public DNS.

---

## 3. Authentication and session security

### C1 — Credential-free `/quick-login` (Critical)

`routes/web.php` registers:

```php
Route::get('/quick-login', function (Request $request) {
    $user = User::where('email', 'admin@clinic.com')->first()
        ?? User::where('email', 'doctor@dentalcare.com')->first()
        ?? User::first();
    auth()->login($user);
    $request->session()->regenerate();
    return redirect($request->query('redirect', '/admin/1/patients/1'));
})->name('quick-login');
```

Consequences:

- Anyone who can reach the host becomes an administrator with one GET request.
- `/register`, `/auth/Register`, and `/auth/Login` redirect here, so the "registration" links are also backdoors.
- `?redirect=` is an **open redirect** after authentication, useful for phishing and token leakage.
- A route like this is trivially indexed by scanners and is present in the default build.

**Exploit:** `curl -c jar https://clinic.example/quick-login` then use the session cookie against `/admin`.

**Fix:**

1. Delete the route and all redirects to it.
2. If a demo shortcut is genuinely needed, gate it on `app()->environment('local')` **and** a non-default `DEMO_LOGIN_ENABLED` flag, never in production builds.
3. Validate redirects with a local-path allowlist (`url()->to()`, `parse_url($url, PHP_URL_HOST) === null`).
4. Search the repository history for the route; assume any deployed instance is compromised and rotate credentials.

### C2 — Silent admin login in the odontogram controller (Critical)

`PatientOdontogramController::authorizePatientAccess()`:

```php
if (!auth()->check()) {
    $fallbackUser = User::where('email', 'admin@clinic.com')->first()
        ?? User::where('email', 'doctor@dentalcare.com')->first()
        ?? User::first();
    if ($fallbackUser) {
        auth()->login($fallbackUser);
    }
}
```

Every odontogram entry point calls this method: `showTest()`, `showDetails()`, `updateTooth()`. An anonymous visitor therefore becomes the seeded admin and can read or mutate any patient's odontogram. If the admin account is absent, the first user in the table (often another admin) is used.

The same method also creates a "Demo Patient" when no patient is supplied, which pollutes real data.

**Fix:** remove the fallback entirely. Put the routes behind `auth` and a policy check. Let unauthenticated users get a 401/redirect, not an identity.

### Seeded and demo accounts (High)

`RoleSeeder`, `ClinicDataSeeder`, `MultiClinicSeeder`, `DentalFinanceSeeder`, `DentalLabSeeder`, and `DentalStaffAndInsuranceSeeder` create accounts with the password `password`, including `admin@clinic.com`, `super@clinic.com`, `dev@clinic.com`, `dr@clinic.com`, and multi-clinic super users.

`deploy/install.ps1` runs `php artisan db:seed --force` on the customer's machine. The result is a production installation whose well-known administrator password is `password`, combined with C1/C2 which do not even require it.

**Fix:**

- Do not run demo seeders in production. Split `DatabaseSeeder` into `ProductionSeeder` (roles, procedure catalog, currencies) and `DemoSeeder`.
- Create the first administrator through an installer prompt or `php artisan app:create-admin`, with a generated password printed once.
- Force a password change on first login.
- Add a `User::must_change_password` flag.

### Login rate limiting

Filament's login page calls `$this->rateLimit(5)` (5 attempts per 60 seconds, keyed by IP + component + method) and returns a generic failure message. That is adequate for the login form, but it is the **only** throttled entry point. `/quick-login` bypasses it entirely, and the API has no throttling.

### M4 — No API or webhook rate limiting

None of the JSON endpoints, the webhook, the voice-note upload, or the prescription print route has a `throttle` middleware. Consequences:

- An authenticated or anonymous client can enumerate patient/examination IDs as fast as the server allows.
- A valid webhook secret (once added) can still be brute-forced or replayed at volume.
- Voice-note uploads (10 MB each) can exhaust disk.
- Gemini and WhatsApp calls triggered by the bot can run up a bill.
- Credential stuffing against the API is not slowed down (the login form is, but the API is not).

**Fix:** apply `throttle:60,1` to general APIs, stricter limits to uploads and the webhook, and per-user/per-IP buckets via `RateLimiter::for()`. Return `429` with `Retry-After`.

### M6 — No MFA or password policy

There is no TOTP/WebAuthn, no minimum length, no breach-password check, and no rotation. For a system holding PHI this is a material gap, especially for `super_admin` and `developer` roles that bypass every policy via `Gate::before`.

**Recommendation:** add Filament MFA (v3 supports app-based MFA), at minimum for `super_admin`, `developer`, and `clinic_admin`.

### L1 — Session configuration

`.env.example` sets `SESSION_ENCRYPT=false` and does not set `SESSION_SECURE_COOKIE`. The defaults are `http_only=true`, `same_site=lax`, which is reasonable, but:

- Without HTTPS the cookie travels in clear text on the LAN.
- Server-side session files/database rows contain authenticated sessions and are not encrypted at rest.

**Fix:** `SESSION_ENCRYPT=true`, `SESSION_SECURE_COOKIE=true` in production, and terminate TLS before the app.

---

## 4. Authorization and multi-tenancy

The intended model is:

- `Practice` is the tenant. Most domain tables carry `practice_id`.
- `Branch` is a location inside a practice.
- Filament scopes queries to the current tenant; `User::canAccessTenant()` restricts users to their practice.
- API controllers compare `$patient->practice_id` with `$user->practice_id`.
- Policies cover some models; `Gate::before` grants everything to `super_admin` and `developer`.

The model is sound in intent but enforced inconsistently.

### C3 — Unauthenticated clinical APIs (Critical)

These routes are declared **outside** any `auth` middleware:

```php
Route::get ('/api/patients/{patient}/examinations', ...);
Route::post('/api/patients/{patient}/examinations', ...);
Route::get ('/api/examinations/{examination}/odontogram', ...);
Route::post('/api/examinations/{examination}/findings', ...);

Route::get ('/api/patients/{patient}/treatment-plans', ...);
Route::post('/api/patients/{patient}/treatment-plans', ...);
Route::get ('/api/patients/{patient}/treatment-plans/active', ...);
Route::post('/api/treatment-plans/{plan}/procedures', ...);
Route::patch('/api/procedures/{procedure}/complete', ...);

Route::get ('/patients/{patient?}/odontogram', ...);
Route::get ('/patients/{patient?}/odontogram-details', ...);
Route::post('/api/patients/{patient}/teeth', ...);
```

Their authorization helpers are written as:

```php
if (auth()->check() && auth()->user()->practice_id) {
    abort_unless($patient->practice_id === auth()->user()->practice_id, 403);
}
```

When nobody is logged in, the condition is false and the request proceeds. When a user is logged in but has `practice_id = null`, the request also proceeds. This is a **fail-open** pattern, not a fail-closed one.

**Impact:** an anonymous attacker can, by iterating sequential patient IDs:

- read every examination and tooth finding (`GET /api/patients/1/examinations`);
- read and create treatment plans and procedures;
- mark procedures complete, which mutates `patient_teeth` and `tooth_findings` and can trigger billing side effects;
- read the full odontogram page (which also auto-logs in as admin, see C2);
- modify tooth conditions.

The `/api/procedures/{procedure}/complete` closure is especially dangerous: it writes clinical findings and can create an examination. Its tenant check has the same `auth()->check() && practice_id` guard.

**Fix (in order):**

1. Move every clinical route into `Route::middleware(['auth', 'verified', 'feature:3d_model'])` (or the appropriate feature).
2. Replace conditional authorization with:
   ```php
   abort_unless(auth()->check(), 401);
   abort_unless($patient->practice_id === auth()->user()->practice_id, 403);
   ```
   Better: use a `PatientPolicy` / `TreatmentPlanPolicy` and `$this->authorize('view', $patient)`.
3. Add a global `practice_id` scope or a `BelongsToPractice` trait, and use route scoping (`->scopeBindings()`) so a tenant can never bind another tenant's model.
4. Add `FeatureManager` checks that **deny** when no tenant is resolvable instead of skipping (L3).

### H7 — The `practice_id = null` bypass (High)

Many checks are written as "if the user has a practice, compare". A user without a practice — for example `admin@dentalcare.com` / `doctor@dentalcare.com` from the seeders, or a super admin browsing outside a tenant — falls through every check.

Affected code includes:

- `DentalExaminationController::authorizePatient()` / `authorizeExamination()`
- `Api\TreatmentPlanController::authorizePatient()` / `authorizePlan()`
- `PatientVoiceNoteController::store()`, `staffStream()`, `revoke()`, `index()`
- `Prescriptions` print route
- `DashboardWidgetController` (falls back to practice `1`)

**Fix:** make the absence of a practice a hard denial for tenant-scoped data, and treat cross-practice access as super-admin-only through an explicit policy, not an implicit null check.

### L3 — Feature middleware fails open when no tenant is resolved

`RequireFeature` only checks the flag when it can resolve a tenant:

```php
$tenant = Filament::getTenant() ?? $request->user()?->practice;
if ($tenant && !FeatureManager::isEnabled($feature, $tenant)) { abort(403); }
```

For an anonymous request, `$tenant` is `null`, so the middleware does nothing. Because the odontogram routes are also unauthenticated (C3), `feature:3d_model` provides no protection there. Even for authenticated routes, a user without a practice skips the check.

**Fix:** deny when the route is tenant-scoped and no tenant can be resolved. Add a test that hits a feature-gated route anonymously and expects 401/403.

### H1 — Filament resources without policies (High)

Only ten policies exist:

```
Appointment, ExternalTreatment, InventoryItem, Inventory, Invoice,
LabOrder, Patient, Supplier, TreatmentPlan, User
```

The following resources' models have **no** policy:

- `PayrollSlipResource` (salaries)
- `StaffMemberResource` (HR records)
- `ClinicExpenseResource` (expenses)
- `DoctorCommissionResource` (doctor pay)
- `InsuranceProviderResource`, `InsuranceClaimResource`, `PatientInsurancePolicyResource`
- `PaymentResource`, `InstallmentPlanResource`
- `DentalLabResource`

Resources whose models **do** have a policy: `PatientResource` (`PatientPolicy`), `AppointmentResource` and `OperationResource` (`AppointmentPolicy`), `InventoryResource` (`InventoryItemPolicy`), `InvoiceResource` (`InvoicePolicy`), `LabOrderResource` (`LabOrderPolicy`), `SupplierResource` (`SupplierPolicy`), `UserResource` (`UserPolicy`), and `TreatmentPlanResource` (`TreatmentPlanPolicy`). `OperationResource` reuses the `Appointment` model, so it is covered by `AppointmentPolicy` even though no `OperationPolicy` exists.

Several of the unprotected resources define `canAccess()` only to check a feature flag (for example `PayrollSlipResource`), not a role. Feature flags do not substitute for authorization.

Filament's authorization helper allows the action when no policy or no matching method exists, after running `Gate::before` callbacks:

```php
// vendor/filament/filament/src/helpers.php
$policy = Gate::getPolicyFor($model);
if (($policy === null) || (! method_exists($policy, $action))) {
    $response = ...callBeforeCallbacks(...);
    if ($response === false) throw new AuthorizationException;
    if (! $response instanceof Response) return Response::allow();
    ...
}
```

`AppServiceProvider` only returns `true` for `developer` / `super_admin`; for everyone else the before callback returns `null`, which Filament interprets as **allow**.

Result: a `secretary` or `doctor` who can access the admin panel can open payroll, staff, expenses, commissions, and insurance resources. The tenant boundary still applies (they see their own practice), but role boundaries do not. Feature flags (`payroll`, `finance`, `insurance`, etc.) default to **true**, so they do not help.

**Fix:**

1. Create policies for every resource model, even if permissive, so the decision is explicit.
2. At minimum: payroll and staff → `clinic_admin` only; expenses/commissions → `clinic_admin`; insurance → `clinic_admin` or `secretary`; clinical → `doctor` and `clinic_admin`.
3. Add a regression test that asserts a `doctor` cannot view `PayrollSlipResource` and a `secretary` cannot edit expenses.
4. Consider `Resource::skipAuthorization(false)` as a project convention, and a CI check that every resource has a policy.

### H2 — Cross-tenant doctor / operatory injection on reschedule (High)

`Api\CalendarController::store()` correctly validates all references:

```php
$patient   = Patient::where('id', ...)->where('practice_id', $practiceId)->firstOrFail();
$doctor    = User::where('id', ...)->where('practice_id', $practiceId)->firstOrFail();
$operatory = Operatory::whereHas('branch', fn ($q) => $q->where('practice_id', $practiceId))->findOrFail(...);
```

`reschedule()` does not:

```php
$validated = $request->validate([
    'doctor_id'      => 'nullable|integer|exists:users,id',
    'operatory_id'   => 'nullable|integer|exists:operatories,id',
]);
```

`exists` only proves the row exists, not that it belongs to the caller's practice. A user can move their appointment onto another tenant's doctor or chair. This corrupts referential integrity and can leak the other tenant's schedule through the conflict check (the conflict query is scoped to the caller's practice, but the assigned foreign doctor will then appear in later queries and notifications).

**Fix:** validate with a tenant-scoped rule, or re-use the `store()` lookup:

```php
$doctor = User::where('id', $validated['doctor_id'])->where('practice_id', $practiceId)->firstOrFail();
$operatory = Operatory::whereHas('branch', fn ($q) => $q->where('practice_id', $practiceId))->findOrFail(...);
```

Add a feature test that reschedules with a foreign `doctor_id` and expects 403/404.

### C4 — Unauthenticated prescription printing (Critical)

```php
Route::get('/prescriptions/{prescription}/print', function (Prescription $prescription) {
    if (auth()->check() && auth()->user()->practice_id) {
        abort_unless($prescription->practice_id === auth()->user()->practice_id, 403);
    }
    return view('prescriptions.print', compact('prescription'));
});
```

No `auth` middleware. No signature. Sequential IDs. An anonymous visitor can enumerate prescriptions and read patient name, drugs, dosages, diagnosis, and clinic details. Even with `auth`, a user with `practice_id = null` bypasses the check (H7).

**Fix:** `Route::middleware('auth')`, then `$this->authorize('view', $prescription)`; alternatively generate a signed, expiring URL (`URL::temporarySignedRoute`) when sending a prescription to a patient.

### Authorization in the Filament panel

The panel-level controls are reasonable:

- `User::canAccessPanel()` restricts the `system` panel to `developer` / `super_admin`.
- `User::getTenants()` / `canAccessTenant()` limit normal users to their practice.
- `CheckLicenseStatus` is applied to tenant middleware in the admin panel.

Gaps:

- `PaymentResource` and `InstallmentPlanResource` rely on tenant scoping only.
- `PracticeResource` in the system panel lets a `super_admin` edit `license_status` and `license_key`, but those columns are **not read by `LicenseManager`** (see H6). This is misleading rather than exploitable.
- No policy prevents a `clinic_admin` from editing their own role or creating a `super_admin` through `UserResource` if the form allows it. Verify the role select is restricted; otherwise this is a privilege-escalation path.

### IDOR and missing ownership checks

- `PatientVoiceNoteController::revoke()` verifies the patient's practice but **not** that `$voiceNote->patient_id === $patient->id`, unlike `staffStream()`. A same-practice user can revoke another patient's link by ID. Low impact, easy fix.
- `PrescriptionsRelationManager` and `SendPostOpCheckupJob` use `Practice::find(1)` for branding, which shows the wrong clinic name when a patient belongs to another practice. Not a data leak, but a correctness/privacy signal.
- `DashboardWidgetController::index()` falls back to practice `1` when the user has no practice, exposing another tenant's metrics to a practice-less authenticated account.

---

## 5. CSRF, webhooks, and public endpoints

### C6 — CSRF disabled for all `api/*` (High)

`bootstrap/app.php`:

```php
$middleware->validateCsrfTokens(except: [
    'api/*',
]);
```

All "API" routes are actually registered in `routes/web.php` and use the `web` middleware group, so they authenticate with the session cookie. Disabling CSRF for the whole prefix means a malicious page can submit a form to any of these endpoints using the victim's cookies. Examples:

- `POST /api/calendar/appointments` (create an appointment)
- `PATCH /api/procedures/{procedure}/complete` (mutate clinical records)
- `POST /api/patients/{patient}/voice-notes` (upload content)
- `POST /api/practice/features` (change feature flags)
- `POST /api/notifications/read-all`

This compounds C3: several of these do not even need a session.

**Fix:**

1. Remove the blanket exemption. Exempt only the third-party webhook and the license mock, and only after adding signature verification.
2. If a stateless API is desired, build it properly: `routes/api.php`, Sanctum tokens, `auth:sanctum`, no cookies, rate limiting.
3. Add `SameSite=Lax` (already default) as defense in depth.

### C5 — WhatsApp webhook is unauthenticated and untrusted (Critical)

`POST /api/whatsapp/webhook` has no authentication, no shared secret, no HMAC signature, and no replay protection. It:

- logs the entire payload (`Log::info('WhatsApp Webhook Payload: ', $payload)`) including phone numbers and message text;
- derives the tenant from `preg_match('/clinic_(\d+)/', $payload['instance'])`, defaulting to `1`;
- creates a `Patient` record when the phone number is unknown;
- writes `CommunicationLog` rows;
- changes `bot_state` / `bot_step`;
- creates `WhatsAppAppointmentRequest` rows;
- calls Gemini (cost, rate limits) and dispatches outbound WhatsApp jobs;
- sends database notifications to every user in the practice.

An attacker can POST a crafted `messages.upsert` payload and inject patients, appointment requests, and notifications into practice 1, or use the endpoint as a free Gemini/WhatsApp relay. A spoofed `instance` selects any `clinic_N`.

**Fix:**

1. Require a shared secret header (e.g. `X-Webhook-Secret`) compared with `hash_equals`, or verify the Evolution API HMAC if available.
2. Reject requests whose `instance` does not map to a real, active practice; do not default to `1`.
3. Validate the payload shape (`event`, `data.key.remoteJid`, message type) before writing.
4. Add idempotency on `key.id` (unique index) to prevent duplicate processing and replay.
5. Move the route to `routes/api.php`, apply `throttle`, and omit the session middleware.
6. Stop logging full payloads; log only a message ID and practice ID.

### H3 / M1 — Public license mock endpoints

`Api\CentralLicenseMockController` exposes:

- `POST /api/internal/license/enroll` — returns `active` for the hardcoded code `CLINIC-XYZ-123`, echoing whatever `hardware_id` is supplied.
- `POST /api/internal/license/heartbeat` — always returns `active`.

These are described as "for local testing of Phase 1". They are registered in production routes and CSRF-exempt. They do not directly change local license state (the real manager talks to `config('license.server_url')`), but they:

- advertise the licensing protocol and a valid enrollment code;
- can be used to make a fake central server look healthy if DNS/hosts are manipulated;
- add unnecessary attack surface.

**Fix:** remove both routes, or register them only in `local`/`testing` via a service provider conditional. Never ship a hardcoded valid code.

---

## 6. Data protection and privacy

### H5 — Patient files on the public disk (High)

Filament's default upload disk is `public`:

```php
// vendor/filament/support/config/filament.php
'default_filesystem_disk' => env('FILAMENT_FILESYSTEM_DISK', 'public'),
```

`PatientResource\RelationManagers\FilesRelationManager` and `PatientFilesWidget` use `FileUpload` without specifying a disk, so X-rays, consent forms, and photos are written to `storage/app/public`. If `php artisan storage:link` has been run (the development guide recommends it), those files are served at `/storage/...` with no authentication. The path contains a random name, but URLs leak through browser history, referers, logs, and screenshots.

**Fix:**

1. Set `FILAMENT_FILESYSTEM_DISK=local` in `.env` and configure a private disk.
2. Serve downloads through a controller that authorizes `PatientPolicy@view` and streams the file (the voice-note controller is a good pattern).
3. Add `signed` URLs with short expiry if files must be shared externally.
4. Encrypt at rest (`Storage::disk('local')` + Laravel encryption, or full-disk encryption on the on-prem host).

### C4 / M5 — PHI in logs and printed documents

- Prescription printing is unauthenticated (C4).
- The WhatsApp webhook logs full inbound payloads, including phone numbers, names, and free-text clinical requests.
- Laravel's default `LOG_CHANNEL=stack` / `LOG_LEVEL=debug` in `.env.example` will record far more than necessary in development.
- Exception responses in non-local environments are rendered as a generic Inertia error page, which is good; but `APP_DEBUG=true` in any production `.env` would expose stack traces, env values, and SQL.

**Fix:** define a redaction policy for logs, set `LOG_LEVEL=warning` in production, never log message bodies, and add a log retention policy (see [DEPLOYMENT.md](DEPLOYMENT.md#7-backups)).

### H8 — No audit trail or soft deletes

There is no `owen-it/laravel-auditing`, no activity log, no `updated_by` columns, and no `SoftDeletes` on clinical or financial models (`grep SoftDeletes app/Models` returns nothing). Consequences:

- A deleted patient, invoice, or procedure is gone; there is no "who deleted it" record.
- A changed diagnosis or tooth finding cannot be traced to a user or time.
- Disputes (billing, clinical, insurance) cannot be reconstructed.
- An insider can alter financial or clinical records without evidence.

**Fix:**

1. Add `SoftDeletes` to patient, clinical, billing, payroll, and insurance models, with `deleted_by`.
2. Add an audit log table (`auditable_type/id`, `event`, `old_values`, `new_values`, `user_id`, `ip`, `user_agent`, `created_at`) and log create/update/delete on sensitive models.
3. Make audit rows append-only (no update/delete route, restrict DB grants).
4. Retain audit data according to local medical-record law (often 5–10 years).

### Encryption at rest

PHI is stored in plaintext in MySQL and in `storage/app`. The only encrypted file is `license.enc`. On a stolen laptop or an unencrypted backup, all patient data is readable.

**Recommendation:** enable full-disk encryption on the on-prem host, encrypt backups, and consider column-level encryption for especially sensitive fields (national ID, insurance policy numbers, voice notes).

---

## 7. Secrets and configuration

### H4 — Installer secrets and production demo data

`deploy/install.ps1` contains:

- a hardcoded Tailscale auth key: `tskey-auth-kVcjs8HMFF11CNTRL-qR4gR9vVE61PtrvHUFve61Sgzd84xs5XG`;
- `DB_PASSWORD=secret` and `MYSQL_ROOT_PASSWORD=root_secret` in the generated compose file;
- the public demo seed (`php artisan db:seed --force`) on first boot;
- an enrollment flow that writes `license.json` but never validates the code;
- no queue worker or scheduler container.

The Tailscale key is the most urgent: it grants network access to the clinic's machine. Rotate it in the Tailscale admin console now, then replace it with a per-installation key fetched from the central server or entered by the technician. Never commit auth keys.

**Fix:**

- Generate random DB passwords per installation and store them in the generated `.env` (not in the compose file).
- Do not seed demo data in production.
- Remove the hardcoded key and document a manual `tailscale up` step, or issue short-lived, tagged, pre-approved keys.
- Add `worker` and `scheduler` services to the installer compose, or document that licensing and WhatsApp jobs will not run.

### M3 — Runtime `env()` calls

`env()` is used outside `config/` in at least these places:

```
app/Filament/Pages/WhatsAppSettings.php
app/Http/Controllers/WhatsAppWebhookController.php
app/Jobs/SendWhatsAppListMessageJob.php
app/Jobs/SendWhatsAppMessageJob.php
app/Services/GeminiService.php
```

When `php artisan config:cache` is run, `env()` returns `null` outside config files. The WhatsApp integration and Gemini analysis will silently stop working in production (or fall back to the hardcoded `http://evolution_api:8080`). This is primarily a reliability issue, but it can also cause traffic to be sent to an unintended endpoint.

**Fix:** add `config/whatsapp.php` and `config/services.php` entries (`'gemini' => ['key' => env('GEMINI_API_KEY')]`), then read via `config()`. Run `php artisan config:cache` in CI to catch regressions.

### M7 — Debug scripts and tracked binary

The repository root contains:

- `check_user.php`
- `debug.php`
- `debug2.php`
- `deploy/DentalCare.exe` (a prebuilt Windows executable)

The PHP scripts bootstrap the application and print user/role/practice data. They are not under `public/`, so a correctly configured nginx will not serve them, but they are easy to expose with a misconfigured docroot or a developer's `php -S` and they normalize committing debug code. The `.exe` is opaque; shipping or executing an unverifiable binary is a supply-chain risk, and it bloats the repository.

**Fix:** delete the scripts and the `.exe`; add `debug*.php`, `check_*.php`, and `deploy/*.exe` to `.gitignore`; build installers in a controlled pipeline with provenance.

### `/system` is outside license enforcement (H6)

`SystemPanelProvider` does not apply `CheckLicenseStatus`. Only `AdminPanelProvider` does, via `tenantMiddleware`. A suspended installation can still reach `/system` if a `super_admin`/`developer` account exists, and can edit `license_status` (which does nothing).

**Fix:** put license enforcement in global middleware (after authentication) or in both panels, and make `LicenseManager` the single source of truth for license state.

### License enforcement gaps (H6)

- `LicenseManager::activate()` exists but has **no caller**: no Filament page, no artisan command, no installer invocation. `grep -rn "activate" app routes` finds only the method definition.
- `deploy/install.ps1` writes `storage/app/license.json`, while `LicenseManager` reads `storage/app/license.enc`. A fresh install therefore has **no license**, and `CheckLicenseStatus` locks the admin panel.
- `license:heartbeat` will fail with "No license installed" on a fresh install.
- `getLicenseState()` returns `active` whenever `APP_ENV=local`, and `isLockedOut()` also bypasses for `local`/`testing`. A misconfigured production `.env` disables licensing entirely.
- The system panel can set `practices.license_status`, but no middleware reads it. `Practice::isLicenseActive()` exists and is unused for enforcement.
- The license key is hashed locally (`Hash::make`), so the server can never re-verify the original key; activation and heartbeat rely on stored fingerprint/status only.

**Fix:**

1. Add a small activation screen (system panel or first-run wizard) and an artisan command (`license:activate {key}`) that calls `LicenseManager::activate()`.
2. Make the installer call that command and write `license.enc`, or remove the installer's fake enrollment.
3. Enforce licensing in global middleware; keep the local bypass but require `app()->environment('local')` **and** an explicit `LICENSE_BYPASS=true`.
4. Use a real central server endpoint for enrollment; do not accept any code locally.
5. Add tests for inactive, grace, suspended, revoked, and tampered states.

---

## 8. Infrastructure and deployment security

### M2 — No TLS in the shipped nginx

`docker/nginx/default.conf` listens on port 80 only. Both compose files publish `443:443`, but no certificate, no `listen 443 ssl`, and no HSTS. The Windows installer also publishes only `80:80`. Traffic between browser and server is plaintext on the LAN, and over Tailscale it is encrypted by the tunnel but still not end-to-end HTTPS at the application layer.

**Fix:** terminate TLS at nginx (or a reverse proxy), redirect HTTP to HTTPS, set `Strict-Transport-Security`, and set `APP_URL=https://...` (the app already forces HTTPS URLs when `APP_URL` starts with `https://`).

### L2 — Missing security headers and CSP

The Docker nginx config sets no security headers. The installer-generated config sets only `X-Frame-Options` and `X-Content-Type-Options`. There is no Content-Security-Policy, `Referrer-Policy`, `Permissions-Policy`, or HSTS. Inertia pages load third-party scripts (Vite dev server in development, bundled assets in production) and the 3D viewer loads large model files; a CSP would meaningfully reduce XSS impact.

**Fix:** add headers at nginx or in a global middleware, start with a report-only CSP, then enforce. Include `frame-ancestors 'none'`, `object-src 'none'`, and an explicit script/style allowlist.

### Container and host hardening

- The production Dockerfile runs `php-fpm` as root by default. Add `USER www-data` or configure the FPM pool to drop privileges.
- The Dockerfile installs Node and `npm install` in the build stage, which bloats the image and pins no integrity beyond `package-lock.json`. Use `npm ci` and a multi-stage build that discards Node from the runtime image.
- The `app` service mounts the entire repository (`.:/var/www/html`) in `docker-compose.yml`, so local edits are live and `.env` is exposed inside the container. The production compose uses a named volume, which is better; verify the deployed image does not include `.env`.
- MySQL and Redis are not exposed to the host in `docker-compose.prod.yml` (good). Keep it that way; do not add `ports:` for them.
- Redis has no password. It is only reachable on the internal network, but any compromised container can read sessions/queues.
- No resource limits, health checks, or log rotation are defined.

### Backups

No automated backup job exists. The deployment guide documents manual `mysqldump` and volume copies. For a clinical system, define:

- nightly encrypted database dumps with 30/90/365-day retention;
- `storage/app` backup (files, voice notes, license state);
- restore drills;
- offsite or at least off-machine copies;
- a documented retention/deletion policy consistent with local law.

### Dependency and supply chain

- `composer.lock` and `package-lock.json` are present (good). Add `composer audit` / `npm audit` to CI.
- `deploy/DentalCare.exe` is unverifiable (M7).
- The base images are floating tags (`php:8.3-fpm-alpine`, `mysql:8.0`, `redis:7-alpine`, `nginx:alpine`). Pin digests for reproducible, auditable builds.
- No CI configuration was found; there is no automated security or test gate.

---

## 9. What is done well

These controls are worth preserving:

- Filament's login page is rate-limited (5 attempts/minute) and returns a generic failure message.
- Tenant scoping in Filament (`getTenants`, `canAccessTenant`) and `Practice`-based query filters in most API controllers.
- `Gate::before` is explicit about the two god-mode roles; it does not accidentally grant to everyone.
- Voice-note sharing uses a 64-character random token, expiry, and revocation, and the staff stream verifies `voiceNote->patient_id === patient->id`.
- Passwords are hashed with bcrypt; no plaintext passwords are stored.
- Eloquent/query builder is used throughout; no obvious raw SQL injection was found.
- React/Inertia escapes output by default; no `dangerouslySetInnerHTML` was found in the audited pages.
- Non-local exception responses are rendered as a generic error page, limiting stack-trace leakage.
- `APP_DEBUG` defaults to `false` in `config/app.php`, and the production compose sets it explicitly.
- Some controllers (`CalendarController::store`, `SupportTicketController`) do enforce tenant ownership correctly; they are good templates for the rest.

---

## 10. Remediation plan

### Stop the line (do before any deployment)

1. Remove `/quick-login`, the `/register` redirects, and the auto-login fallback in `PatientOdontogramController`.
2. Put `auth` + tenant authorization on every clinical route; replace all `if (auth()->check() && practice_id)` guards with fail-closed policy checks.
3. Add authentication and a signed URL to the prescription print route.
4. Add a shared secret/signature and strict practice resolution to the WhatsApp webhook; stop logging payloads.
5. Remove the public license mock endpoints.
6. Remove the blanket `api/*` CSRF exemption; scope it to the webhook only after signing.
7. Rotate the Tailscale key, remove it from the repo, and replace `secret`/`root_secret` with generated credentials.
8. Stop seeding demo accounts in production; create the first admin securely.

### Within the first hardening sprint

9. Add policies for every Filament resource, starting with payroll, staff, expenses, commissions, and insurance.
10. Fix `CalendarController::reschedule()` tenant validation.
11. Move patient uploads to a private disk and serve them through an authorized controller.
12. Make `practice_id = null` a denial for tenant-scoped data.
13. Add TLS, secure cookies, and HTTPS redirects; set `SESSION_ENCRYPT=true`.
14. Add audit logging and soft deletes to clinical/financial models.
15. Wire license activation and enforcement into the installer, system panel, and global middleware.

### Next

16. Add rate limiting to APIs and the webhook.
17. Add MFA for privileged roles and a password policy.
18. Replace runtime `env()` calls with config entries and test `config:cache`.
19. Add security headers (CSP, HSTS, `X-Content-Type-Options`, `Referrer-Policy`, `Permissions-Policy`).
20. Add CI gates: tests, `composer audit`, `npm audit`, a policy-coverage check, and a route-auth check.
21. Document an incident-response and breach-notification procedure.

A sequenced, estimate-bearing version of this plan is in [RECOMMENDATIONS.md](RECOMMENDATIONS.md).

---

## 11. Verification checklist

Use this after remediation. Each item should be a test or a repeatable command, not a manual impression.

### Authentication

- [ ] `GET /quick-login`, `/register`, `/auth/Login`, `/auth/Register` return 404 or redirect to the real login.
- [ ] An anonymous request to every clinical route returns 401/302, not data.
- [ ] An anonymous request to `/prescriptions/1/print` returns 401/302.
- [ ] A user without a `practice_id` cannot read or mutate tenant data.
- [ ] Login is throttled; repeated failures do not reveal whether the email exists.

### Authorization

- [ ] A `doctor` cannot open payroll, staff, expenses, commissions, or insurance resources.
- [ ] A `secretary` cannot edit clinical findings or payroll.
- [ ] A `clinic_admin` of practice A cannot read or write any practice B record by ID.
- [ ] `PATCH /api/calendar/appointments/{id}/reschedule` with a foreign `doctor_id`/`operatory_id` is rejected.
- [ ] Every Filament resource has a policy and a test for at least `viewAny` and `update`.

### Webhooks and integrations

- [ ] `POST /api/whatsapp/webhook` without the secret returns 401/403.
- [ ] A replayed payload with the same `key.id` is ignored.
- [ ] An `instance` that does not map to an active practice is rejected.
- [ ] Webhook logs contain no phone numbers or message bodies.

### Licensing

- [ ] A fresh install can activate a key and write `storage/app/license.enc`.
- [ ] `license:heartbeat` succeeds after activation.
- [ ] Suspended/revoked/tampered states lock the admin panel but allow the activation screen.
- [ ] `/system` is subject to the same license gate (or explicitly exempt with a documented reason).
- [ ] `APP_ENV=production` with `LICENSE_BYPASS` unset cannot bypass the license.

### Data protection

- [ ] Patient files are not reachable at `/storage/...` without authorization.
- [ ] Backups are encrypted and a restore has been tested.
- [ ] Audit rows exist for create/update/delete on patient, invoice, payment, and procedure models.
- [ ] Production logs contain no PHI and `APP_DEBUG=false`.

### Infrastructure

- [ ] HTTP redirects to HTTPS; HSTS is present.
- [ ] Session cookies are `Secure`, `HttpOnly`, and `SameSite=Lax` (or `Strict`).
- [ ] Database and Redis are not published to the host.
- [ ] The Tailscale key in `deploy/install.ps1` is gone and has been rotated.
- [ ] `php artisan config:cache` does not break WhatsApp/Gemini configuration.

---

## 12. Reporting a vulnerability

There is no `SECURITY.md` disclosure policy, no security contact, and no private reporting channel in the repository. Before distributing the product, add:

- a `SECURITY.md` with a monitored contact and a response target;
- a private issue tracker or email alias;
- a policy for handling PHI in reports (do not attach real patient data);
- a breach-notification procedure that satisfies the jurisdictions where the product is sold.
