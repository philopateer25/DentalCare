<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$user = App\Models\User::where('email', 'admin@clinic.com')->first();
auth()->login($user);
$practice = App\Models\Practice::first();
\Filament\Facades\Filament::setTenant($practice);

$supplier = App\Models\Supplier::first();

echo "Testing creating item via InventoryItemsRelationManager CreateAction mutateFormDataUsing...\n";

$data = [
    'name' => 'Kerr OptiBond Universal Adhesive 5ml',
    'category' => 'Operative & Restorative',
    'unit' => 'bottles',
    'unit_price' => 75.00,
];

// Replicate mutateFormDataUsing logic
$data['practice_id'] = \Filament\Facades\Filament::getTenant()?->id ?? auth()->user()?->practice_id;

$item = $supplier->inventoryItems()->create($data);
echo "✔ Created item #{$item->id} under Supplier #{$supplier->id} with practice_id: {$item->practice_id}\n";
$item->delete();
echo "✔ Cleaned up test item.\n";
