<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$item = App\Models\InventoryItem::first();
echo "Testing creating batch under Item #{$item->id}...\n";
try {
    $batch = $item->batches()->create([
        'batch_number' => 'BAT-TEST-001',
        'received_date' => now(),
        'expiry_date' => now()->addYear(),
        'unit_cost' => 50.00,
        'quantity_received' => 10,
        'quantity_remaining' => 10,
    ]);
    echo "Created batch #{$batch->id}\n";
    $batch->delete();
} catch (\Throwable $e) {
    echo "❌ Error creating batch: " . $e->getMessage() . "\n";
}
