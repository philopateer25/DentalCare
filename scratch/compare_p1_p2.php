<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$models = [
    'Patient' => App\Models\Patient::class,
    'Appointment' => App\Models\Appointment::class,
    'Invoice' => App\Models\Invoice::class,
    'Payment' => App\Models\Payment::class,
    'LabOrder' => App\Models\LabOrder::class,
    'Supplier' => App\Models\Supplier::class,
    'InventoryItem' => App\Models\InventoryItem::class,
    'InventoryBatch' => App\Models\InventoryBatch::class,
];

foreach ($models as $name => $class) {
    if (in_array($name, ['InventoryBatch'])) {
        $p1 = $class::whereHas('inventoryItem', fn($q) => $q->where('practice_id', 1))->count();
        $p2 = $class::whereHas('inventoryItem', fn($q) => $q->where('practice_id', 2))->count();
    } else {
        $p1 = $class::where('practice_id', 1)->count();
        $p2 = $class::where('practice_id', 2)->count();
    }
    echo "{$name} -> Practice 1: {$p1} | Practice 2: {$p2}\n";
}
