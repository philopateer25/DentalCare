# DentalCare Project Status & Roadmap

*Current Status Snapshot: November 2026*

---

## 1. Complete & Working Features

### Multi-Tenant Practice Architecture
- **Tenant Isolation:** `Practice` serves as the tenant root (`Filament::tenant(\App\Models\Practice::class)`). All models (Branches, Operatories, Staff, Patients, Invoices, Payments, Expenses, Inventory, Lab Orders) enforce strict `practice_id` scoping.
- **Dual Panel Setup:**
  - `/admin/{tenant}`: Clinic back-office powered by `AdminPanelProvider` for clinic managers, doctors, receptionists, and accountants.
  - `/system`: Super-Admin control center powered by `SystemPanelProvider` for practice provisioning, package limits, and license monitoring.
- **Spatie RBAC Guardrails:** Role-based permissions (`super_admin`, `clinic_admin`, `doctor`, `receptionist`, `senior_consultant`, `accountant`) enforced in policies and UI menus.

### Clinical & Odontogram Systems
- **2D & 3D Interactive Odontograms:**
  - 2D SVG anatomical vector tooth component.
  - 3D Dentition Viewer built with React Three Fiber (`@react-three/fiber`, `@react-three/drei`, `three.js`) with raycasting surface interaction.
  - Complete support for Adult dentition (FDI 11–48 / Universal 1–32) and Primary/Pediatric dentition (FDI 51–85 / Universal A–T).
- **Surface Coordinates:** 5 surfaces per tooth: Mesial (M), Distal (D), Occlusal (O) / Incisal (I), Buccal / Facial (B/F), Lingual / Palatal (L/P).
- **Periodontal Probing Matrix:** 6-site probing per tooth (DB, B, MB, DL, L, ML) capturing PD, CAL, GM, BOP, SUP, PI, mobility, and furcation grades.
- **Clinical Condition Layering:** State machine supporting simultaneous conditions (e.g. active caries + composite restoration + planned RCT + crown).
- **Procedure & Charting Auto-Sync:** Marking treatment procedures completed chairside automatically syncs conditions to `patient_teeth` and `tooth_findings`.

### Treatment Plans & Clinical Notes
- Multi-phase treatment planning (`TreatmentPlan`, `TreatmentPhase`, `TreatmentProcedure`).
- Pre-populated CDT procedure catalog (`ProcedureCode`, `ProcedureCategory`) with standard pricing and estimated duration.
- 360-degree consolidated medical history view inside `PatientResource` combining past visits, allergies, systemic conditions, prescriptions, and external treatments.

### Appointments & Dynamic Calendar
- Role-scoped React + FullCalendar `AppointmentsCalendarWidget` on the Filament dashboard (Month / Week / Day / List views):
  - Admins and receptionists see all appointments across doctors and operatories; doctors see and move only their own.
  - Click/drag an empty slot to book, drag/resize to reschedule, side drawer for status transitions (arrived → in chair → completed).
  - Maintenance windows render as hatched background blocks plus a chip strip above the grid.
  - The legacy table `DashboardCalendarWidget` is kept in code but hidden (`canView()` returns false).
- Conflict prevention avoiding overlapping bookings for the same doctor or chair (`RoomAvailabilityService`).
- Lifecycle tracking: `booked` -> `confirmed` -> `arrived` / `in_chair` -> `completed` / `no_show` / `cancelled`.

### Room & Equipment Maintenance
- `MaintenanceResource` (Clinic Operations → Maintenance) logs AC, chair, X-ray, autoclave, etc. jobs with vendor and cost.
- Each job picks an impact: **close all rooms** (branch), **close selected rooms**, or **rooms stay operational** with a "what's missing" note.
- Closures block booking at every entry point. Partial outages show a "Something will be missing in this room" pop-up that must be confirmed.
- Managed by secretary / clinic_admin / super_admin; doctors have view-only access (`MaintenanceRecordPolicy`).

### Billing, Invoicing & Financial Informatics
- **Chairside Invoicing:** Instant conversion of completed clinical procedures into invoice line items.
- **Auto Balance Calculation:** `balance_due = max(0, total_amount - paid_amount)`.
- **Installment Debt Engine:** Flexible down payments + recurring schedules (weekly, bi-weekly, monthly, milestone-based).
- **InstaPay Reference Logging:** Validated transaction reference receipts with immutable audit trails.
- **Specialist Doctor Commissions:** Gross chair-production splits, net production (minus lab costs), and tiered volume splits; linked to `PayrollSlip`.
- **Multi-Currency:** Dynamic formatting centered on EGP (Egyptian Pound), with runtime switching to SAR, AED, USD, and EUR via `CurrencyHelper`.

### Inventory, Dental Labs & Clinic Operations
- Consumable inventory tracking with batch numbers, expiration dates, and First-Expired-First-Out (FEFO) stock movement.
- Dental lab directory and outsourced prosthesis order tracking (`LabOrder`).
- Clinic expense tracking with distinct categorizations (facility expenses, supplies, payroll, commissions).

### Security, Cryptography & Backup System
- **Encrypted Local Backups:** Full database dumps + patient file attachments encrypted with AES-256-CBC and signed with HMAC-SHA256 (Encrypt-then-MAC).
- **Tamper-Resistant Backup Deletion:** Deletion requires entering the `BACKUP_DELETE_PASSWORD`.
- **Hardware Fingerprint & Tamper-Proof Clock:** Monotonic tick recording (`license_clock_ticks`) preventing local clock rollbacks during offline grace periods.
- **Post-Op Voice Notes:** Protected audio storage with time-limited or revocable token streaming (`/voice-notes/stream/{share_token}`).

### AI & Automated Communication
- **WhatsApp Integration:** Automated appointment confirmation and reminder delivery via Evolution API / Meta Cloud API.
- **Gemini NLP Parsing:** `GeminiService.php` parses inbound patient WhatsApp text and voice notes into structured appointment booking payloads.

---

## 2. In-Progress & Partially Built Features

1. **Direct 3D Odontogram Embedding in Filament:**
   - The 3D viewer is fully functional in Inertia/React (`resources/js/Components/Dental3D/`). Embedding it natively into Filament Livewire tabs without iframe reload friction is currently in progress.
2. **Central License Server Production Deployment:**
   - Client-side offline grace period (72 hours) and heartbeat mechanisms are verified locally with mock endpoints (`/api/internal/license/*`). Remote server deployment on `auth.dentalcare.app` is pending infrastructure setup.
3. **Egyptian Tax Authority (ETA) E-Invoicing Connector:**
   - Invoicing data models and tax schemas align with ETA standards. Direct API dispatch awaits clinic tax portal credentials.
4. **Push Notification Background Worker Queues:**
   - Web Push subscription and notification center are operational. Background dispatch via queue workers in production needs live VAPID keys.

---

## 3. Known Tech Debt & Quirks

- **Doc-Comment Test Annotations:** Feature and unit tests use `/** @test */` doc-comments, which trigger PHPUnit 11 deprecation notices (targeting PHP 8 attributes `#[Test]`).
- **Local License Bypass:** `LicenseManager` purposefully bypasses verification when `APP_ENV=local`. This is intentional and prevents developers from being locked out.

---

## 4. Immediate Next Priorities

1. **Enhance Filament 3D Viewer Bridge:** Create a smooth Livewire-to-React/Canvas bridge for the 3D Odontogram inside the `history-360` tab.
2. **Refactor PHPUnit Metadata:** Update test methods from `/** @test */` to `#[Test]` attributes to eliminate deprecation noise.
3. **Automate ETA E-Invoice Generation:** Build the XML/JSON serializer conforming to Egyptian Tax Authority v1.0 specifications.
4. **Clean Scratch Scripts:** Archive non-production debug scripts into a single git reference branch or documentation folder.
