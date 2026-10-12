<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$dev = App\Models\User::where('email', 'dev@clinic.com')->first();
$adminPanel = Filament\Facades\Filament::getPanel('admin');
$systemPanel = Filament\Facades\Filament::getPanel('system');
$practice = App\Models\Practice::first();

echo "Dev Email: {$dev->email}\n";
echo "Dev Can Access System Panel: " . ($dev->canAccessPanel($systemPanel) ? 'YES' : 'NO') . "\n";
echo "Dev Can Access Admin Panel: " . ($dev->canAccessPanel($adminPanel) ? 'YES' : 'NO') . "\n";
echo "Dev Can Access Practice #{$practice->id}: " . ($dev->canAccessTenant($practice) ? 'YES' : 'NO') . "\n";
echo "Dev Tenants Count: " . $dev->getTenants($adminPanel)->count() . "\n";
