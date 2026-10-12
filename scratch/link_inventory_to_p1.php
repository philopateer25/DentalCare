<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "Before update:\n";
echo "Suppliers in P1: " . App\Models\Supplier::where('practice_id', 1)->count() . "\n";
echo "Suppliers in P2: " . App\Models\Supplier::where('practice_id', 2)->count() . "\n";
echo "InventoryItems in P1: " . App\Models\InventoryItem::where('practice_id', 1)->count() . "\n";
echo "InventoryItems in P2: " . App\Models\InventoryItem::where('practice_id', 2)->count() . "\n";

App\Models\Supplier::where('practice_id', 2)->update(['practice_id' => 1]);
App\Models\InventoryItem::where('practice_id', 2)->update(['practice_id' => 1]);

echo "\nAfter update:\n";
echo "Suppliers in P1: " . App\Models\Supplier::where('practice_id', 1)->count() . "\n";
echo "InventoryItems in P1: " . App\Models\InventoryItem::where('practice_id', 1)->count() . "\n";
echo "InventoryBatches in P1: " . App\Models\InventoryBatch::whereHas('inventoryItem', fn($q) => $q->where('practice_id', 1))->count() . "\n";
