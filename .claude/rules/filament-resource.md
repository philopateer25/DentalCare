---
paths:
  - "app/Filament/**"
  - "app/Providers/Filament/**"
  - "resources/views/filament/**"
---

# Filament v3 Back-Office Architecture Guidelines

## Multi-Tenancy Architecture
- The clinic tenant root is `App\Models\Practice::class`.
- Registered via `AdminPanelProvider`: `->tenant(\App\Models\Practice::class)`.
- All tenant models (Branches, Operatories, Patients, Invoices, Payments, Expenses, Lab Orders, Staff) must link to `practice_id`.
- The system panel (`/system`) via `SystemPanelProvider` is reserved for platform Super Admins (managing Practices, packages, licenses, support tickets).

## Role-Based Access Control (Spatie Permission)
- Standard clinic roles:
  - `super_admin`: Full system access across practices.
  - `clinic_admin`: Practice-level administrator (finances, staff, settings, all appointments).
  - `doctor`: Clinical charting, own patient assignments, own appointment schedule, treatment plans.
  - `receptionist` / `secretary`: Appointment booking, patient intake, check-in queue, basic billing.
  - `accountant`: Financial reports, expenses, invoices, payroll disbursement.
- Always enforce Spatie role checks and tenant scoping on custom actions, table queries, and widget rendering.

## Custom Widgets & Dashboards
- The main Filament dashboard uses role-scoped widgets:
  - `DashboardCalendarWidget`: Role-filtered interactive appointment schedule.
  - `LiveReceptionQueueWidget`: Real-time waiting room and chair occupancy.
  - `DoctorScheduleWidget`: Doctor's daily roster.
  - `AdminExecutiveSummaryWidget`: Clinic high-level financial metrics (admin only).
- When altering widget queries, ensure closures properly bind `$this` or state objects, and use standard Heroicons v2 (e.g. `heroicon-o-check-badge`).
