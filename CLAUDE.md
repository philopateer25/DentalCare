# AgentRules — DOXamigo

> **Goal:** Maximum output quality with minimum token burn. Read this file FIRST on every task. Never re-read files you already have in context. Never echo large blocks back — summarize and reference by path + line.

---

## 1. Project Identity

| Key | Value |
|-----|-------|
| Name | DOXamigo |
| Org | DOXamigo |
| Stack | Laravel 11 · PHP 8.3 · Filament v3 · Inertia.js v3 · React (JSX/TSX) · Tailwind CSS |
| DB | MySQL/SQLite · Eloquent ORM |
| Auth | Spatie Permission (multi-role, multi-tenant) |
| Real-time | Laravel Reverb WebSockets |
| Notifications | WebPush · WhatsApp API |
| PDF | barryvdh/laravel-dompdf |
| Multi-tenant | Practice-scoped (`practice_id` on nearly every table) |

---

## 2. Token Conservation Rules (MANDATORY)

1. **Never dump full files.** Read only the lines you need (`StartLine`/`EndLine`). Cite by path#L-range.
2. **Never re-read a file** already in context. Use what you have.
3. **Never echo code back to the user** unless they asked. Say "Updated X at path#L10-L25".
4. **Batch related edits** into one `edit_file` call per file when possible.
5. **Prefer `edit_file` over `write_file(Overwrite)`** — surgical edits save tokens vs rewriting.
6. **Short git commits:** `feat(odontogram): add surface selection` — no essays.
7. **Stop generating once the task is done.** Don't add "let me know if you need anything".
8. **For tests:** Run `php artisan test --filter=ClassName` (targeted), not full suite unless asked.
9. **Search before reading:** Use `grep -rn` to locate, then read only the target lines.
10. **Never repeat the user's prompt** or paraphrase it back.

---

## 3. Architecture Map

### 3.1 Directory Layout

```
app/
├── Console/Commands/       # 7 Artisan commands (Backup*, License*, MigrateOdontogramData)
├── Filament/
│   ├── Pages/              # BackupManager, WhatsAppSettings, Settings
│   ├── Resources/          # 20 Resources (see §3.3)
│   └── Widgets/            # 17 Dashboard widgets (see §3.4)
├── Http/Controllers/       # 15 controllers (see §3.5)
├── Jobs/                   # SendPostOpCheckupJob, SendWhatsApp*Job
├── Livewire/               # Odontogram, PatientFinanceTab
├── Models/                 # 47 Eloquent models (see §3.2)
├── Notifications/          # 6 notification classes
├── Observers/              # Appointment, Practice, TreatmentProcedure
├── Policies/               # 10 policy classes
├── Providers/
└── Services/               # 20 service classes (see §3.6)
resources/js/
├── Components/             # Shared React (Navbar, Sidebar, Badge, Card, etc.)
│   └── Dental3D/           # InteractiveTooth.tsx, Dental3DViewer.tsx, SingleToothViewer.tsx
├── Contexts/               # ThemeContext, LocaleContext
├── Layouts/                # AppLayout.jsx
└── Pages/                  # dashboard, patiants, operations, finance, calendar, analytics, labs, inventory, insurance, support, onboarding
routes/web.php              # All routes (Inertia + API endpoints)
tests/
├── Feature/                # 14 feature tests
└── Unit/                   # 5 unit tests
database/migrations/        # ~40 migration files
agents/                     # 4 domain agent specs (see §6)
```

### 3.2 Models (47 total)

**Clinical:** Patient, PatientTooth, ToothRecord, ToothFinding, ToothSurfaceFinding, SurfaceCondition, PerioCharting, DentalExamination, MedicalHistory, TreatmentPlan, TreatmentProcedure, TreatmentPhase, ProcedureCode, ProcedureCategory, ExternalTreatment, Prescription, VoiceNote, PatientFile

**Scheduling:** Appointment, Operatory

**Financial:** Invoice, InvoiceItem, Payment, InstallmentPlan, InstallmentSchedule, DoctorCommission, PayrollSlip, ClinicExpense

**Inventory & Lab:** InventoryItem, InventoryBatch, InventoryStockMovement, ProcedureConsumption, DentalLab, LabOrder, Supplier

**Insurance:** InsuranceProvider, InsuranceClaim, PatientInsurancePolicy

**Organization:** Practice, Branch, User, StaffMember, DoctorProfile

**Communication:** CommunicationLog, SupportTicket, SupportTicketReply, WhatsAppAppointmentRequest

**Key Relations to remember:**
- `Practice` → has many Patients, Appointments, Invoices, Users (tenant root)
- `Patient` → hasMany TreatmentPlans, Invoices, PatientTeeth, Appointments, MedicalHistory
- `TreatmentPlan` → hasMany TreatmentProcedures → belongsTo ProcedureCode
- `Invoice` → hasMany InvoiceItems (polymorphic), Payments; hasOne InstallmentPlan
- `TreatmentProcedure` → triggers Invoice line-item on completion (via Observer)

### 3.3 Filament Resources (20)

AppointmentResource, ClinicExpenseResource, DentalLabResource, DoctorCommissionResource, InstallmentPlanResource, InsuranceClaimResource, InsuranceProviderResource, InventoryResource, InvoiceResource, LabOrderResource, OperationResource, PatientResource, PatientInsurancePolicyResource, PaymentResource, PayrollSlipResource, ProcedureCodeResource, StaffMemberResource, SupplierResource, SupportTicketResource, UserResource

**Relation Managers:** AppointmentsRM, BatchesRM, CommunicationsRM, ExternalTreatmentsRM, FilesRM, InventoryItemsRM, ItemsRM, LabOrdersRM, MovementsRM, PaymentsRM, PrescriptionsRM, SchedulesRM, TreatmentPlansRM, UsersRM

**Patient-specific Widgets (ViewPatient page):** PatientAlertWidget, PatientFilesWidget, PatientFinanceWidget, PatientLabOrdersWidget, PatientMedicalHistory360Widget

### 3.4 Dashboard Widgets (17)

| Widget | Role(s) |
|--------|---------|
| AdminExecutiveSummaryWidget | Super Admin |
| AdminRevenueExpenseChartWidget | Super Admin |
| AdminTopTreatmentsWidget | Super Admin |
| AdminLabDebtsWidget | Super Admin |
| FinanceOverviewWidget | Admin, Accountant |
| InventoryOverviewWidget | Admin, Manager |
| DoctorEarningsWidget | Doctor |
| DoctorScheduleWidget | Doctor |
| DoctorPendingLabsWidget | Doctor |
| DoctorCommissionsOverviewWidget | Doctor |
| SecretaryStatsWidget | Receptionist |
| LiveReceptionQueueWidget | Receptionist |
| WhatsAppPendingRequestsWidget | Receptionist |
| DashboardCalendarWidget | All |
| PendingTasksWidget | All |
| ActionRequiredWidget | All |
| TestPushNotificationWidget | Dev/Admin |

### 3.5 Controllers (15)

AnalyticsController, CalendarController, CentralLicenseMockController, DashboardWidgetController, DentalExaminationController, NotificationController, OnboardingController, PatientOdontogramController, PatientVoiceNoteController, PracticeFeatureController, PushSubscriptionController, SupportTicketController, TreatmentPlanController, WhatsAppWebhookController, Controller

### 3.6 Services (20)

AnalyticsService, BackupEncryptor, BackupService, ClinicalPresets, CurrencyHelper, DashboardWidgetEngine, DatabaseDumper, DatabaseRestorer, FeatureManager, FinanceWidgetService, GeminiService, HardwareFingerprint, InventoryStockService, InventoryWidgetService, InvoiceGenerationService, LanguageHelper, LicenseManager, NotificationService, TamperProofClock, WhatsAppService

### 3.7 Observers

| Observer | Triggers |
|----------|----------|
| AppointmentObserver | Notifications on create/update |
| PracticeObserver | Tenant setup on create |
| TreatmentProcedureObserver | Auto-generate invoice line-item on status → completed |

### 3.8 Jobs

`SendPostOpCheckupJob`, `SendWhatsAppListMessageJob`, `SendWhatsAppMessageJob`

### 3.9 Notifications

`AppointmentReminderNotification`, `InvoiceOverdueNotification`, `LowInventoryNotification`, `ProcedureCompletedNotification`, `SystemAlertNotification`, `TestPushNotification`

---

## 4. Critical Conventions

### 4.1 Multi-Tenancy

- **Every** query MUST scope by `practice_id`. No global unscoped queries.
- Filament uses tenant-aware panel: `/admin/{tenant}/resource`.
- Cross-tenant data access = security bug. Always `abort_unless()`.

### 4.2 FDI Tooth Numbering

- Adult: Quadrants 1-4 → teeth 11-18, 21-28, 31-38, 41-48
- Pediatric: Quadrants 5-8 → teeth 51-55, 61-65, 71-75, 81-85
- Store as `tooth_number_fdi` (string). Convert FDI↔Universal via `ToothRecord` model.

### 4.3 Surfaces (5)

Mesial (M), Distal (D), Occlusal/Incisal (O/I), Buccal/Facial (B/F), Lingual/Palatal (L/P)

### 4.4 Periodontal Charting

6 sites per tooth: Distobuccal, Midbuccal, Mesiobuccal, Distolingual, Midlingual, Mesiolingual.
Metrics: PD, CAL, GM, BOP, SUP, PI, Mobility (I-III), Furcation (I-IV).

### 4.5 Financial Flow

```
TreatmentProcedure → status:completed
  → TreatmentProcedureObserver
    → InvoiceGenerationService → Invoice + InvoiceItems
      → Payment (Cash / InstaPay / POS / TPA)
        → DoctorCommission (Gross/Net/Tiered split)
```

### 4.6 Appointment Lifecycle

`Booked → Arrived → In Chair → Completed → (or No-Show)`

### 4.7 Commission Splits

- Percentage of Gross Chair Production (e.g., 60/40)
- Net Production (Gross − Lab costs)
- Tiered Volume (40% up to 50K EGP, 50% above)

### 4.8 Currency

Primary: EGP. Configurable: SAR, AED, USD. Use `CurrencyHelper` service.

---

## 5. Testing Checklist

### 5.1 Commands

```bash
php artisan test                              # Full suite
php artisan test --filter=ClassName           # Single test class
php artisan test --filter=ClassName::method   # Single method
php artisan test --parallel                   # Parallel execution
```

### 5.2 Existing Tests (19 total)

**Feature (14):**
- AnalyticsModuleTest
- BackupSystemTest
- ClinicalUpgradesAndVoiceNotesTest
- DashboardWidgetSystemTest
- DetailedCalendarTest
- EndToEndWorkflowAndSecurityTest
- InventoryCostingAndAdjustmentTest
- NotificationsSystemTest
- PracticeFeatureFlagsAndOnboardingTest
- RbacUserManagementTest
- SupportTicketModuleTest
- TenantIsolationTest
- TreatmentPlanInvoiceIntegrationTest
- ExampleTest

**Unit (5):**
- CurrencyHelperTest
- DoctorCommissionTest
- LanguageHelperTest
- ToothRecordTest
- ExampleTest

### 5.3 Test Coverage Matrix — What to Test for Every Change

| Layer Changed | Must Test |
|--------------|-----------|
| Model | Unit test: relations, casts, scopes, accessors |
| Observer | Feature test: trigger event → verify side effect |
| Service | Unit test: input/output, edge cases |
| Filament Resource | Feature test: CRUD via Livewire `assertSuccessful()` |
| Controller/API | Feature test: HTTP verbs, auth, tenant isolation |
| React Component | Manual or Puppeteer: render, click, state change |
| Migration | Run `migrate:fresh --seed` → verify schema |
| Policy | Feature test: authorized vs forbidden for each role |
| Notification | Feature test: `Notification::fake()` → `assertSentTo()` |
| Job | Feature test: `Queue::fake()` → `assertPushed()` |
| Widget | Feature test: Livewire render assertion |

### 5.4 Integration Test Chains (Full Workflow)

Always verify these end-to-end flows work after any change to their nodes:

```
Chain 1 — Clinical → Financial:
  Patient → TreatmentPlan → TreatmentProcedure(completed)
    → Observer → Invoice(auto) → Payment → DoctorCommission

Chain 2 — Appointment → Clinical:
  Appointment(Booked) → status:InChair → DentalExamination
    → ToothFinding → SurfaceCondition → TreatmentPlan

Chain 3 — Inventory → Clinical:
  ProcedureCode → ProcedureConsumption → InventoryStockMovement
    → LowInventoryNotification (if below threshold)

Chain 4 — Lab Flow:
  TreatmentProcedure → LabOrder → DentalLab
    → status tracking → invoice cost deduction

Chain 5 — Insurance:
  PatientInsurancePolicy → InsuranceClaim → Payment(TPA)
    → Invoice balance_due adjustment

Chain 6 — Communication:
  Appointment → AppointmentObserver → AppointmentReminderNotification
    → WebPush + WhatsApp (via SendWhatsAppMessageJob)

Chain 7 — Payroll:
  DoctorCommission(accrued) → PayrollSlip → disbursement

Chain 8 — Tenant Isolation:
  Any CRUD → verify practice_id filter → abort on cross-tenant
```

---

## 6. Domain Agent References

Read these ONLY when working on that specific domain:

| Domain | File | When to Read |
|--------|------|-------------|
| Clinical/Odontogram | `agents/dental-clinical-agent.md` | Tooth numbering, surfaces, perio charting, CDT codes |
| Billing/Finance | `agents/billing-finance-agent.md` | Invoices, payments, installments, commissions |
| Filament Back-office | `agents/filament-resource-agent.md` | Resources, widgets, forms, tables, Spatie roles |
| Inertia/React Chairside | `agents/inertia-react-agent.md` | SVG odontogram, 3D viewer, real-time UI, Reverb |

---

## 7. Code Generation Rules

### 7.1 PHP / Laravel

- Always `declare(strict_types=1)` in new files.
- Type-hint everything: params, returns, properties.
- Use Laravel 11 conventions: no `app/Http/Kernel.php`, use `bootstrap/app.php`.
- Eloquent: use `$casts` array (not `$dates`). Always define `$fillable`.
- Relationships: return type-hint (`HasMany`, `BelongsTo`, etc.).
- Queries: always scope `->where('practice_id', $tenantId)` or use a global scope.
- Filament: use `->tenant()` panel method. Resources must implement `HasTenancy`.
- Policies: check `$user->practice_id === $record->practice_id` in every method.

### 7.2 React / Inertia

- Use `usePage()` for shared Inertia props, not redundant API calls.
- Shared layout via `AppLayout.jsx`. Don't duplicate nav/sidebar.
- Tailwind only — no inline styles. Dark mode via `dark:` prefix.
- Framer Motion for animations. Lucide for icons.
- 3D/SVG: components in `Components/Dental3D/`. Use `types.ts` for shared types.
- Inertia links via `<Link>` component, not `<a>`. Use `router.visit()` for programmatic nav.

### 7.3 Migrations

- Always include `$table->foreignId('practice_id')->constrained()->cascadeOnDelete()`.
- Use `down()` method for rollback.
- Name pattern: `YYYY_MM_DD_HHMMSS_description.php`.

### 7.4 Testing

- Extend `Tests\TestCase`.
- Use `RefreshDatabase` trait.
- Create tenant context: `$practice = Practice::factory()->create()`.
- Authenticate: `$this->actingAs($user)`.
- Filament tests: `Livewire::test(ListPatients::class, ['tenant' => $practice])`.

---

## 8. Spatie Roles & Permissions

| Role | Access Level |
|------|-------------|
| Super Admin | Full system access, all practices |
| Practice Manager | Full access within own practice |
| Senior Consultant | Clinical + read finance |
| Associate Dentist | Own patients + clinical |
| Receptionist | Appointments, patient registration, queue |
| Accountant | Finance, invoices, payments, payroll |

Always check: `$user->hasRole('Super Admin')` or `$user->can('view_patient')`.

---

## 9. API Endpoints Reference

| Method | Endpoint | Controller | Auth |
|--------|----------|------------|------|
| GET | `/api/dashboard/widgets` | DashboardWidgetController | ✅ |
| POST | `/api/dashboard/widgets/preferences` | DashboardWidgetController | ✅ |
| GET | `/api/practice/features` | PracticeFeatureController | ✅ |
| POST | `/api/practice/features` | PracticeFeatureController | ✅ |
| GET | `/api/notifications` | NotificationController | ✅ |
| POST | `/api/notifications/{id}/read` | NotificationController | ✅ |
| POST | `/api/notifications/read-all` | NotificationController | ✅ |
| GET | `/api/calendar/appointments` | CalendarController | ✅ |
| POST | `/api/calendar/appointments` | CalendarController | ✅ |
| PATCH | `/api/calendar/appointments/{id}/reschedule` | CalendarController | ✅ |
| PATCH | `/api/calendar/appointments/{id}/status` | CalendarController | ✅ |
| GET | `/api/analytics` | AnalyticsController | ✅ |
| GET/POST | `/api/support/tickets` | SupportTicketController | ✅ |
| GET | `/api/support/tickets/{id}` | SupportTicketController | ✅ |
| POST | `/api/support/tickets/{id}/reply` | SupportTicketController | ✅ |
| PATCH | `/api/support/tickets/{id}/status` | SupportTicketController | ✅ |
| POST | `/api/patients/{id}/voice-notes` | PatientVoiceNoteController | ✅ |
| GET | `/api/patients/{id}/voice-notes` | PatientVoiceNoteController | ✅ |
| POST | `/api/whatsapp/webhook` | WhatsAppWebhookController | ✗ (no CSRF) |
| POST | `/api/odontogram/{patient}` | PatientOdontogramController | ✅ |
| POST | `/api/treatment-procedures/{id}/complete` | (closure in web.php) | ✅ |

---

## 10. Quick Commands

```bash
# Dev
php artisan serve                    # Start dev server
npm run dev                          # Vite HMR
php artisan migrate:fresh --seed     # Reset DB

# Test
php artisan test --filter=TenantIsolationTest
php artisan test --filter=TreatmentPlanInvoiceIntegrationTest

# Filament
php artisan make:filament-resource ModelName --generate
php artisan make:filament-widget WidgetName
php artisan make:filament-relation-manager ResourceName RelationName column

# Debug
php artisan route:list --compact
php artisan model:show ModelName
```

---

## 11. Pre-Commit Checklist

Before considering ANY task done:

- [ ] `practice_id` scoping present on all new queries
- [ ] Policy created/updated for new resources
- [ ] `$fillable` or `$guarded` set on new/modified models
- [ ] Relationships type-hinted
- [ ] Existing tests still pass: `php artisan test`
- [ ] New feature has at least one test covering the happy path
- [ ] Integration chain (§5.4) verified if touching a chain node
- [ ] No N+1 queries (use `->with()` eager loading)
- [ ] Filament resource has proper `canAccess()` / role guard
- [ ] No hardcoded practice IDs or user IDs
- [ ] Currency amounts use `CurrencyHelper` for formatting
- [ ] Notifications use proper channel (WebPush/WhatsApp/Database)
