<?php

namespace Tests\Feature;

use App\Models\InventoryBatch;
use App\Models\InventoryItem;
use App\Models\InventoryStockMovement;
use App\Models\Practice;
use App\Models\User;
use App\Services\InventoryStockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class InventoryCostingAndAdjustmentTest extends TestCase
{
    use RefreshDatabase;

    protected Practice $practiceA;
    protected Practice $practiceB;
    protected User $adminA;
    protected User $adminB;
    protected User $doctorA;
    protected User $secretaryA;
    protected InventoryItem $itemA;
    protected InventoryItem $itemB;
    protected InventoryBatch $batchA;
    protected InventoryStockService $service;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'developer']);
        Role::firstOrCreate(['name' => 'super_admin']);
        Role::firstOrCreate(['name' => 'clinic_admin']);
        Role::firstOrCreate(['name' => 'doctor']);
        Role::firstOrCreate(['name' => 'secretary']);

        $this->service = app(InventoryStockService::class);

        // Practice A
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
            'name' => 'Doctor Alpha',
            'email' => 'doctor@practice-a.test',
            'password' => bcrypt('password'),
        ]);
        $this->doctorA->assignRole('doctor');

        $this->secretaryA = User::create([
            'practice_id' => $this->practiceA->id,
            'name' => 'Secretary Alpha',
            'email' => 'secretary@practice-a.test',
            'password' => bcrypt('password'),
        ]);
        $this->secretaryA->assignRole('secretary');

        // Practice B
        $this->practiceB = Practice::create(['name' => 'Practice Beta', 'currency' => 'EUR']);
        $this->adminB = User::create([
            'practice_id' => $this->practiceB->id,
            'name' => 'Admin Beta',
            'email' => 'admin@practice-b.test',
            'password' => bcrypt('password'),
        ]);
        $this->adminB->assignRole('clinic_admin');

        // Inventory Items
        $this->itemA = InventoryItem::create([
            'practice_id' => $this->practiceA->id,
            'name' => 'Alpha Composite Syringe',
            'category' => 'Operative & Restorative',
            'unit' => 'syringes',
            'unit_price' => 50.00,
            'min_reorder_level' => 5,
        ]);

        $this->batchA = InventoryBatch::create([
            'inventory_item_id' => $this->itemA->id,
            'batch_number' => 'BAT-A-101',
            'received_date' => now(),
            'unit_cost' => 50.00,
            'quantity_received' => 20,
            'quantity_remaining' => 20,
        ]);

        $this->itemB = InventoryItem::create([
            'practice_id' => $this->practiceB->id,
            'name' => 'Beta Anesthetic Cartridge',
            'category' => 'Local Anesthesia & Pharma',
            'unit' => 'box',
            'unit_price' => 35.00,
            'min_reorder_level' => 10,
        ]);

        InventoryBatch::create([
            'inventory_item_id' => $this->itemB->id,
            'batch_number' => 'BAT-B-201',
            'received_date' => now(),
            'unit_cost' => 35.00,
            'quantity_received' => 50,
            'quantity_remaining' => 50,
        ]);
    }

    public function test_stock_increase_adjustment_works(): void
    {
        $this->actingAs($this->adminA);

        $initialStock = $this->itemA->total_stock; // 20
        $movement = $this->service->adjustStock(
            item: $this->itemA,
            quantity: 10,
            type: 'addition',
            reason: 'Received additional stock delivery',
            user: $this->adminA,
            unitCost: 55.00
        );

        $this->assertEquals($initialStock, $movement->previous_quantity);
        $this->assertEquals(30, $movement->resulting_quantity);
        $this->assertEquals(10, $movement->quantity_change);
        $this->assertEquals(30, $this->itemA->fresh()->total_stock);
    }

    public function test_stock_decrease_adjustment_works(): void
    {
        $this->actingAs($this->adminA);

        $initialStock = $this->itemA->total_stock; // 20
        $movement = $this->service->adjustStock(
            item: $this->itemA,
            quantity: 5,
            type: 'subtraction',
            reason: 'Expired syringes discarded',
            user: $this->adminA
        );

        $this->assertEquals($initialStock, $movement->previous_quantity);
        $this->assertEquals(15, $movement->resulting_quantity);
        $this->assertEquals(-5, $movement->quantity_change);
        $this->assertEquals(15, $this->itemA->fresh()->total_stock);
    }

    public function test_quantity_is_updated_correctly(): void
    {
        $this->actingAs($this->adminA);

        $this->assertEquals(20, $this->itemA->total_stock);

        // Increase by 15
        $this->service->adjustStock($this->itemA, 15, 'addition', 'Restock', $this->adminA);
        $this->assertEquals(35, $this->itemA->fresh()->total_stock);

        // Decrease by 10
        $this->service->adjustStock($this->itemA, 10, 'subtraction', 'Usage', $this->adminA);
        $this->assertEquals(25, $this->itemA->fresh()->total_stock);
    }

    public function test_historical_movement_information_is_preserved(): void
    {
        $this->actingAs($this->adminA);

        $this->service->adjustStock($this->itemA, 5, 'addition', 'Physical Count Correction', $this->adminA, null, 50.00);

        $this->assertDatabaseHas('inventory_stock_movements', [
            'practice_id' => $this->practiceA->id,
            'inventory_item_id' => $this->itemA->id,
            'user_id' => $this->adminA->id,
            'type' => 'addition',
            'quantity_change' => 5,
            'previous_quantity' => 20,
            'resulting_quantity' => 25,
            'reason' => 'Physical Count Correction',
        ]);
    }

    public function test_invalid_adjustment_quantities_are_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->service->adjustStock($this->itemA, 0, 'addition', 'Zero quantity', $this->adminA);
    }

    public function test_negative_stock_is_prevented(): void
    {
        $this->expectException(InvalidArgumentException::class);

        // Current total stock is 20, attempting to reduce by 50
        $this->service->adjustStock($this->itemA, 50, 'subtraction', 'Excessive decrease', $this->adminA);
    }

    public function test_cost_and_value_calculation_remains_correct_after_stock_changes(): void
    {
        $this->actingAs($this->adminA);

        // Initial batch: 20 units @ $50.00 = $1,000.00
        $initialValuation = InventoryBatch::where('inventory_item_id', $this->itemA->id)
            ->selectRaw('SUM(unit_cost * quantity_remaining) as val')->value('val');
        $this->assertEquals(1000.00, $initialValuation);

        // Add 10 units @ $60.00 = $600.00
        $this->service->adjustStock($this->itemA, 10, 'addition', 'New batch', $this->adminA, null, 60.00);

        $newValuation = InventoryBatch::where('inventory_item_id', $this->itemA->id)
            ->selectRaw('SUM(unit_cost * quantity_remaining) as val')->value('val');
        $this->assertEquals(1600.00, $newValuation);
    }

    public function test_practice_a_cannot_adjust_practice_b_inventory(): void
    {
        $this->expectException(InvalidArgumentException::class);

        // Admin A trying to adjust Item B (belonging to Practice B)
        $this->service->adjustStock($this->itemB, 5, 'addition', 'Cross practice tamper', $this->adminA);
    }

    public function test_unauthorized_roles_cannot_perform_inventory_adjustments(): void
    {
        // Doctor is unauthorized to perform stock adjustments
        $this->assertFalse($this->doctorA->can('adjustStock', $this->itemA));

        // Admin and Secretary are authorized
        $this->assertTrue($this->adminA->can('adjustStock', $this->itemA));
        $this->assertTrue($this->secretaryA->can('adjustStock', $this->itemA));

        // Direct service invocation by doctor must throw InvalidArgumentException
        $this->expectException(InvalidArgumentException::class);
        $this->service->adjustStock($this->itemA, 5, 'addition', 'Bypass attempt', $this->doctorA);
    }

    public function test_existing_inventory_behavior_still_works(): void
    {
        $this->assertEquals('In Stock', $this->itemA->stock_status);
        $this->assertEquals(20, $this->itemA->total_stock);
        $this->assertEquals($this->practiceA->id, $this->itemA->practice->id);
    }
}
