# API reference

## 1. Conventions

- All API routes are declared in `routes/web.php`, not `routes/api.php`. There is **no `routes/api.php`** and no `api` route group.
- Consequence: every API request runs the `web` middleware stack (session, cookies, CSRF, Inertia sharing, locale). Clients must use a browser session or send the `XSRF-TOKEN`/`X-XSRF-TOKEN` pair, not a bearer token.
- Sanctum is installed (`laravel/sanctum ^4.0`) and its CSRF-cookie route is registered, but **no route uses `auth:sanctum` and no tokens are issued.**
- CSRF is disabled for **every** `api/*` path in `bootstrap/app.php`, not just the webhook. So unauthenticated POSTs work without a token if the route itself lacks `auth`.
- Responses are JSON (`shouldRenderJsonWhen` returns true for `api/*`). Validation errors are standard Laravel 422 JSON.
- Pagination is not used; list endpoints return all matching rows (calendar, treatment plans, notifications).
- There is no versioning, no OpenAPI schema, and no rate limiting.

Auth legend:

| Symbol | Meaning |
| --- | --- |
| **Session** | `auth` middleware; unauthenticated requests redirect to `/admin/login` (HTML) or 401 for JSON. |
| **Conditional** | No middleware. If a user is logged in *and* has a practice, cross-tenant checks run; otherwise the request proceeds anonymously. |
| **Public** | No auth at all. |
| **Tenant** | Session auth plus an explicit `practice_id` comparison (and sometimes a policy). |

## 2. Endpoint inventory

### 2.1 Authentication and onboarding

| Method | Path | Auth | Purpose |
| --- | --- | --- | --- |
| GET | `/login` | Public | Redirect to Filament admin login |
| GET | `/register`, `/auth/Register`, `/auth/Login` | Public | **Redirect to `/quick-login`** |
| GET | `/quick-login` | Public | **Logs in an existing user without credentials** |
| POST | `/admin/login` | Public | Filament login form |
| POST | `/admin/logout` | Session | Filament logout |
| GET | `/system/login` | Public | System panel login |
| GET | `/onboarding` | Session | Onboarding wizard |
| POST | `/onboarding/step` | Session | Save step `1..5` |
| POST | `/onboarding/complete` | Session | Finish onboarding |
| GET | `/locale/{lang}` | Public | Set locale (`en`, `ar`, `fr`) |
| POST | `/push/subscribe` | Session | Register a Web Push subscription |

### 2.2 Clinical

| Method | Path | Auth | Purpose |
| --- | --- | --- | --- |
| GET | `/patients/{patient?}/odontogram` | Public (feature `3d_model`) | 3D odontogram page; auto-logs in a fallback user |
| GET | `/patients/{patient?}/odontogram-details` | Public (feature `3d_model`) | Detailed odontogram page |
| POST | `/api/patients/{patient}/teeth` | Conditional | Upsert a tooth condition in `patient_teeth` + latest exam |
| GET | `/api/patients/{patient}/examinations` | Conditional | List examinations |
| POST | `/api/patients/{patient}/examinations` | Conditional | Create an examination (`type`: initial/periodic/emergency) |
| GET | `/api/examinations/{examination}/odontogram` | Conditional | Finding dictionary keyed by FDI number |
| POST | `/api/examinations/{examination}/findings` | Conditional | Upsert a tooth/surface finding |
| GET | `/api/patients/{patient}/treatment-plans` | Conditional | List plans with phases/procedures |
| POST | `/api/patients/{patient}/treatment-plans` | Conditional | Create a draft plan + phase 1 |
| GET | `/api/patients/{patient}/treatment-plans/active` | Conditional | Get or create the active draft plan |
| POST | `/api/treatment-plans/{plan}/procedures` | Conditional | Add a procedure by code |
| PATCH | `/api/procedures/{procedure}/complete` | Conditional | Complete a procedure + execution fields + odontogram sync |
| GET | `/prescriptions/{prescription}/print` | Conditional | Printable prescription |
| GET | `/voice-notes/stream/{share_token}` | Public (token) | Stream a voice note |
| GET | `/voice-notes/download/{share_token}` | Public (token) | Download a voice note |
| POST | `/api/patients/{patient}/voice-notes` | Session | Upload audio or create from a template |
| GET | `/api/patients/{patient}/voice-notes` | Session | List voice notes |
| GET | `/api/patients/{patient}/voice-notes/{voiceNote}/audio` | Session | Staff audio stream |
| POST | `/api/patients/{patient}/voice-notes/{voiceNote}/revoke` | Session | Revoke a share token |

### 2.3 Scheduling

| Method | Path | Auth | Purpose |
| --- | --- | --- | --- |
| GET | `/api/calendar/appointments?start=&end=&doctor_id=&operatory_id=&status=` | Session | Calendar data + filter options |
| POST | `/api/calendar/appointments` | Session | Create appointment with conflict check |
| PATCH | `/api/calendar/appointments/{appointment}/reschedule` | Session + tenant | Move appointment |
| PATCH | `/api/calendar/appointments/{appointment}/status` | Session + tenant | Change status |

### 2.4 Finance, analytics, operations

| Method | Path | Auth | Purpose |
| --- | --- | --- | --- |
| GET | `/api/analytics?start_date=&end_date=&branch_id=` | Session | Analytics metrics |
| GET | `/api/dashboard/widgets` | Session | Widget payloads |
| POST | `/api/dashboard/widgets/preferences` | Session | Save layout |
| GET | `/api/practice/features` | Session | Feature flag status |
| POST | `/api/practice/features` | Session + tenant | Update feature flags |
| GET | `/api/notifications` | Session | Notification list |
| POST | `/api/notifications/{id}/read` | Session | Mark one read |
| POST | `/api/notifications/read-all` | Session | Mark all read |
| GET | `/api/support/tickets` | Session | List own-practice tickets |
| POST | `/api/support/tickets` | Session | Create ticket |
| GET | `/api/support/tickets/{ticket}` | Session + tenant | Ticket + replies |
| POST | `/api/support/tickets/{ticket}/reply` | Session + tenant | Reply |
| PATCH | `/api/support/tickets/{ticket}/status` | Session + tenant | Change status/priority |

### 2.5 Integrations and internal

| Method | Path | Auth | Purpose |
| --- | --- | --- | --- |
| POST | `/api/whatsapp/webhook` | Public | Evolution API inbound message webhook |
| POST | `/api/internal/license/enroll` | Public | Mock central license enrollment |
| POST | `/api/internal/license/heartbeat` | Public | Mock central license heartbeat |
| GET | `/up` | Public | Health check |

## 3. Request/response examples

### 3.1 Create an examination

```http
POST /api/patients/42/examinations
Content-Type: application/json

{
  "type": "initial",
  "notes": "Full mouth exam"
}
```

```json
{
  "id": 17,
  "patient_id": 42,
  "doctor_id": 3,
  "type": "initial",
  "examined_at": "2026-10-26T10:00:00.000000Z",
  "notes": "Full mouth exam",
  "status": "completed",
  "created_at": "2026-10-26T10:00:00.000000Z",
  "updated_at": "2026-10-26T10:00:00.000000Z"
}
```

### 3.2 Upsert a finding

```http
POST /api/examinations/17/findings
Content-Type: application/json

{
  "tooth_number": "16",
  "condition": "crown",
  "notes": "Existing zirconia crown",
  "surfaces": { "occlusal": "crown", "buccal": "healthy" }
}
```

Returns the `ToothFinding` with `surface_findings`. Side effects: creates/updates `patient_teeth`, sets `examined_at` if empty, touches the patient.

### 3.3 Read the odontogram

```http
GET /api/examinations/17/odontogram
```

```json
{
  "16": { "condition": "crown", "notes": "Existing zirconia crown", "surfaces": { "occlusal": "crown", "buccal": "healthy" } },
  "36": { "condition": "missing", "notes": null, "surfaces": {} }
}
```

### 3.4 Add a treatment procedure

```http
POST /api/treatment-plans/9/procedures
Content-Type: application/json

{
  "tooth_number_fdi": 16,
  "surface": "WHOLE",
  "procedure_code": "D2740"
}
```

If `D2740` is not in `procedure_codes`, the controller creates a stub with fee `0.00` (and `category_id = 1`). The plan total is then recomputed from `sum(fee)`.

### 3.5 Complete a procedure

```http
PATCH /api/procedures/88/complete
Content-Type: application/json

{
  "anesthetic_agent": "Articaine 4%",
  "anesthetic_dosage": "1.0 carpule (1.8 ml)",
  "anesthetic_technique": "Infiltration (Buccal/Labial)",
  "tools_used_json": ["Rubber Dam (Medium/Heavy)"],
  "materials_used_json": ["Composite Shade A2"],
  "hemostasis_achieved": true,
  "chairside_notes": "No complications"
}
```

Side effects: sets `status=completed`, `completed_at`, upserts `patient_teeth` and the latest exam finding when `tooth_number_fdi` is present, touches the patient. The observer also attempts a post-op WhatsApp send (currently a no-op stub).

### 3.6 Create an appointment

```http
POST /api/calendar/appointments
Content-Type: application/json

{
  "patient_id": 42,
  "doctor_id": 3,
  "operatory_id": 1,
  "start_time": "2026-10-27T10:00:00",
  "end_time": "2026-10-27T10:45:00",
  "chief_complaint": "Pain upper right",
  "procedure_name": "Composite filling",
  "consultation_fee": 200,
  "status": "booked"
}
```

Validation enforces that patient, doctor, and operatory belong to the caller's practice, and rejects overlapping doctor/operatory slots with `422`. A successful create returns `201`. If `consultation_fee > 0`, `AppointmentObserver` also creates an invoice.

### 3.7 Calendar response

```json
{
  "appointments": [
    {
      "id": 101,
      "title": "Mona Ali - Composite filling",
      "patient_id": 42,
      "patient_name": "Mona Ali",
      "doctor_id": 3,
      "doctor_name": "Dr. Ahmed",
      "operatory_id": 1,
      "operatory_name": "Chair 1",
      "branch_id": 1,
      "start_time": "2026-10-27T10:00:00+02:00",
      "end_time": "2026-10-27T10:45:00+02:00",
      "status": "booked",
      "consultation_fee": 200,
      "chief_complaint": "Pain upper right",
      "procedure_name": "Composite filling",
      "notes": null
    }
  ],
  "doctors": [{ "id": 3, "name": "Dr. Ahmed", "role": "doctor" }],
  "operatories": [{ "id": 1, "name": "Chair 1", "branch_id": 1 }],
  "patients": [{ "id": 42, "first_name": "Mona", "last_name": "Ali", "phone": "01000000000" }],
  "statuses": ["booked", "arrived", "in_chair", "completed", "no_show", "cancelled"]
}
```

### 3.8 Analytics

```http
GET /api/analytics?start_date=2026-05-01&end_date=2026-10-26&branch_id=1
```

```json
{
  "practice_id": 1,
  "start_date": "2026-05-01",
  "end_date": "2026-10-26",
  "branch_id": 1,
  "metrics": {
    "gross_production": 125000,
    "collections": 98000,
    "outstanding_ar": 27000,
    "production_vs_collections": [],
    "no_show_rate": 4.2,
    "operatory_utilization": [],
    "top_procedures_by_volume": [],
    "top_procedures_by_revenue": [],
    "doctor_leaderboard": [],
    "patient_acquisition": []
  }
}
```

`branch_id` is currently unreliable because `AnalyticsService` filters on the non-existent `patients.branch_id` (see [DATA_MODEL §6.1](DATA_MODEL.md#61-patientsbranch_id-does-not-exist)).

### 3.9 Support ticket

```http
POST /api/support/tickets
Content-Type: application/json

{ "subject": "Printer not working", "message": "The prescription printer shows an error.", "priority": "high" }
```

```json
{
  "message": "Support ticket created successfully.",
  "ticket": {
    "id": 5,
    "ticket_number": "TICK-1A2B3C",
    "practice_id": 1,
    "user_id": 3,
    "subject": "Printer not working",
    "status": "open",
    "priority": "high"
  }
}
```

### 3.10 WhatsApp webhook

```http
POST /api/whatsapp/webhook
Content-Type: application/json

{
  "event": "messages.upsert",
  "instance": "clinic_1",
  "data": {
    "key": { "remoteJid": "201000000000@s.whatsapp.net", "fromMe": false, "id": "ABC123" },
    "message": { "conversation": "1" },
    "base64": null
  }
}
```

Returns `{"status":"success"}` for handled messages, or `{"status":"ignored","reason":"..."}` for unsupported events. There is no signature verification.

## 4. Authorization expectations

| Resource | Expected rule | Actual enforcement |
| --- | --- | --- |
| Clinical exam/odontogram/plan | Caller's `practice_id` equals patient's practice | **Conditional**: skipped when not authenticated |
| Appointment mutation | Tenant match | Explicit check on reschedule/status; create validates related IDs against practice |
| Invoice/patient/treatment plan policies | Role + tenant | Policies exist but most API routes do not call `authorize()` |
| Practice features | Clinic admin or super admin | `FeatureManager::updatePracticeFeatures()` checks practice and system-only flags |
| Support tickets | Own practice, staff replies for super admin | Explicit checks |
| Voice notes | Own practice patient | Explicit check; list/store require auth, but authorization is conditional on a practice id |
| Webhook | Shared secret/signature | **None** |
| License mock | Internal only | **None**, and publicly routable |

## 5. API design problems

1. **Unauthenticated clinical access.** The examination, odontogram, treatment-plan, and procedure-completion routes have no `auth` middleware. The `authorize*` helpers only run when `auth()->check() && auth()->user()->practice_id`. An anonymous request with a valid patient/exam ID can read or modify clinical data.
2. **Automatic login as a fallback user.** Odontogram page routes call `auth()->login()` using `admin@clinic.com` (or the first user) when unauthenticated. They can also create a demo patient. See [SECURITY](SECURITY.md#c2--silent-admin-login-in-the-odontogram-controller-critical).
3. **`/quick-login` is a credential-free login** and is the target of `/register`, `/auth/Login`, and `/auth/Register`.
4. **No token auth.** Sanctum is installed but unused; mobile/desktop clients cannot authenticate without a session. Either implement token auth (`auth:sanctum`) or remove Sanctum.
5. **CSRF disabled for all `api/*`.** This was probably meant only for the webhook. Any cookie-authenticated browser request can be forged cross-site.
6. **No rate limiting.** Login, webhook, license enrollment, and clinical writes are unthrottled.
7. **Mock license endpoints are public.** `POST /api/internal/license/enroll` accepts a hardcoded code (`CLINIC-XYZ-123`) and returns `active`. It should not exist outside local/testing.
8. **Tenant checks are inconsistent.** Some controllers compare `practice_id`, some use `abort_unless`, some rely on route-model binding, and some (analytics, widgets) fall back to practice `1` when no tenant is available.
9. **`procedure_code` auto-stubbing.** Unknown codes are created on the fly with fee `0`, which hides configuration errors and pollutes the catalog.
10. **No idempotency or ETags.** Completing a procedure twice re-runs side effects; retries are unsafe.
11. **No pagination or filtering contract.** Large practices will receive very large payloads from calendar, notifications, and patient lists (the calendar returns *all* patients for the filter dropdown).
12. **Webhook trust boundary.** Practice is derived from an instance-name regex and defaults to `1`; a crafted payload can write into another practice's messages/patients.
13. **Error shape varies.** Some endpoints return `{message}`, others abort with HTML-ish errors; JSON rendering is configured but not uniform.
14. **Versioning.** No `/api/v1`; breaking changes are unavoidable. Introduce a version prefix before external clients exist.
