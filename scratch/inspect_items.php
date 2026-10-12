<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$items = App\Models\InvoiceItem::take(10)->get();
foreach ($items as $it) {
    echo "ID: {$it->id} | desc: '{$it->description}' | type: '{$it->invoiceable_type}' | inv_id: {$it->invoiceable_id} | proc_id: {$it->treatment_procedure_id}\n";
}
