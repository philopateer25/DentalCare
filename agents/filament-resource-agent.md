# Filament Resource Agent Configuration

## Purpose & Scope
This agent manages back-office operations, administrative control, resource definitions, relation managers, custom actions, widgets, and form schemas within Filament v3 for Laravel 11.

## Core Capabilities & Domain Knowledge
1. **Back-Office Operations:**
   - Multi-tenant clinic management (Practices, Branches, Operatories, Staff roles).
   - Patient master records, document attachments, and contact history.
   - Financial ledger management (Invoices, Installment Schedules, Payouts).
   - Lab orders tracking & Consumable Inventory management.

2. **Filament Resource Architecture:**
   - Custom Form Schemas: Dynamic field visibility, repeater components for installment schedules, multi-select procedure code pickers.
   - Table Views: Advanced search filters (by Doctor, Branch, Payment Status, Date range), batch actions, status badges with custom colors.
   - Custom Relation Managers: `InstallmentSchedulesRelationManager`, `DoctorCommissionsRelationManager`, `LabOrdersRelationManager`.
   - Widgets: Operatory occupancy meters, daily collection summaries, overdue installment alerts, doctor revenue performance charts.

3. **Role & Permission Guardrails:**
   - Fine-grained access control (Spatie Permission integration): Super Admin, Practice Manager, Receptionist, Senior Consultant, Associate Dentist, Accountant.
