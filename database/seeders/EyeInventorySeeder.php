<?php

namespace Database\Seeders;

use App\Models\InventoryItem;
use App\Models\Practice;
use App\Models\Supplier;
use Illuminate\Database\Seeder;

class EyeInventorySeeder extends Seeder
{
    public ?Practice $targetPractice = null;

    public function run(): void
    {
        $practice = $this->targetPractice ?? Practice::where('type', 'ophthalmology')->first();

        if (!$practice) {
            return;
        }

        $supplier = Supplier::firstOrCreate(
            ['name' => 'Alcon Vision Care'],
            ['practice_id' => $practice->id, 'email' => 'sales@alcon.com', 'is_active' => true]
        );

        $items = [
            ['name' => 'Systane Artificial Tears', 'sku' => 'SYS-001', 'category' => 'Drops', 'unit' => 'Bottle'],
            ['name' => 'AcrySof IQ IOL', 'sku' => 'ACR-IOL-01', 'category' => 'Implants', 'unit' => 'Piece'],
            ['name' => 'Excimer Laser Gas Mixture', 'sku' => 'EXC-GAS-99', 'category' => 'Surgical Supplies', 'unit' => 'Cylinder'],
        ];

        foreach ($items as $item) {
            $inventoryItem = InventoryItem::firstOrCreate(
                ['sku' => $item['sku'], 'practice_id' => $practice->id],
                array_merge($item, [
                    'supplier_id' => $supplier->id,
                    'min_reorder_level' => 10,
                    'reorder_quantity' => 20,
                    'unit_price' => 100.00,
                    'selling_price' => 150.00,
                    'has_expiration' => true,
                    'is_active' => true,
                ])
            );

            \App\Models\InventoryBatch::firstOrCreate(
                ['inventory_item_id' => $inventoryItem->id, 'batch_number' => 'LOT-EYE-' . $item['sku']],
                [
                    'supplier_id' => $supplier->id,
                    'quantity_received' => 50,
                    'quantity_remaining' => 50,
                    'unit_cost' => 100.00,
                    'received_date' => now(),
                    'expiry_date' => now()->addYears(2),
                ]
            );
        }
    }
}
