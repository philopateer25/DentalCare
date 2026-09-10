<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Branch;
use App\Models\ClinicExpense;
use App\Models\DentalExamination;
use App\Models\DentalLab;
use App\Models\InventoryItem;
use App\Models\Invoice;
use App\Models\Operatory;
use App\Models\Patient;
use App\Models\Practice;
use App\Models\ProcedureCode;
use App\Models\TreatmentPlan;
use App\Models\TreatmentProcedure;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    protected Practice $practiceA;
    protected Practice $practiceB;
    protected User $userA;
    protected User $userB;
    protected Branch $branchA;
    protected Branch $branchB;
    protected Operatory $operatoryA;
    protected Operatory $operatoryB;
    protected Patient $patientA;
    protected Patient $patientB;
    protected Appointment $appointmentA;
    protected Appointment $appointmentB;
    protected Invoice $invoiceA;
    protected Invoice $invoiceB;
    protected TreatmentPlan $treatmentPlanA;
    protected TreatmentPlan $treatmentPlanB;
    protected DentalExamination $examinationA;
    protected DentalExamination $examinationB;
    protected InventoryItem $inventoryA;
    protected InventoryItem $inventoryB;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'doctor']);
        Role::firstOrCreate(['name' => 'clinic_admin']);

        // Practice A Setup
        $this->practiceA = Practice::create([
            'name' => 'Practice Alpha',
            'currency' => 'USD',
            'is_active' => true,
            'features' => ['3d_model', 'inventory', 'finance', 'labs', 'insurance', 'whatsapp'],
        ]);
        $this->branchA = Branch::create([
            'practice_id' => $this->practiceA->id,
            'name' => 'Branch Alpha Main',
        ]);
        $this->operatoryA = Operatory::create([
            'branch_id' => $this->branchA->id,
            'name' => 'Operatory A1',
        ]);
        $this->userA = User::create([
            'practice_id' => $this->practiceA->id,
            'branch_id' => $this->branchA->id,
            'name' => 'Dr. Alice Alpha',
            'email' => 'alice@practice-a.test',
            'password' => bcrypt('password'),
            'role' => 'doctor',
        ]);
        $this->userA->assignRole('doctor');

        $this->patientA = Patient::create([
            'practice_id' => $this->practiceA->id,
            'file_number' => 'PAT-A-001',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'phone' => '1111111111',
            'gender' => 'male',
        ]);
        $this->appointmentA = Appointment::create([
            'practice_id' => $this->practiceA->id,
            'branch_id' => $this->branchA->id,
            'operatory_id' => $this->operatoryA->id,
            'patient_id' => $this->patientA->id,
            'doctor_id' => $this->userA->id,
            'start_time' => now(),
            'end_time' => now()->addMinutes(30),
            'status' => 'booked',
        ]);
        $this->invoiceA = Invoice::create([
            'practice_id' => $this->practiceA->id,
            'patient_id' => $this->patientA->id,
            'invoice_number' => 'INV-A-1001',
            'total_amount' => 500.00,
            'paid_amount' => 0.00,
            'balance_due' => 500.00,
            'status' => 'unpaid',
            'issue_date' => now(),
        ]);
        $this->treatmentPlanA = TreatmentPlan::create([
            'patient_id' => $this->patientA->id,
            'doctor_id' => $this->userA->id,
            'title' => 'Alpha Plan',
            'status' => 'draft',
        ]);
        $this->examinationA = DentalExamination::create([
            'patient_id' => $this->patientA->id,
            'doctor_id' => $this->userA->id,
            'type' => 'initial',
        ]);
        $this->inventoryA = InventoryItem::create([
            'practice_id' => $this->practiceA->id,
            'name' => 'Alpha Composite Syringe',
        ]);

        // Practice B Setup
        $this->practiceB = Practice::create([
            'name' => 'Practice Beta',
            'currency' => 'EUR',
            'is_active' => true,
            'features' => ['3d_model', 'inventory', 'finance', 'labs', 'insurance', 'whatsapp'],
        ]);
        $this->branchB = Branch::create([
            'practice_id' => $this->practiceB->id,
            'name' => 'Branch Beta Main',
        ]);
        $this->operatoryB = Operatory::create([
            'branch_id' => $this->branchB->id,
            'name' => 'Operatory B1',
        ]);
        $this->userB = User::create([
            'practice_id' => $this->practiceB->id,
            'branch_id' => $this->branchB->id,
            'name' => 'Dr. Bob Beta',
            'email' => 'bob@practice-b.test',
            'password' => bcrypt('password'),
            'role' => 'doctor',
        ]);
        $this->userB->assignRole('doctor');

        $this->patientB = Patient::create([
            'practice_id' => $this->practiceB->id,
            'file_number' => 'PAT-B-001',
            'first_name' => 'Jane',
            'last_name' => 'Smith',
            'phone' => '2222222222',
            'gender' => 'female',
        ]);
        $this->appointmentB = Appointment::create([
            'practice_id' => $this->practiceB->id,
            'branch_id' => $this->branchB->id,
            'operatory_id' => $this->operatoryB->id,
            'patient_id' => $this->patientB->id,
            'doctor_id' => $this->userB->id,
            'start_time' => now(),
            'end_time' => now()->addMinutes(30),
            'status' => 'booked',
        ]);
        $this->invoiceB = Invoice::create([
            'practice_id' => $this->practiceB->id,
            'patient_id' => $this->patientB->id,
            'invoice_number' => 'INV-B-2001',
            'total_amount' => 750.00,
            'paid_amount' => 0.00,
            'balance_due' => 750.00,
            'status' => 'unpaid',
            'issue_date' => now(),
        ]);
        $this->treatmentPlanB = TreatmentPlan::create([
            'patient_id' => $this->patientB->id,
            'doctor_id' => $this->userB->id,
            'title' => 'Beta Plan',
            'status' => 'draft',
        ]);
        $this->examinationB = DentalExamination::create([
            'patient_id' => $this->patientB->id,
            'doctor_id' => $this->userB->id,
            'type' => 'initial',
        ]);
        $this->inventoryB = InventoryItem::create([
            'practice_id' => $this->practiceB->id,
            'name' => 'Beta Anesthetic Cartridge',
        ]);
    }

    public function test_practice_a_cannot_retrieve_practice_b_patients_via_api(): void
    {
        $this->actingAs($this->userA);

        // User A accessing Patient B odontogram details should be denied (403)
        $response = $this->get("/patients/{$this->patientB->id}/odontogram");
        $response->assertStatus(403);

        // User A accessing Patient A odontogram should succeed (200)
        $responseA = $this->get("/patients/{$this->patientA->id}/odontogram");
        $responseA->assertStatus(200);
    }

    public function test_practice_a_cannot_retrieve_practice_b_examinations_or_odontogram_data(): void
    {
        $this->actingAs($this->userA);

        // Index examinations for Patient B
        $response = $this->get("/api/patients/{$this->patientB->id}/examinations");
        $response->assertStatus(403);

        // Show examination B findings
        $responseShow = $this->get("/api/examinations/{$this->examinationB->id}/odontogram");
        $responseShow->assertStatus(403);

        // Accessing own examination should succeed
        $responseOwn = $this->get("/api/examinations/{$this->examinationA->id}/odontogram");
        $responseOwn->assertStatus(200);
    }

    public function test_practice_a_cannot_mutate_practice_b_records_via_api(): void
    {
        $this->actingAs($this->userA);

        // Attempt to update finding on Practice B examination
        $response = $this->postJson("/api/examinations/{$this->examinationB->id}/findings", [
            'tooth_number' => '16',
            'condition' => 'active_caries',
        ]);
        $response->assertStatus(403);

        // Attempt to update tooth on Patient B
        $responseTooth = $this->postJson("/api/patients/{$this->patientB->id}/teeth", [
            'tooth_number' => '16',
            'condition' => 'crown',
        ]);
        $responseTooth->assertStatus(403);

        // Attempt to add procedure to Treatment Plan B
        $responsePlan = $this->postJson("/api/treatment-plans/{$this->treatmentPlanB->id}/procedures", [
            'tooth_number_fdi' => 16,
            'procedure_code' => 'D2140',
        ]);
        $responsePlan->assertStatus(403);
    }

    public function test_practice_a_cannot_access_practice_b_finance_ledger(): void
    {
        $this->actingAs($this->userA);

        $response = $this->get('/finance');
        $response->assertStatus(200);

        // Ensure Practice B invoice and expenses are NOT present in Practice A view payload
        $invoices = collect($response->viewData('page')['props']['invoices'] ?? []);
        $this->assertTrue($invoices->pluck('id')->contains($this->invoiceA->id));
        $this->assertFalse($invoices->pluck('id')->contains($this->invoiceB->id));
    }

    public function test_policy_prevents_cross_tenant_access_for_appointments_and_invoices(): void
    {
        // User A cannot view or update Appointment B
        $this->assertFalse($this->userA->can('view', $this->appointmentB));
        $this->assertFalse($this->userA->can('update', $this->appointmentB));

        // User A CAN view and update Appointment A
        $this->assertTrue($this->userA->can('view', $this->appointmentA));
        $this->assertTrue($this->userA->can('update', $this->appointmentA));

        // User A cannot view or update Invoice B
        $this->assertFalse($this->userA->can('view', $this->invoiceB));
        $this->assertFalse($this->userA->can('update', $this->invoiceB));

        // User A CAN view and update Invoice A
        $this->assertTrue($this->userA->can('view', $this->invoiceA));
        $this->assertTrue($this->userA->can('update', $this->invoiceA));
    }

    public function test_operatory_practice_relationship_resolves_correctly(): void
    {
        $this->assertEquals($this->practiceA->id, $this->operatoryA->practice->id);
        $this->assertEquals($this->practiceB->id, $this->operatoryB->practice->id);
    }
}
