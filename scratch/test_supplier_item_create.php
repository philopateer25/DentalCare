<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$admin = App\Models\User::where('email', 'admin@clinic.com')->first();
auth()->login($admin);
$practice = App\Models\Practice::first();
\Filament\Facades\Filament::setTenant($practice);

$supplier = App\Models\Supplier::first();
if (!$supplier) {
    echo "No supplier to test with!\n";
    exit;
}

echo "Testing creating inventory item under Supplier #{$supplier->id}...\n";
try {
    // Simulate what Filament does if practice_id is omitted
    $item = $supplier->inventoryItems()->create([
        'name' => 'Test Item',
        'category' => 'Consumables',
        'unit' => 'pcs',
        'unit_price' => 10.00,
    ]);
    echo "Created item #{$item->id} with practice_id: " . var_export($item->practice_id, true) . "\n";
} catch (\Throwable $e) {
    echo "❌ Error creating item under supplier: " . $e->getMessage() . "\n";
}
