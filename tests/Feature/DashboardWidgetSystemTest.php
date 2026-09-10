<?php

namespace Tests\Feature;

use App\Models\DoctorCommission;
use App\Models\InventoryBatch;
use App\Models\InventoryItem;
use App\Models\Invoice;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\Practice;
use App\Models\ProcedureCategory;
use App\Models\ProcedureCode;
use App\Models\ProcedureConsumption;
use App\Models\TreatmentPhase;
use App\Models\TreatmentPlan;
use App\Models\TreatmentProcedure;
use App\Models\User;
use App\Services\DashboardWidgetEngine;
use App\Services\Widgets\FinanceWidgetService;
use App\Services\Widgets\InventoryWidgetService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DashboardWidgetSystemTest extends TestCase
{
    use RefreshDatabase;

    protected Practice $practiceA;
    protected Practice $practiceB;
    protected User $adminA;
    protected User $adminB;
    protected Patient $patientA;
    protected Patient $patientB;
    protected FinanceWidgetService $financeService;
    protected InventoryWidgetService $inventoryService;
    protected DashboardWidgetEngine $engine;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'developer']);
        Role::firstOrCreate(['name' => 'super_admin']);
        Role::firstOrCreate(['name' => 'clinic_admin']);
        Role::firstOrCreate(['name' => 'doctor']);
        Role::firstOrCreate(['name' => 'secretary']);

        $this->financeService = app(FinanceWidgetService::class);
        $this->inventoryService = app(InventoryWidgetService::class);
        $this->engine = app(DashboardWidgetEngine::class);

        // Practice A
        $this->practiceA = Practice::create(['name' => 'Practice Alpha', 'currency' => 'USD']);
        $this->adminA = User::create([
            'practice_id' => $this->practiceA->id,
            'name' => 'Admin Alpha',
            'email' => 'admin@practice-a.test',
            'password' => bcrypt('password'),
        ]);
        $this->adminA->assignRole('clinic_admin');

        $this->patientA = Patient::create([
            'practice_id' => $this->practiceA->id,
            'file_number' => 'PAT-A-500',
            'first_name' => 'John',
            'last_name' => 'Alpha',
            'phone' => '1112223333',
            'gender' => 'male',
        ]);

        // Practice B
        $this->practiceB = Practice::create(['name' => 'Practice Beta', 'currency' => 'EUR']);
        $this->adminB = User::create([
            'practice_id' => $this->practiceB->id,
            'name' => 'Admin Beta',
            'email' => 'admin@practice-b.test',
            'password' => bcrypt('password'),
        ]);
        $this->adminB->assignRole('clinic_admin');

        $this->patientB = Patient::create([
            'practice_id' => $this->practiceB->id,
            'file_number' => 'PAT-B-600',
            'first_name' => 'Jane',
            'last_name' => 'Beta',
            'phone' => '4445556666',
            'gender' => 'female',
        ]);
    }

    public function test_finance_widget_totals_match_source_data(): void
    {
        // Invoice 1: $500 total, $200 paid, $300 balance due
        $invoice1 = Invoice::create([
            'practice_id' => $this->practiceA->id,
            'patient_id' => $this->patientA->id,
            'invoice_number' => 'INV-A-001',
            'subtotal' => 500.00,
            'discount_amount' => 0.00,
            'total_amount' => 500.00,
            'paid_amount' => 200.00,
            'balance_due' => 300.00,
            'status' => 'partially_paid',
            'issue_date' => now(),
        ]);

        \App\Models\InvoiceItem::create([
            'invoice_id' => $invoice1->id,
            'description' => 'Crown Procedure',
            'quantity' => 1,
            'unit_price' => 500.00,
            'total_price' => 500.00,
        ]);

        // Payment 1: $200
        $payment1 = Payment::create([
            'practice_id' => $this->practiceA->id,
            'invoice_id' => $invoice1->id,
            'patient_id' => $this->patientA->id,
            'amount' => 200.00,
            'payment_method' => 'cash',
            'paid_at' => now(),
        ]);

        // Doctor Commission: $80
        DoctorCommission::create([
            'doctor_id' => $this->adminA->id,
            'payment_id' => $payment1->id,
            'gross_amount' => 200.00,
            'lab_deduction_amount' => 0.00,
            'commission_percentage' => 40.00,
            'commission_amount' => 80.00,
            'status' => 'accrued',
        ]);

        $gross = $this->financeService->getGrossProduction($this->practiceA->id);
        $collectionsMonth = $this->financeService->getCollectionsThisMonth($this->practiceA->id);
        $outstanding = $this->financeService->getOutstandingBalance($this->practiceA->id);
        $commissions = $this->financeService->getDoctorCommissions($this->practiceA->id);

        $this->assertEquals(500.00, $gross);
        $this->assertEquals(200.00, $collectionsMonth);
        $this->assertEquals(300.00, $outstanding);
        $this->assertEquals(80.00, $commissions['total_commissions']);
        $this->assertCount(1, $commissions['top_earners']);
    }

    public function test_inventory_valuation_and_low_stock_detection(): void
    {
        // Item 1: Low stock (min_reorder = 10, total_stock = 4)
        $item1 = InventoryItem::create([
            'practice_id' => $this->practiceA->id,
            'name' => 'Anesthetic Cartridges',
            'min_reorder_level' => 10,
            'unit_price' => 25.00,
        ]);

        InventoryBatch::create([
            'inventory_item_id' => $item1->id,
            'batch_number' => 'BAT-001',
            'unit_cost' => 25.00,
            'quantity_received' => 20,
            'quantity_remaining' => 4,
            'expiry_date' => now()->addDays(30),
        ]);

        // Item 2: Optimal stock (min_reorder = 5, total_stock = 50)
        $item2 = InventoryItem::create([
            'practice_id' => $this->practiceA->id,
            'name' => 'Composite Syringes',
            'min_reorder_level' => 5,
            'unit_price' => 40.00,
        ]);

        InventoryBatch::create([
            'inventory_item_id' => $item2->id,
            'batch_number' => 'BAT-002',
            'unit_cost' => 40.00,
            'quantity_received' => 50,
            'quantity_remaining' => 50,
            'expiry_date' => now()->addMonths(12),
        ]);

        $lowStock = $this->inventoryService->getLowStock($this->practiceA->id);
        $expiring = $this->inventoryService->getExpiringSoon($this->practiceA->id, 60);
        $valuation = $this->inventoryService->getInventoryValuation($this->practiceA->id);

        $this->assertEquals(1, $lowStock['count']);
        $this->assertEquals('Anesthetic Cartridges', $lowStock['items'][0]['name']);
        $this->assertEquals(1, $expiring['count']);
        // Valuation = (4 * $25) + (50 * $40) = 100 + 2000 = 2100
        $this->assertEquals(2100.00, $valuation);
    }

    public function test_consumption_rate_calculation(): void
    {
        $item = InventoryItem::create([
            'practice_id' => $this->practiceA->id,
            'name' => 'Dental Sutures',
            'min_reorder_level' => 5,
        ]);

        $batch = InventoryBatch::create([
            'inventory_item_id' => $item->id,
            'batch_number' => 'BAT-SUTR-01',
            'unit_cost' => 15.00,
            'quantity_received' => 100,
            'quantity_remaining' => 70,
        ]);

        $plan = TreatmentPlan::create([
            'patient_id' => $this->patientA->id,
            'title' => 'Surgical Plan',
        ]);
        $phase = TreatmentPhase::create([
            'treatment_plan_id' => $plan->id,
            'name' => 'Surgery Phase',
        ]);
        $cat = ProcedureCategory::create(['name' => 'Surgery', 'code' => 'SURG']);
        $code = ProcedureCode::create(['category_id' => $cat->id, 'code' => 'D7210', 'title' => 'Surgical Extraction']);

        $proc = TreatmentProcedure::create([
            'treatment_phase_id' => $phase->id,
            'procedure_code_id' => $code->id,
            'fee' => 250.00,
            'net_amount' => 250.00,
            'status' => 'completed',
        ]);

        // Create 2 consumption records totaling 30 units
        ProcedureConsumption::create([
            'treatment_procedure_id' => $proc->id,
            'inventory_batch_id' => $batch->id,
            'quantity_consumed' => 20,
        ]);
        ProcedureConsumption::create([
            'treatment_procedure_id' => $proc->id,
            'inventory_batch_id' => $batch->id,
            'quantity_consumed' => 10,
        ]);

        $consumptionData = $this->inventoryService->getConsumptionRate($this->practiceA->id, 30);

        $this->assertEquals(30, $consumptionData['total_consumed']);
        $this->assertEquals(1.00, $consumptionData['daily_consumption_rate']);
    }

    public function test_widget_queries_are_tenant_safe(): void
    {
        // Create Practice B invoice and inventory item
        Invoice::create([
            'practice_id' => $this->practiceB->id,
            'patient_id' => $this->patientB->id,
            'invoice_number' => 'INV-B-999',
            'subtotal' => 9000.00,
            'total_amount' => 9000.00,
            'paid_amount' => 0.00,
            'balance_due' => 9000.00,
            'status' => 'unpaid',
            'issue_date' => now(),
        ]);

        $itemB = InventoryItem::create([
            'practice_id' => $this->practiceB->id,
            'name' => 'Beta High Cost Instrument',
            'min_reorder_level' => 10,
        ]);
        InventoryBatch::create([
            'inventory_item_id' => $itemB->id,
            'batch_number' => 'BAT-B-888',
            'unit_cost' => 500.00,
            'quantity_received' => 10,
            'quantity_remaining' => 10,
        ]);

        // Practice A queries must not include Practice B data
        $grossA = $this->financeService->getGrossProduction($this->practiceA->id);
        $valuationA = $this->inventoryService->getInventoryValuation($this->practiceA->id);

        $this->assertEquals(0.00, $grossA);
        $this->assertEquals(0.00, $valuationA);
    }

    public function test_preference_persistence_and_invalid_fallback(): void
    {
        // 1. Initial fallback for null preferences
        $defaultWidgets = $this->engine->getWidgetsForUser($this->adminA);
        $this->assertCount(8, $defaultWidgets);

        // 2. Custom preferences (hide low_stock, reorder expiring_soon to top)
        $customPrefs = [
            'expiring_soon' => ['enabled' => true, 'order' => 1, 'category' => 'inventory'],
            'low_stock' => ['enabled' => false, 'order' => 2, 'category' => 'inventory'],
        ];

        $updated = $this->engine->updateUserPreferences($this->adminA, $customPrefs);
        $this->assertFalse($updated['low_stock']['enabled']);
        $this->assertEquals(1, $updated['expiring_soon']['order']);

        $activeWidgets = $this->engine->getWidgetsForUser($this->adminA->fresh());
        $this->assertArrayHasKey('expiring_soon', $activeWidgets);
        $this->assertArrayNotHasKey('low_stock', $activeWidgets);

        // 3. Invalid preference fallback
        $this->adminA->update(['dashboard_widgets' => ['invalid_malformed_string']]);
        $fallbackWidgets = $this->engine->getWidgetsForUser($this->adminA->fresh());
        $this->assertCount(8, $fallbackWidgets);
    }

    public function test_dashboard_widget_controller_endpoints(): void
    {
        $this->actingAs($this->adminA);

        $response = $this->getJson('/api/dashboard/widgets');
        $response->assertStatus(200);
        $response->assertJsonStructure(['widgets', 'preferences']);

        $updateResponse = $this->postJson('/api/dashboard/widgets/preferences', [
            'preferences' => [
                'gross_production' => ['enabled' => true, 'order' => 1],
                'low_stock' => ['enabled' => false, 'order' => 2],
            ],
        ]);

        $updateResponse->assertStatus(200);
        $this->assertFalse($this->adminA->fresh()->dashboard_widgets['low_stock']['enabled']);
    }
}
