<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$admin = App\Models\User::where('email', 'admin@clinic.com')->first();
auth()->login($admin);
$practice = App\Models\Practice::first();
\Filament\Facades\Filament::setTenant($practice);

echo "Inventory items for Practice 1: " . App\Filament\Resources\InventoryResource::getEloquentQuery()->count() . "\n";
echo "Suppliers for Practice 1: " . App\Filament\Resources\SupplierResource::getEloquentQuery()->count() . "\n";

$dr = App\Models\User::where('email', 'dr@clinic.com')->first();
auth()->login($dr);
\Filament\Facades\Filament::setTenant($practice);

echo "Doctor can access Inventory? " . var_export(App\Filament\Resources\InventoryResource::canAccess(), true) . "\n";
echo "Doctor can access Suppliers? " . var_export(App\Filament\Resources\SupplierResource::canAccess(), true) . "\n";
echo "Doctor Inventory query count: " . App\Filament\Resources\InventoryResource::getEloquentQuery()->count() . "\n";
echo "Doctor Suppliers query count: " . App\Filament\Resources\SupplierResource::getEloquentQuery()->count() . "\n";
