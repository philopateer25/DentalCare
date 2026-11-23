---
paths:
  - "app/Models/Invoice*.php"
  - "app/Models/Payment*.php"
  - "app/Models/Installment*.php"
  - "app/Models/DoctorCommission*.php"
  - "app/Models/ClinicExpense*.php"
  - "app/Models/PayrollSlip*.php"
  - "app/Filament/Resources/InvoiceResource/**"
  - "app/Filament/Resources/PaymentResource/**"
  - "app/Filament/Resources/InstallmentPlanResource/**"
  - "app/Filament/Resources/DoctorCommissionResource/**"
  - "app/Filament/Resources/ClinicExpenseResource/**"
  - "app/Filament/Resources/PayrollSlipResource/**"
  - "app/Services/*Invoice*.php"
  - "app/Services/Widgets/FinanceWidgetService.php"
---

# Billing & Financial Informatics Guidelines

## Invoicing & Procedure Sync
- When clinical procedures are marked completed chairside, auto-generate corresponding line items on the patient's active or new invoice.
- Balance due must maintain invariant: `balance_due = max(0, total_amount - paid_amount)`.
- Support package pricing, bundled treatment discounts, and per-procedure line-item adjustments.

## Installment Debt Engine (InstaPay & Cash)
- Down payment + flexible recurring schedules: weekly, bi-weekly, monthly, or treatment milestone triggers (e.g. stage 1: bracket placement, stage 2: archwire change).
- **InstaPay Transaction Logging:**
  - Validate, format, and attach InstaPay reference numbers to payment transactions with immutable audit logs.
  - Overdue alerts and WhatsApp payment reminders in Arabic and English.

## Specialist Doctor Commissions
- **Split calculation models:**
  1. *Gross chair production percentage:* (e.g. 60% clinic / 40% specialist).
  2. *Net production split:* Gross fee minus outsourced dental lab and material expenses.
  3. *Tiered volume splits:* (e.g. 40% up to 50,000 EGP, 50% above 50,000 EGP monthly).
- **Commission Lifecycle:**
  `Pending Collection` -> `Funds Collected` -> `Commission Accrued` -> `Disbursed via Payroll Slip`.
- Doctor commissions can be tied directly into staff & doctor payroll slips (`PayrollSlip`).

## Multi-Currency & Compliance
- Default currency: EGP (Egyptian Pound). Formatted with `\App\Services\CurrencyHelper`.
- Extensible to SAR, AED, USD, EUR for cross-border MENA clinics.
- Schema prepared for Egyptian Tax Authority (ETA) e-invoicing compliance.
