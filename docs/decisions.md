# DentalCare Architectural Decisions & Codebase Conventions

This document captures non-obvious architectural patterns, intentional workarounds, library selections, and design rationale across DentalCare. **AI agents and developers should review this document before refactoring or "cleaning up" code that may appear unusual at first glance.**

---

## 1. Local License Verification Bypass
- **Location:** `app/Services/LicenseManager.php` (`getLicenseState()`)
- **Pattern:**
  ```php
  if (app()->environment('local')) {
      return [
          'status' => self::STATUS_ACTIVE,
          'elapsed_hours' => 0,
          'grace_remaining_hours' => 72,
          'message' => 'Development mode — license always active.',
      ];
  }
  ```
- **Rationale:** Production DentalCare installations enforce offline grace periods (72 hours) and hardware fingerprinting. Without this bypass in `local` mode, local development, automated testing, and CI would fail or lock out due to the absence of a signed license file.
- **Guidance:** **DO NOT** remove, "harden", or refactor this local bypass unless explicitly building production licensing tests.

---

## 2. Monotonic Tamper-Proof Clock
- **Location:** `app/Services/TamperProofClock.php`, `config/license.php`
- **Pattern:** Tracks elapsed time using encrypted state files (`storage/app/license_clock.enc`) and database tick records (`license_clock_ticks`) rather than querying the host OS system time (`now()`).
- **Rationale:** Clinic desktop machines in regions with unreliable internet might attempt to bypass subscription expiry by winding back their system clock. The tamper-proof clock detects negative time shifts and marks the installation as `tampered`.

---

## 3. Dual Tooth Data Architecture: Flattened State vs. Historical Examinations
- **Location:** `patient_teeth` table vs. `dental_examinations` + `tooth_findings` tables
- **Pattern:** The application maintains two separate clinical representations:
  1. `patient_teeth`: A flattened key-value record per tooth for instant UI rendering of current tooth conditions and surfaces.
  2. `dental_examinations` & `tooth_findings`: An immutable, versioned chronological history of every clinical exam and finding over time.
- **Rationale:** Interactive vector and 3D odontograms need $O(1)$ fast lookups to render 32 adult and 20 primary teeth at 60fps on clinic tablets. Legal and medical compliance simultaneously requires a complete point-in-time audit trail of every past visit.
- **Guidance:** When updating a tooth's condition via procedures, ensure both `patient_teeth` and the active examination's `tooth_findings` are updated.

---

## 4. Single-Database Multi-Tenancy via Filament v3
- **Location:** `app/Providers/Filament/AdminPanelProvider.php`, `app/Models/Practice.php`
- **Pattern:** Multi-tenancy uses Filament's built-in tenant model:
  ```php
  $panel->tenant(\App\Models\Practice::class)
  ```
  All business tables (`patients`, `invoices`, `appointments`, `expenses`, `inventory_items`, `lab_orders`) contain a `practice_id` foreign key.
- **Rationale:** Separate-database multi-tenancy packages (e.g. `stancl/tenancy`) introduce severe migration overhead, prevent simple SQLite local testing, and complicate unified backups for single-clinic appliances. Filament's native tenant scoping combined with Eloquent global scopes and Spatie permissions provides clean data isolation with low operational complexity.
- **Guidance:** Always scope queries by `practice_id` or `Filament::getTenant()->id`. Never expose unscoped queries in public API routes.

---

## 5. Automatic Invoice Balance Invariant
- **Location:** `app/Models/Invoice.php`, `app/Filament/Resources/InvoiceResource/Pages/`
- **Pattern:** `balance_due` is computed strictly as:
  ```php
  $balanceDue = max(0, $totalAmount - $paidAmount);
  ```
- **Rationale:** Earlier iterations allowed manual balance adjustments, which led to synchronization drift between payments and invoice totals. Automatic calculation guarantees ledger integrity across installments and returns.

---

## 6. 3D Odontogram Manifest & Single-Asset Optimization
- **Location:** `resources/js/Components/Dental3D/dentitionManifest.ts`, `Dental3DViewer.tsx`
- **Pattern:** Rather than loading 32 individual 3D `.glb` files over the network, a central manifest maps FDI numbers and surface coordinates to specific named sub-meshes within a unified GLTF model.
- **Rationale:** Loading dozens of separate 3D assets causes significant network latency, memory spikes, and WebGL context crashes on clinic mobile devices and tablets.
- **Guidance:** Preserve the manifest mapping when modifying 3D scene lighting, materials, or raycasting detection.

---

## 7. SQLite Concurrency Optimizations for Local & Testing
- **Location:** `config/database.php`
- **Pattern:**
  ```php
  'journal_mode' => 'WAL',
  'synchronous' => 'NORMAL',
  'busy_timeout' => 5000,
  'transaction_mode' => 'DEFERRED',
  ```
- **Rationale:** Standard SQLite locks during simultaneous read/write cycles. Enabling Write-Ahead Logging (WAL) and a 5-second busy timeout eliminates "database is locked" exceptions during fast parallel test execution and concurrent browser sessions.

---

## 8. Backup Security & Encrypt-then-MAC
- **Location:** `app/Services/Backup/BackupEncryptor.php`, `config/backup.php`
- **Pattern:** Backups are compressed into a `.tar.gz`, encrypted with AES-256-CBC, and signed with HMAC-SHA256. Deletion requires an explicit `BACKUP_DELETE_PASSWORD`.
- **Rationale:** Clinic patient records constitute Protected Health Information (PHI). If backup USB drives or cloud mirrors are compromised, data remains completely secure. The deletion password prevents accidental loss or malicious wiping by staff.

---

## 9. Direct Gemini REST Integration for WhatsApp Appointment NLP
- **Location:** `app/Services/GeminiService.php`
- **Pattern:** Uses direct Laravel `Http::post` calls to the Google Gemini API with `responseMimeType: 'application/json'` rather than a heavyweight SDK or local Python daemon.
- **Rationale:** Provides fast, zero-dependency parsing of appointment dates, times, and patient notes from incoming WhatsApp messages and voice notes without requiring external server processes.
- **Guidance:** **DO NOT** delete or break `GeminiService.php`. It is an active production integration.

---

## 10. Dashboard Calendar Widget Replacement
- **Location:** `app/Filament/Widgets/DashboardCalendarWidget.php`
- **Pattern:** Replaced the legacy static "Revenue vs Expenses" chart on the dashboard with an interactive, role-scoped appointment calendar.
- **Rationale:** Operational feedback showed clinic receptionists and attending doctors prioritize their immediate daily schedule and chair queue upon logging in. Financial metrics remain fully accessible in the executive summary widget and `/finance` screen.
- **Update (Nov 2026):** The table widget is superseded by `AppointmentsCalendarWidget` (React + FullCalendar). `DashboardCalendarWidget::canView()` returns `false`; restore its role check to bring it back.

---

## 11. React Mounted Directly Inside Filament (No Iframe)
- **Location:** `app/Filament/Widgets/AppointmentsCalendarWidget.php`, `resources/js/filament/appointments-calendar.jsx`, `resources/css/filament-calendar.css`, Dashboard-scoped `HEAD_END` render hook in `AdminPanelProvider`.
- **Pattern:**
  - The widget's Blade view renders `<div wire:ignore data-appointments-calendar>`.
  - A separate Vite entry mounts React onto it and receives the owning Livewire component (`Livewire.find(wire:id)`).
  - All data goes through `#[Renderless]` PHP methods (`fetchEvents`, `bookAppointment`, `rescheduleAppointment`, …), which enforce tenant and doctor scoping. No new REST routes were added.
- **Why the assets go in `<head>`:** Filament widgets lazy-load by default, and `<script type="module">` tags inside lazily morphed HTML never run. The widget therefore sets `$isLazy = false`, and its assets come from a render hook scoped to `Filament\Pages\Dashboard`.
- **Why plain CSS:** the calendar CSS deliberately avoids Tailwind (no preflight) so it cannot restyle Filament. It themes FullCalendar through Filament's `--primary-*` / `--gray-*` variables and follows `html.dark`.
- **Gotcha:** after adding Filament widgets, resources or pages, run `php artisan filament:cache-components`. This project caches discovered components in `bootstrap/cache/filament`, and new classes stay invisible until the cache is refreshed.

---

## 12. Maintenance Impact Model & Shared Availability Guard
- **Location:** `app/Models/MaintenanceRecord.php`, `app/Services/Scheduling/RoomAvailabilityService.php`.
- **Pattern:** `impact` is one of `closes_branch`, `closes_rooms` or `no_closure`, with a pivot to operatories. `RoomAvailabilityService::assess()` returns:
  - `closures`, which always block booking;
  - `warnings`, which block until the user acknowledges them;
  - `appointment_conflict`.
- **Callers:** the API (`CalendarController`: 422 / 409 + `acknowledge_maintenance`), the React widget, AppointmentResource Create/Edit (Filament confirm modal via `GuardsRoomMaintenance`), the Patient appointments relation manager (callout + checkbox), and the follow-up and WhatsApp-approval actions.
- **Rationale:** a single rule set stops entry points from drifting apart. A `no_closure` job with no rooms selected warns every room in its branch (for example, central AC that is degraded but not down).
- **Timezone gotcha:** datetimes are stored as naive app-local values (`Africa/Cairo`). `RoomAvailabilityService::parse()` converts browser ISO/UTC input to the app timezone before querying. Don't compare raw UTC Carbons against these columns.

---

## 13. DOXamigo Theme as Plain CSS Layered Over Filament
- **Location:** `resources/css/filament-theme.css` (HEAD_END render hook in `AdminPanelProvider`), `resources/views/filament/brand/*`, design source in `design/stitch/` and `design/brand/`.
- **Pattern:** brand colours go through `->colors()` (primary `#3859a8`, success `#009e83`, danger `#d82c1e`) and `->font('DM Sans')`. All visual restyling is plain CSS targeting Filament's `fi-*` classes plus our own `dx-*` components.
- **Why not `->viteTheme()`:** Filament v3 themes need Tailwind v3, but this project uses Tailwind v4 for the Inertia app. `viteTheme()` would also replace Filament's compiled CSS.
- **Custom screens:**
  - branded login (`App\Filament\Pages\Auth\Login` + `filament.layouts.auth`)
  - dashboard greeting widget
  - patient directory columns
  - stepped appointment form with `PatientSnapshot`
  - patient profile header widget
  - maintenance stats and popup

  They change presentation only; authentication, booking guards and tenancy are unchanged.
- **Gotchas:**
  - After adding any Livewire/Filament class (pages, widgets, the custom Login), run `docker exec clinic_app php artisan filament:cache-components`. A stale cache makes Livewire reject requests to the new component with **419 Page Expired**.
  - The container's opcache revalidates every 60s. Run `docker restart clinic_app` to see PHP changes immediately.
