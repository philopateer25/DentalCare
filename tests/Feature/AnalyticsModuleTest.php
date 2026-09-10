<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Branch;
use App\Models\DoctorCommission;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Operatory;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\Practice;
use App\Models\ProcedureCategory;
use App\Models\ProcedureCode;
use App\Models\TreatmentPhase;
use App\Models\TreatmentPlan;
use App\Models\TreatmentProcedure;
use App\Models\User;
use App\Services\AnalyticsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AnalyticsModuleTest extends TestCase
{
    use RefreshDatabase;

    protected Practice $practiceA;
    protected Practice $practiceB;
    protected User $userA;
    protected User $userB;
    protected Branch $branchA1;
    protected Branch $branchA2;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'clinic_admin']);
        Role::firstOrCreate(['name' => 'doctor']);

        $this->practiceA = Practice::create(['name' => 'Practice Alpha', 'is_active' => true]);
        $this->practiceB = Practice::create(['name' => 'Practice Beta', 'is_active' => true]);

        $this->branchA1 = Branch::create(['practice_id' => $this->practiceA->id, 'name' => 'Branch A1 Main']);
        $this->branchA2 = Branch::create(['practice_id' => $this->practiceA->id, 'name' => 'Branch A2 North']);

        $this->userA = User::create([
            'name' => 'Admin Alpha',
            'email' => 'admin.alpha@analytics.test',
            'password' => bcrypt('password'),
            'practice_id' => $this->practiceA->id,
            'role' => 'clinic_admin',
        ]);
        $this->userA->assignRole('clinic_admin');

        $this->userB = User::create([
            'name' => 'Admin Beta',
            'email' => 'admin.beta@analytics.test',
            'password' => bcrypt('password'),
            'practice_id' => $this->practiceB->id,
            'role' => 'clinic_admin',
        ]);
        $this->userB->assignRole('clinic_admin');
    }

    /** @test */
    public function calculations_against_known_fixture_data()
    {
        $patient = Patient::create([
            'practice_id' => $this->practiceA->id,
            'branch_id' => $this->branchA1->id,
            'first_name' => 'John',
            'last_name' => 'Doe',
            'phone' => '1234567890',
            'gender' => 'male',
        ]);

        $invoice = Invoice::create([
            'practice_id' => $this->practiceA->id,
            'patient_id' => $patient->id,
            'invoice_number' => 'INV-TEST-001',
            'issue_date' => now(),
            'total_amount' => 1000.00,
            'paid_amount' => 0.00,
            'balance_due' => 1000.00,
            'status' => 'unpaid',
        ]);

        InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'description' => 'Test Procedure',
            'quantity' => 1,
            'unit_price' => 1000.00,
            'total_price' => 1000.00,
        ]);

        Payment::create([
            'practice_id' => $this->practiceA->id,
            'patient_id' => $patient->id,
            'invoice_id' => $invoice->id,
            'payment_number' => 'PAY-TEST-001',
            'amount' => 400.00,
            'payment_method' => 'cash',
            'paid_at' => now(),
        ]);

        $service = new AnalyticsService();
        $metrics = $service->getMetrics($this->practiceA->id, now()->startOfMonth(), now()->endOfMonth());

        $this->assertEquals(1000.00, $metrics['summary']['total_production']);
        $this->assertEquals(400.00, $metrics['summary']['total_collections']);
        $this->assertEquals(600.00, $metrics['summary']['outstanding_ar']);
    }

    /** @test */
    public function date_and_branch_filtering()
    {
        $doctor1 = User::create([
            'name' => 'Dr One',
            'email' => 'dr1@branch.test',
            'password' => bcrypt('password'),
            'practice_id' => $this->practiceA->id,
            'branch_id' => $this->branchA1->id,
            'role' => 'doctor',
        ]);

        $doctor2 = User::create([
            'name' => 'Dr Two',
            'email' => 'dr2@branch.test',
            'password' => bcrypt('password'),
            'practice_id' => $this->practiceA->id,
            'branch_id' => $this->branchA2->id,
            'role' => 'doctor',
        ]);

        $patient1 = Patient::create([
            'practice_id' => $this->practiceA->id,
            'first_name' => 'Branch1',
            'last_name' => 'Patient',
            'phone' => '1111111111',
            'gender' => 'male',
        ]);

        $patient2 = Patient::create([
            'practice_id' => $this->practiceA->id,
            'first_name' => 'Branch2',
            'last_name' => 'Patient',
            'phone' => '2222222222',
            'gender' => 'female',
        ]);

        $tp1 = TreatmentPlan::create([
            'patient_id' => $patient1->id,
            'doctor_id' => $doctor1->id,
            'title' => 'Plan 1',
            'status' => 'approved',
            'total_amount' => 500,
            'net_amount' => 500,
        ]);

        $tp2 = TreatmentPlan::create([
            'patient_id' => $patient2->id,
            'doctor_id' => $doctor2->id,
            'title' => 'Plan 2',
            'status' => 'approved',
            'total_amount' => 800,
            'net_amount' => 800,
        ]);

        $inv1 = Invoice::create([
            'practice_id' => $this->practiceA->id,
            'patient_id' => $patient1->id,
            'treatment_plan_id' => $tp1->id,
            'invoice_number' => 'INV-B1',
            'issue_date' => now(),
            'total_amount' => 500.00,
            'paid_amount' => 500.00,
            'balance_due' => 0.00,
            'status' => 'paid',
        ]);

        InvoiceItem::create([
            'invoice_id' => $inv1->id,
            'description' => 'Branch 1 Procedure',
            'quantity' => 1,
            'unit_price' => 500.00,
            'total_price' => 500.00,
        ]);

        $inv2 = Invoice::create([
            'practice_id' => $this->practiceA->id,
            'patient_id' => $patient2->id,
            'treatment_plan_id' => $tp2->id,
            'invoice_number' => 'INV-B2',
            'issue_date' => now(),
            'total_amount' => 800.00,
            'paid_amount' => 800.00,
            'balance_due' => 0.00,
            'status' => 'paid',
        ]);

        InvoiceItem::create([
            'invoice_id' => $inv2->id,
            'description' => 'Branch 2 Procedure',
            'quantity' => 1,
            'unit_price' => 800.00,
            'total_price' => 800.00,
        ]);

        $service = new AnalyticsService();

        // Branch A1 filter should return only 500
        $prodB1 = $service->getGrossProduction($this->practiceA->id, now()->startOfMonth(), now()->endOfMonth(), $this->branchA1->id);
        $this->assertEquals(500.00, $prodB1);

        // Branch A2 filter should return only 800
        $prodB2 = $service->getGrossProduction($this->practiceA->id, now()->startOfMonth(), now()->endOfMonth(), $this->branchA2->id);
        $this->assertEquals(800.00, $prodB2);
    }

    /** @test */
    public function tenant_isolation_in_analytics_api()
    {
        $this->actingAs($this->userA);

        $response = $this->getJson('/api/analytics');
        $response->assertStatus(200)
            ->assertJsonPath('practice_id', $this->practiceA->id);
    }

    /** @test */
    public function empty_data_handling()
    {
        $service = new AnalyticsService();
        $metrics = $service->getMetrics($this->practiceB->id, now()->startOfMonth(), now()->endOfMonth());

        $this->assertEquals(0.00, $metrics['summary']['total_production']);
        $this->assertEquals(0.00, $metrics['summary']['total_collections']);
        $this->assertEquals(0.0, $metrics['no_show_stats']['no_show_rate']);
    }
}
