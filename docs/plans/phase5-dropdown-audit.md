# Phase 5 — dropdown audit (for review)

The app has **139 `Select` fields** in `app/Filament`. This sorts them into:

- **Fixed:** records or statuses. These must stay a closed list.
- **Creatable:** the clinic should be able to type a new value once and pick it next time.
- **Text → creatable:** free-text fields that staff fill with the same words again and again.

The reusable pieces are already built and used on the fields marked ✅:
- `App\Filament\Forms\CreatableSelect::make()` / `::tags()` / `::phrases()`
- the `practice_options` table and Settings → **Dropdown lists**

The rest waits for your yes or no.

## ✅ Done in this round (the plan's own examples)

| Screen | Field | List |
|---|---|---|
| Prescriptions (patient page) | Medication, Dosage, Frequency, Duration | `medication`, `rx_dosage`, `rx_frequency`, `rx_duration` |
| Treatment plan → Draft Rx | Medication, Dosage, Frequency, Duration | same lists |
| Treatment plan → procedure | Tools, Materials (tags) | `tool`, `material` |
| Treatment plan → procedure | **Quick phrase** → appends to Notes | `procedure_note` |
| Patient → medical history | Other conditions, Active medications, Cardiac conditions, Diabetes medications (tags) | `medical_condition`, `active_medication`, `cardiac_condition`, `diabetes_medication` |
| Expenses | Accounting category | `expense_category` (the 11 old options are the defaults) |
| Dental chart (3D/2D) → Diagnosis | Clinical diagnosis | `diagnosis` |
| Dental chart → Findings | Crown material, Implant system, Reason missing | `crown_material`, `implant_brand`, `extraction_reason` |
| Dental chart → Findings | **Quick phrase** → appends to tooth Notes | `tooth_note` |

## Fixed — leave as they are

| Kind | Fields |
|---|---|
| Records (choose an existing row) | `patient_id`, `doctor_id`, `operatory_id`, `branch_id`, `invoice_id`, `payment_id`, `supplier_id`, `dental_lab_id`, `insurance_provider_id`, `patient_insurance_policy_id`, `procedure_code_id`, `staff_member_id`, `user_id`, `treatment_plan_id`, `lab_order_id`, `appointment_id`, `inventory_batch_id`, `parent_appointment_id`, `logged_by_user_id`, `category_id`, `practice_id` |
| Statuses & workflow | every `status`, `payment_status`, `license_status`, `priority` |
| Money & accounting rules | `payment_method`, `frequency`, `recurring_frequency`, `payment_terms`, `pay_period_month`, `pricing_tier`, `plan_type`, `claim_type`, `employment_type` |
| Clinical scales (must stay standard) | `surface`, `blood_type`, `diabetes_type`, `blood_pressure_stage`, `pregnancy_trimester`, `dental_anxiety_level`, `anticoagulant_type`, `subscriber_relationship`, `gender` |
| System settings | `roles`, `currency`, `locale`, `package_tier`, `type` (practice), `duration_minutes`, `pay_period_month` |
| Lab order specs (standard lab vocabulary) | `shade`, `stump_shade`, `translucency`, `surface_texture`, `occlusal_staining`, `margin_design`, `impression_type` |

## Proposed next — your call

| Screen | Field today | Proposal | Why |
|---|---|---|---|
| Appointments (3 screens) | Chief complaint — free text | Creatable (`complaint`) | Reception types "pain", "check-up", "cleaning"… every day |
| Appointments | Appointment `type` — fixed | Creatable | Clinics name visit types differently |
| Treatment plan → procedure | Anesthetic agent / dosage / technique — fixed presets | Creatable with presets as defaults | Local brands (e.g. Mepecaine, Artinibsa) |
| Treatment plan → procedure | Chairside notes — free text | Quick phrases, as for Notes | Same phrases repeat |
| Patient → external treatments | `procedure_title` — fixed | Creatable | Free description of work done elsewhere |
| Lab orders | `material`, `order_type` | Creatable | New materials and lab products appear often |
| Dental labs | `lab_type` | Creatable | |
| Inventory | `category`, `unit`, `type` | Creatable | Each clinic organises stock differently |
| Supplier items | `category` | Creatable | Same as inventory |
| Maintenance | `category` | Creatable | Equipment types vary |
| Expenses | Payee — free text | Creatable (`payee`) | Same landlord / suppliers every month |
| Invoice items | Description — free text | Creatable | Repeated non-procedure items |
| Patient files | `type`, Title | Keep type fixed; title → quick phrases | |

## Not done yet (from the plan)

- **Chairside React screens** (task 5): done for the Dental chart (`CreatableField.tsx` + `/api/practice-options`). Left fixed on purpose: severity, urgency, mobility, pulp/periapical diagnosis, caries depth/class, canals, implant diameter/length.
- **Most-used per doctor:** counts are per clinic for now. Per-doctor counts would need a `user_id` column on usage.
- **Timing check** of the 5 core flows, before and after (task 6). To do with a real receptionist.
- **Default lists sent from the central app** (2.8).
