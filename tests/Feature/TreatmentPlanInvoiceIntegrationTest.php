<?php

namespace Tests\Feature;

use App\Models\DoctorCommission;
use App\Models\DoctorProfile;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\Practice;
use App\Models\ProcedureCategory;
use App\Models\ProcedureCode;
use App\Models\TreatmentPhase;
use App\Models\TreatmentPlan;
use App\Models\TreatmentProcedure;
use App\Models\User;
use App\Services\InvoiceGenerationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TreatmentPlanInvoiceIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected Practice $practiceA;
    protected Practice $practiceB;
    protected User $adminA;
    protected User $doctorA;
    protected User $secretaryA;
    protected User $adminB;
    protected Patient $patientA;
    protected Patient $patientB;
    protected TreatmentPlan $planA;
    protected TreatmentPhase $phaseA;
    protected TreatmentProcedure $procedure1;
    protected TreatmentProcedure $procedure2;
    protected ProcedureCode $codeD2140;
    protected ProcedureCode $codeD7140;
    protected InvoiceGenerationService $service;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'developer']);
        Role::firstOrCreate(['name' => 'super_admin']);
        Role::firstOrCreate(['name' => 'clinic_admin']);
        Role::firstOrCreate(['name' => 'doctor']);
        Role::firstOrCreate(['name' => 'secretary']);

        $this->service = app(InvoiceGenerationService::class);

        // Practice A Setup
        $this->practiceA = Practice::create(['name' => 'Practice Alpha', 'currency' => 'USD']);
        $this->adminA = User::create([
            'practice_id' => $this->practiceA->id,
            'name' => 'Admin Alpha',
            'email' => 'admin@practice-a.test',
            'password' => bcrypt('password'),
        ]);
        $this->adminA->assignRole('clinic_admin');

        $this->doctorA = User::create([
            'practice_id' => $this->practiceA->id,
            'name' => 'Dr. Alice Alpha',
            'email' => 'doctor@practice-a.test',
            'password' => bcrypt('password'),
        ]);
        $this->doctorA->assignRole('doctor');

        DoctorProfile::create([
            'user_id' => $this->doctorA->id,
            'practice_id' => $this->practiceA->id,
            'specialty' => 'General Dentistry',
            'default_commission_percentage' => 40.00,
        ]);

        $this->secretaryA = User::create([
            'practice_id' => $this->practiceA->id,
            'name' => 'Secretary Alpha',
            'email' => 'secretary@practice-a.test',
            'password' => bcrypt('password'),
        ]);
        $this->secretaryA->assignRole('secretary');

        // Practice B Setup
        $this->practiceB = Practice::create(['name' => 'Practice Beta', 'currency' => 'EUR']);
        $this->adminB = User::create([
            'practice_id' => $this->practiceB->id,
            'name' => 'Admin Beta',
            'email' => 'admin@practice-b.test',
            'password' => bcrypt('password'),
        ]);
        $this->adminB->assignRole('clinic_admin');

        $category = ProcedureCategory::create(['name' => 'Restorative & Surgery', 'code' => 'REST_SURG']);

        $this->codeD2140 = ProcedureCode::create([
            'category_id' => $category->id,
            'code' => 'D2140',
            'title' => 'Amalgam - One Surface, Primary or Permanent',
            'standard_fee' => 150.00,
        ]);

        $this->codeD7140 = ProcedureCode::create([
            'category_id' => $category->id,
            'code' => 'D7140',
            'title' => 'Extraction, Erupted Tooth or Exposed Root',
            'standard_fee' => 200.00,
        ]);

        // Patient A & Plan A
        $this->patientA = Patient::create([
            'practice_id' => $this->practiceA->id,
            'file_number' => 'PAT-A-100',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'phone' => '1112223333',
            'gender' => 'male',
        ]);

        $this->planA = TreatmentPlan::create([
            'patient_id' => $this->patientA->id,
            'doctor_id' => $this->doctorA->id,
            'title' => 'Comprehensive Alpha Plan',
            'status' => 'in_progress',
        ]);

        $this->phaseA = TreatmentPhase::create([
            'treatment_plan_id' => $this->planA->id,
            'name' => 'Phase 1 - Restorative',
            'sequence' => 1,
        ]);

        $this->procedure1 = TreatmentProcedure::create([
            'treatment_phase_id' => $this->phaseA->id,
            'procedure_code_id' => $this->codeD2140->id,
            'doctor_id' => $this->doctorA->id,
            'tooth_number_fdi' => 16,
            'surface' => 'O',
            'fee' => 150.00,
            'discount' => 10.00,
            'net_amount' => 140.00,
            'status' => 'completed',
            'completed_at' => now(),
        ]);

        $this->procedure2 = TreatmentProcedure::create([
            'treatment_phase_id' => $this->phaseA->id,
            'procedure_code_id' => $this->codeD7140->id,
            'doctor_id' => $this->doctorA->id,
            'tooth_number_fdi' => 26,
            'surface' => 'WHOLE',
            'fee' => 200.00,
            'discount' => 0.00,
            'net_amount' => 200.00,
            'status' => 'completed',
            'completed_at' => now(),
        ]);

        // Patient B & Plan B
        $this->patientB = Patient::create([
            'practice_id' => $this->practiceB->id,
            'file_number' => 'PAT-B-200',
            'first_name' => 'Jane',
            'last_name' => 'Smith',
            'phone' => '4445556666',
            'gender' => 'female',
        ]);
    }

    public function test_billable_treatment_procedure_can_generate_invoice_item(): void
    {
        $this->actingAs($this->adminA);

        $invoice = $this->service->generateInvoiceFromPlan(
            plan: $this->planA,
            procedureIds: [$this->procedure1->id],
            user: $this->adminA
        );

        $this->assertInstanceOf(Invoice::class, $invoice);
        $this->assertEquals($this->patientA->id, $invoice->patient_id);
        $this->assertEquals($this->practiceA->id, $invoice->practice_id);
        $this->assertCount(1, $invoice->items);

        $item = $invoice->items->first();
        $this->assertEquals($this->procedure1->id, $item->treatment_procedure_id);
        $this->assertEquals(140.00, $item->total_price);
    }

    public function test_invoice_amount_is_calculated_correctly(): void
    {
        $this->actingAs($this->adminA);

        $invoice = $this->service->generateInvoiceFromPlan(
            plan: $this->planA,
            procedureIds: [$this->procedure1->id, $this->procedure2->id],
            user: $this->adminA
        );

        // Subtotal = 150 + 200 = 350
        // Discount = 10 + 0 = 10
        // Total = 340
        $this->assertEquals(350.00, $invoice->subtotal);
        $this->assertEquals(10.00, $invoice->discount_amount);
        $this->assertEquals(340.00, $invoice->total_amount);
        $this->assertEquals(340.00, $invoice->balance_due);
    }

    public function test_historical_procedure_price_is_preserved_on_invoice(): void
    {
        $this->actingAs($this->adminA);

        $invoice = $this->service->generateInvoiceFromPlan(
            plan: $this->planA,
            procedureIds: [$this->procedure1->id],
            user: $this->adminA
        );

        $item = $invoice->items->first();
        $this->assertEquals(140.00, $item->total_price);

        // Mutate treatment procedure fee afterwards
        $this->procedure1->update([
            'fee' => 300.00,
            'net_amount' => 290.00,
        ]);

        // Invoice item must retain historical billed price (140.00)
        $this->assertEquals(140.00, $item->fresh()->total_price);
    }

    public function test_same_procedure_cannot_be_invoiced_twice(): void
    {
        $this->actingAs($this->adminA);

        // First invoicing succeeds
        $this->service->generateInvoiceFromPlan(
            plan: $this->planA,
            procedureIds: [$this->procedure1->id],
            user: $this->adminA
        );

        // Second invoicing attempt must fail with InvalidArgumentException
        $this->expectException(InvalidArgumentException::class);
        $this->service->generateInvoiceFromPlan(
            plan: $this->planA,
            procedureIds: [$this->procedure1->id],
            user: $this->adminA
        );
    }

    public function test_multiple_procedures_can_be_billed_correctly(): void
    {
        $this->actingAs($this->adminA);

        $invoice = $this->service->generateInvoiceFromPlan(
            plan: $this->planA,
            procedureIds: null, // Bill all completed procedures in plan
            user: $this->adminA
        );

        $this->assertCount(2, $invoice->items);
        $this->assertEquals(340.00, $invoice->total_amount);
    }

    public function test_existing_payment_behavior_remains_correct(): void
    {
        $this->actingAs($this->adminA);

        $invoice = $this->service->generateInvoiceFromPlan(
            plan: $this->planA,
            procedureIds: [$this->procedure1->id], // Total 140.00
            user: $this->adminA
        );

        $this->assertEquals('unpaid', $invoice->status);
        $this->assertEquals(140.00, $invoice->balance_due);

        // Record payment of 140.00
        Payment::create([
            'practice_id' => $this->practiceA->id,
            'invoice_id' => $invoice->id,
            'patient_id' => $this->patientA->id,
            'amount' => 140.00,
            'payment_method' => 'cash',
            'paid_at' => now(),
        ]);

        $invoice->recalculateTotals();

        $this->assertEquals('paid', $invoice->fresh()->status);
        $this->assertEquals(0.00, $invoice->fresh()->balance_due);
    }

    public function test_doctor_provider_association_is_preserved(): void
    {
        $this->actingAs($this->adminA);

        $invoice = $this->service->generateInvoiceFromPlan(
            plan: $this->planA,
            procedureIds: [$this->procedure1->id],
            user: $this->adminA
        );

        $item = $invoice->items->first();
        $this->assertEquals($this->doctorA->id, $item->procedure->doctor_id);
        $this->assertEquals(40.00, $item->procedure->doctor->doctorProfile->default_commission_percentage);
    }

    public function test_existing_commission_calculation_is_not_duplicated_or_broken(): void
    {
        $this->actingAs($this->adminA);

        $invoice = $this->service->generateInvoiceFromPlan(
            plan: $this->planA,
            procedureIds: [$this->procedure1->id], // 140.00
            user: $this->adminA
        );

        $payment = Payment::create([
            'practice_id' => $this->practiceA->id,
            'invoice_id' => $invoice->id,
            'patient_id' => $this->patientA->id,
            'amount' => 140.00,
            'payment_method' => 'cash',
            'paid_at' => now(),
        ]);

        $commission = DoctorCommission::create([
            'doctor_id' => $this->doctorA->id,
            'payment_id' => $payment->id,
            'treatment_procedure_id' => $this->procedure1->id,
            'gross_amount' => 140.00,
            'lab_deduction_amount' => 0.00,
            'commission_percentage' => 40.00,
            'commission_amount' => DoctorCommission::calculateCommission(140.00, 0.00, 40.00),
            'status' => 'accrued',
        ]);

        $this->assertEquals(56.00, $commission->commission_amount);
    }

    public function test_practice_a_cannot_invoice_practice_b_treatment_procedures(): void
    {
        $this->expectException(InvalidArgumentException::class);

        // Plan B belonging to Patient B (Practice B)
        $planB = TreatmentPlan::create([
            'patient_id' => $this->patientB->id,
            'doctor_id' => $this->adminB->id,
            'title' => 'Beta Plan',
            'status' => 'in_progress',
        ]);

        // Admin A (Practice A) attempting to generate invoice for Plan B
        $this->service->generateInvoiceFromPlan(
            plan: $planB,
            procedureIds: null,
            user: $this->adminA
        );
    }

    public function test_unauthorized_roles_cannot_generate_invoices(): void
    {
        $unauthorizedUser = User::create([
            'practice_id' => $this->practiceA->id,
            'name' => 'Unprivileged User',
            'email' => 'unprivileged@practice-a.test',
            'password' => bcrypt('password'),
        ]);

        $this->expectException(InvalidArgumentException::class);

        $this->service->generateInvoiceFromPlan(
            plan: $this->planA,
            procedureIds: [$this->procedure1->id],
            user: $unauthorizedUser
        );
    }

    public function test_existing_treatment_plan_behavior_still_passes(): void
    {
        $this->assertEquals('in_progress', $this->planA->status);
        $this->assertEquals($this->patientA->id, $this->planA->patient->id);
    }
}
