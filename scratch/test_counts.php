<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "InventoryItems count: " . App\Models\InventoryItem::count() . PHP_EOL;
echo "Suppliers count: " . App\Models\Supplier::count() . PHP_EOL;
echo "Batches count: " . App\Models\InventoryBatch::count() . PHP_EOL;
echo "Movements count: " . App\Models\InventoryStockMovement::count() . PHP_EOL;

$items = App\Models\InventoryItem::all();
foreach ($items as $item) {
    echo "Item: {$item->id} - {$item->name} (practice_id: {$item->practice_id})\n";
}
$suppliers = App\Models\Supplier::all();
foreach ($suppliers as $s) {
    echo "Supplier: {$s->id} - {$s->name} (practice_id: {$s->practice_id})\n";
}
