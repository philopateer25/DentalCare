<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$admin = App\Models\User::where('email', 'admin@clinic.com')->first();
$dr = App\Models\User::where('email', 'dr@clinic.com')->first();
$practice = App\Models\Practice::first();

foreach (['admin' => $admin, 'dr' => $dr] as $roleName => $user) {
    echo "====================================================\n";
    echo "TESTING INVENTORY & SUPPLIERS FOR {$roleName} ({$user->email})\n";
    echo "====================================================\n";
    auth()->login($user);
    \Filament\Facades\Filament::setTenant($practice);

    // 1. Check canAccess on Resources
    echo "InventoryResource canAccess: " . var_export(App\Filament\Resources\InventoryResource::canAccess(), true) . "\n";
    echo "SupplierResource canAccess: " . var_export(App\Filament\Resources\SupplierResource::canAccess(), true) . "\n";

    // 2. Check Gate permissions
    echo "Can viewAny InventoryItem? " . var_export($user->can('viewAny', App\Models\InventoryItem::class), true) . "\n";
    echo "Can create InventoryItem? " . var_export($user->can('create', App\Models\InventoryItem::class), true) . "\n";
    echo "Can viewAny Supplier? " . var_export($user->can('viewAny', App\Models\Supplier::class), true) . "\n";
    echo "Can create Supplier? " . var_export($user->can('create', App\Models\Supplier::class), true) . "\n";

    // 3. Check Form schema creation
    try {
        $invForm = App\Filament\Resources\InventoryResource::form(new \Filament\Forms\Form(new \Livewire\Component()));
        echo "✔ InventoryResource form schema built successfully\n";
    } catch (\Throwable $e) {
        echo "❌ InventoryResource form schema failed: " . $e->getMessage() . "\n";
    }

    try {
        $supForm = App\Filament\Resources\SupplierResource::form(new \Filament\Forms\Form(new \Livewire\Component()));
        echo "✔ SupplierResource form schema built successfully\n";
    } catch (\Throwable $e) {
        echo "❌ SupplierResource form schema failed: " . $e->getMessage() . "\n";
    }

    // 4. Check Table schema and query
    try {
        $invTable = App\Filament\Resources\InventoryResource::table(new \Filament\Tables\Table(new \Livewire\Component()));
        $q = App\Filament\Resources\InventoryResource::getEloquentQuery();
        $count = $q->count();
        echo "✔ InventoryResource table query succeeded ({$count} items)\n";
    } catch (\Throwable $e) {
        echo "❌ InventoryResource table failed: " . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine() . "\n";
    }

    try {
        $supTable = App\Filament\Resources\SupplierResource::table(new \Filament\Tables\Table(new \Livewire\Component()));
        $q = App\Filament\Resources\SupplierResource::getEloquentQuery();
        $count = $q->count();
        echo "✔ SupplierResource table query succeeded ({$count} suppliers)\n";
    } catch (\Throwable $e) {
        echo "❌ SupplierResource table failed: " . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine() . "\n";
    }

    // 5. Test Relation Managers
    $item = App\Models\InventoryItem::where('practice_id', $practice->id)->first();
    if ($item) {
        echo "Testing with InventoryItem #{$item->id}...\n";
        try {
            $batchesRm = new App\Filament\Resources\InventoryResource\RelationManagers\BatchesRelationManager();
            $batchesRm->ownerRecord = $item;
            $batchesTable = $batchesRm->table(new \Filament\Tables\Table($batchesRm));
            echo "✔ BatchesRelationManager query succeeded (" . $item->batches()->count() . " batches)\n";
        } catch (\Throwable $e) {
            echo "❌ BatchesRelationManager failed: " . $e->getMessage() . "\n";
        }

        try {
            $movementsRm = new App\Filament\Resources\InventoryResource\RelationManagers\MovementsRelationManager();
            $movementsRm->ownerRecord = $item;
            $movementsTable = $movementsRm->table(new \Filament\Tables\Table($movementsRm));
            echo "✔ MovementsRelationManager query succeeded (" . $item->movements()->count() . " movements)\n";
        } catch (\Throwable $e) {
            echo "❌ MovementsRelationManager failed: " . $e->getMessage() . "\n";
        }
    } else {
        echo "No InventoryItem found in practice 1 to test relation managers.\n";
    }

    $supplier = App\Models\Supplier::where('practice_id', $practice->id)->first();
    if ($supplier) {
        echo "Testing with Supplier #{$supplier->id}...\n";
        try {
            $itemsRm = new App\Filament\Resources\SupplierResource\RelationManagers\InventoryItemsRelationManager();
            $itemsRm->ownerRecord = $supplier;
            $itemsTable = $itemsRm->table(new \Filament\Tables\Table($itemsRm));
            echo "✔ InventoryItemsRelationManager query succeeded (" . $supplier->inventoryItems()->count() . " items)\n";
        } catch (\Throwable $e) {
            echo "❌ InventoryItemsRelationManager failed: " . $e->getMessage() . "\n";
        }
    } else {
        echo "No Supplier found in practice 1 to test relation managers.\n";
    }
}
