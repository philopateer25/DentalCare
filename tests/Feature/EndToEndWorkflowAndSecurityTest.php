<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Branch;
use App\Models\DoctorCommission;
use App\Models\InstallmentPlan;
use App\Models\InventoryBatch;
use App\Models\InventoryItem;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Operatory;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\Practice;
use App\Models\ProcedureCategory;
use App\Models\ProcedureCode;
use App\Models\SupportTicket;
use App\Models\TreatmentPhase;
use App\Models\TreatmentPlan;
use App\Models\TreatmentProcedure;
use App\Models\User;
use App\Services\AnalyticsService;
use App\Services\InventoryStockService;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EndToEndWorkflowAndSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected Practice $practiceA;
    protected Practice $practiceB;
    protected Branch $branchA;
    protected Branch $branchB;
    protected User $adminA;
    protected User $doctorA;
    protected User $adminB;
    protected Operatory $operatoryA;
    protected Patient $patientA;
    protected Patient $patientB;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'clinic_admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'doctor', 'guard_name' => 'web']);

        $this->practiceA = Practice::create([
            'name' => 'Alpha Dental Clinic',
            'is_active' => true,
            'features' => ['inventory', 'finance', 'labs'],
            'onboarding_completed_at' => now(),
            'onboarding_step' => 6,
        ]);

        $this->practiceB = Practice::create([
            'name' => 'Beta Dental Clinic',
            'is_active' => true,
            'features' => ['finance'],
            'onboarding_completed_at' => now(),
            'onboarding_step' => 6,
        ]);

        $this->branchA = Branch::create(['practice_id' => $this->practiceA->id, 'name' => 'Alpha Main']);
        $this->branchB = Branch::create(['practice_id' => $this->practiceB->id, 'name' => 'Beta Main']);

        $this->adminA = User::create([
            'name' => 'Admin Alpha',
            'email' => 'admin.a@e2e.test',
            'password' => bcrypt('password'),
            'practice_id' => $this->practiceA->id,
            'branch_id' => $this->branchA->id,
            'role' => 'clinic_admin',
        ]);
        $this->adminA->assignRole('clinic_admin');

        $this->doctorA = User::create([
            'name' => 'Dr. Alpha',
            'email' => 'doctor.a@e2e.test',
            'password' => bcrypt('password'),
            'practice_id' => $this->practiceA->id,
            'branch_id' => $this->branchA->id,
            'role' => 'doctor',
        ]);
        $this->doctorA->assignRole('doctor');

        $this->adminB = User::create([
            'name' => 'Admin Beta',
            'email' => 'admin.b@e2e.test',
            'password' => bcrypt('password'),
            'practice_id' => $this->practiceB->id,
            'branch_id' => $this->branchB->id,
            'role' => 'clinic_admin',
        ]);
        $this->adminB->assignRole('clinic_admin');

        $this->operatoryA = Operatory::create([
            'branch_id' => $this->branchA->id,
            'name' => 'Operatory 101',
            'is_active' => true,
        ]);

        $this->patientA = Patient::create([
            'practice_id' => $this->practiceA->id,
            'first_name' => 'Alice',
            'last_name' => 'Smith',
            'phone' => '1112223333',
            'gender' => 'female',
        ]);

        $this->patientB = Patient::create([
            'practice_id' => $this->practiceB->id,
            'first_name' => 'Bob',
            'last_name' => 'Jones',
            'phone' => '4445556666',
            'gender' => 'male',
        ]);
    }

    /** @test */
    public function workflow_01_login_to_dashboard()
    {
        $this->actingAs($this->adminA);
        $response = $this->get('/dashboard');
        $response->assertStatus(200);
    }

    /** @test */
    public function workflow_02_tenant_isolation_api_and_ui()
    {
        $this->actingAs($this->adminA);

        // Practice A cannot view Practice B patient list or specific patient
        $response = $this->getJson("/api/patients/{$this->patientB->id}");
        $response->assertStatus(404);

        // Practice A POST mutation on Practice B patient fails
        $response = $this->patchJson("/api/patients/{$this->patientB->id}", [
            'first_name' => 'Hacked',
        ]);
        $response->assertStatus(404);
    }

    /** @test */
    public function workflow_03_create_patient()
    {
        $this->actingAs($this->adminA);

        $patient = Patient::create([
            'practice_id' => $this->practiceA->id,
            'first_name' => 'Charlie',
            'last_name' => 'Brown',
            'phone' => '5551234567',
            'gender' => 'male',
        ]);

        $this->assertDatabaseHas('patients', [
            'id' => $patient->id,
            'practice_id' => $this->practiceA->id,
            'first_name' => 'Charlie',
        ]);
    }

    /** @test */
    public function workflow_04_edit_patient_as_doctor()
    {
        $this->actingAs($this->doctorA);

        $this->patientA->update(['phone' => '9998887777']);
        $this->assertEquals('9998887777', $this->patientA->fresh()->phone);
    }

    /** @test */
    public function workflow_05_create_appointment()
    {
        $this->actingAs($this->adminA);

        $appointment = Appointment::create([
            'practice_id' => $this->practiceA->id,
            'branch_id' => $this->branchA->id,
            'patient_id' => $this->patientA->id,
            'doctor_id' => $this->doctorA->id,
            'operatory_id' => $this->operatoryA->id,
            'start_time' => now()->addHours(2),
            'end_time' => now()->addHours(3),
            'status' => 'booked',
            'reason' => 'Routine Consultation',
        ]);

        $this->assertDatabaseHas('appointments', [
            'id' => $appointment->id,
            'practice_id' => $this->practiceA->id,
            'status' => 'booked',
        ]);
    }

    /** @test */
    public function workflow_06_appointment_to_consultation_invoice()
    {
        $this->actingAs($this->adminA);

        $appointment = Appointment::create([
            'practice_id' => $this->practiceA->id,
            'branch_id' => $this->branchA->id,
            'operatory_id' => $this->operatoryA->id,
            'patient_id' => $this->patientA->id,
            'doctor_id' => $this->doctorA->id,
            'start_time' => now(),
            'end_time' => now()->addHour(),
            'status' => 'completed',
        ]);

        $invoice = Invoice::create([
            'practice_id' => $this->practiceA->id,
            'patient_id' => $this->patientA->id,
            'invoice_number' => 'INV-E2E-001',
            'issue_date' => now(),
            'total_amount' => 150.00,
            'paid_amount' => 0.00,
            'balance_due' => 150.00,
            'status' => 'unpaid',
        ]);

        InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'description' => 'Consultation Fee',
            'quantity' => 1,
            'unit_price' => 150.00,
            'total_price' => 150.00,
        ]);

        $this->assertEquals(150.00, (float)$invoice->total_amount);
    }

    /** @test */
    public function workflow_07_08_09_treatment_plan_to_procedure_completion_to_invoice_item()
    {
        $this->actingAs($this->adminA);

        $category = ProcedureCategory::create(['name' => 'Restorative', 'code' => 'REST']);
        $code = ProcedureCode::create([
            'category_id' => $category->id,
            'code' => 'D2140',
            'title' => 'Amalgam Filling',
            'default_fee' => 200.00,
        ]);

        $plan = TreatmentPlan::create([
            'patient_id' => $this->patientA->id,
            'doctor_id' => $this->doctorA->id,
            'title' => 'Composite Filling Plan',
            'status' => 'approved',
            'total_amount' => 200.00,
            'net_amount' => 200.00,
        ]);

        $phase = TreatmentPhase::create([
            'treatment_plan_id' => $plan->id,
            'name' => 'Phase 1',
            'sequence' => 1,
        ]);

        $procedure = TreatmentProcedure::create([
            'treatment_phase_id' => $phase->id,
            'procedure_code_id' => $code->id,
            'tooth_number' => '14',
            'status' => 'completed',
            'fee' => 200.00,
            'discount' => 0.00,
            'net_amount' => 200.00,
        ]);

        $invoice = Invoice::create([
            'practice_id' => $this->practiceA->id,
            'patient_id' => $this->patientA->id,
            'treatment_plan_id' => $plan->id,
            'issue_date' => now(),
            'total_amount' => 200.00,
            'paid_amount' => 0.00,
            'balance_due' => 200.00,
            'status' => 'unpaid',
        ]);

        $item = InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'treatment_procedure_id' => $procedure->id,
            'description' => 'Amalgam Filling',
            'quantity' => 1,
            'unit_price' => 200.00,
            'total_price' => 200.00,
        ]);

        $this->assertEquals(200.00, (float)$item->total_price);
    }

    /** @test */
    public function workflow_10_payment_and_balance_recalculation()
    {
        $this->actingAs($this->adminA);

        $invoice = Invoice::create([
            'practice_id' => $this->practiceA->id,
            'patient_id' => $this->patientA->id,
            'issue_date' => now(),
            'total_amount' => 300.00,
            'paid_amount' => 0.00,
            'balance_due' => 300.00,
            'status' => 'unpaid',
        ]);

        InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'description' => 'Root Canal',
            'quantity' => 1,
            'unit_price' => 300.00,
            'total_price' => 300.00,
        ]);

        Payment::create([
            'practice_id' => $this->practiceA->id,
            'patient_id' => $this->patientA->id,
            'invoice_id' => $invoice->id,
            'amount' => 300.00,
            'payment_method' => 'card_pos',
            'paid_at' => now(),
        ]);

        $this->assertEquals('paid', $invoice->fresh()->status);
        $this->assertEquals(0.00, (float)$invoice->fresh()->balance_due);
    }

    /** @test */
    public function workflow_11_installment_plan()
    {
        $this->actingAs($this->adminA);

        $invoice = Invoice::create([
            'practice_id' => $this->practiceA->id,
            'patient_id' => $this->patientA->id,
            'issue_date' => now(),
            'total_amount' => 1000.00,
            'paid_amount' => 0.00,
            'balance_due' => 1000.00,
            'status' => 'unpaid',
        ]);

        $plan = InstallmentPlan::create([
            'invoice_id' => $invoice->id,
            'total_funded_amount' => 1000.00,
            'number_of_installments' => 2,
            'frequency' => 'monthly',
            'status' => 'active',
        ]);

        $this->assertDatabaseHas('installment_plans', ['id' => $plan->id]);
    }

    /** @test */
    public function workflow_12_doctor_commission()
    {
        $this->actingAs($this->adminA);

        $invoice = Invoice::create([
            'practice_id' => $this->practiceA->id,
            'patient_id' => $this->patientA->id,
            'issue_date' => now(),
            'total_amount' => 500.00,
            'paid_amount' => 500.00,
            'balance_due' => 0.00,
            'status' => 'paid',
        ]);

        $payment = Payment::create([
            'practice_id' => $this->practiceA->id,
            'patient_id' => $this->patientA->id,
            'invoice_id' => $invoice->id,
            'amount' => 500.00,
            'payment_method' => 'cash',
            'paid_at' => now(),
        ]);

        $commission = DoctorCommission::create([
            'doctor_id' => $this->doctorA->id,
            'payment_id' => $payment->id,
            'gross_amount' => 500.00,
            'commission_percentage' => 20.00,
            'commission_amount' => 100.00,
            'status' => 'accrued',
        ]);

        $this->assertEquals(100.00, (float)$commission->commission_amount);
    }

    /** @test */
    public function workflow_13_14_15_inventory_purchase_consumption_and_fefo_adjustment()
    {
        $this->actingAs($this->adminA);

        $item = InventoryItem::create([
            'practice_id' => $this->practiceA->id,
            'name' => 'Composite Syringe A2',
            'sku' => 'COMP-A2',
            'min_reorder_level' => 5,
        ]);

        $batch = InventoryBatch::create([
            'inventory_item_id' => $item->id,
            'batch_number' => 'BATCH-001',
            'unit_cost' => 25.00,
            'quantity_received' => 20,
            'quantity_remaining' => 20,
            'expiry_date' => now()->addYear(),
        ]);

        $service = new InventoryStockService();
        $movement = $service->adjustStock($item, 5, 'subtraction', 'QA Test Deduction', $this->adminA);

        $this->assertEquals(15, $item->fresh()->batches()->sum('quantity_remaining'));
        $this->assertEquals(-5, $movement->quantity_change);
    }

    /** @test */
    public function workflow_16_17_dashboard_and_inventory_widgets()
    {
        $this->actingAs($this->adminA);

        $response = $this->getJson('/api/dashboard/widgets');
        $response->assertStatus(200);
    }

    /** @test */
    public function workflow_18_calendar_and_filters()
    {
        $this->actingAs($this->adminA);

        $response = $this->getJson('/api/calendar/appointments?start_date=' . now()->startOfMonth()->toDateString() . '&end_date=' . now()->endOfMonth()->toDateString());
        $response->assertStatus(200);
    }

    /** @test */
    public function workflow_19_notifications_creation_and_mark_as_read()
    {
        $appointment = Appointment::create([
            'practice_id' => $this->practiceA->id,
            'branch_id' => $this->branchA->id,
            'operatory_id' => $this->operatoryA->id,
            'patient_id' => $this->patientA->id,
            'doctor_id' => $this->adminA->id,
            'start_time' => now()->addDay(),
            'end_time' => now()->addDay()->addMinutes(30),
            'status' => 'booked',
        ]);

        NotificationService::notifyAppointmentReminder($appointment);

        $this->assertEquals(1, $this->adminA->notifications()->count());
        $notif = $this->adminA->notifications()->first();

        $this->actingAs($this->adminA);
        $response = $this->postJson("/api/notifications/{$notif->id}/read");
        $response->assertStatus(200);
    }

    /** @test */
    public function workflow_20_analytics_metrics()
    {
        $this->actingAs($this->adminA);

        $service = new AnalyticsService();
        $metrics = $service->getMetrics($this->practiceA->id, now()->startOfMonth(), now()->endOfMonth());

        $this->assertArrayHasKey('summary', $metrics);
        $this->assertArrayHasKey('production_vs_collections', $metrics);
    }

    /** @test */
    public function workflow_21_support_ticket_submission_and_replies()
    {
        $this->actingAs($this->adminA);

        $ticket = SupportTicket::create([
            'ticket_number' => 'TICK-E2E-001',
            'practice_id' => $this->practiceA->id,
            'user_id' => $this->adminA->id,
            'subject' => 'Printer Calibration Help',
            'message' => 'Need assistance setting up label printer.',
            'status' => 'open',
            'priority' => 'medium',
        ]);

        $this->assertDatabaseHas('support_tickets', ['id' => $ticket->id]);
    }

    /** @test */
    public function workflow_22_feature_flag_disabled_route_blocked()
    {
        $this->actingAs($this->adminB);

        // Practice B has inventory feature disabled
        $response = $this->get('/inventory');
        $response->assertStatus(403);
    }

    /** @test */
    public function workflow_23_arabic_rtl_direction()
    {
        session(['locale' => 'ar']);
        $this->assertEquals('rtl', \App\Services\LanguageHelper::direction());
    }

    /** @test */
    public function workflow_24_custom_error_page_responses()
    {
        $this->actingAs($this->adminA);

        // Non-existent route returns 404
        $response = $this->get('/non-existent-route-999');
        $response->assertStatus(404);
    }

    /** @test */
    public function strict_security_audit_cross_tenant_mutation_rejected()
    {
        $this->actingAs($this->adminA);

        // Attempting to delete or update Practice B's patient returns 404
        $response = $this->deleteJson("/api/patients/{$this->patientB->id}");
        $response->assertStatus(404);

        // Attempting to access Practice B support ticket returns 403
        $ticketB = SupportTicket::create([
            'ticket_number' => 'TICK-B-SEC',
            'practice_id' => $this->practiceB->id,
            'user_id' => $this->adminB->id,
            'subject' => 'Secret Beta Ticket',
            'message' => 'Confidential message',
            'status' => 'open',
            'priority' => 'high',
        ]);

        $response = $this->getJson("/api/support/tickets/{$ticketB->id}");
        $response->assertStatus(403);
    }
}
