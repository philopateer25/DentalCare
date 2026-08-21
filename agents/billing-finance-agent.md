# Billing & Financial Informatics Agent Configuration

## Purpose & Scope
This agent governs financial ledger operations, multi-stage installment plans, localized payment gateway integration (Cash, InstaPay, POS, TPA Insurance), and specialist chair-production commission splits tailored for clinics in Egypt and the MENA region.

## Core Capabilities & Domain Knowledge
1. **Invoice & Treatment Synchronization:**
   - Automatic line-item generation when clinical procedures are marked completed chairside.
   - Package pricing, bundled treatment discounts, and per-procedure adjustments.

2. **Installment Debt Engine (InstaPay / Flexible Cash Rules):**
   - **Down Payment + Recurring Installments:** Supports weekly, bi-weekly, monthly, or customized clinical milestone schedules (e.g., stage 1: bracket placement, stage 2: archwire change).
   - **InstaPay Transaction Reference Logging:** Validates, formats, and attaches InstaPay reference receipts to payments with audit trails.
   - **Payment Reminders:** Triggers localized Arabic/English WhatsApp notifications for due and overdue installments.

3. **Specialist Revenue Split Formulas:**
   - **Commission Split Rules:**
     - Percentage of Gross Chair Production (e.g., 60% Clinic / 40% Specialist).
     - Net Production Split (Gross fee minus Lab material costs).
     - Tiered Volume Splits (e.g., 40% up to 50,000 EGP, 50% above 50,000 EGP per month).
   - **Commission Settlement Lifecycle:** Pending Collection -> Funds Collected -> Commission Accrued -> Doctor Payout Disbursed.

4. **Multi-Currency & Regional Compliance:**
   - Primary currency: EGP (Egyptian Pound), customizable to SAR, AED, USD for cross-border MENA clinics.
   - E-invoicing schema ready for Egyptian Tax Authority (ETA) integration.
