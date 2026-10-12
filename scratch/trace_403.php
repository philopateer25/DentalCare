<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$dev = \App\Models\User::where('email', 'dev@clinic.com')->first();
$adminPanel = \Filament\Facades\Filament::getPanel('admin');
$practice = \App\Models\Practice::find(1);

echo "1. canAccessPanel: " . ($dev->canAccessPanel($adminPanel) ? 'YES' : 'NO') . "\n";
echo "2. canAccessTenant: " . ($dev->canAccessTenant($practice) ? 'YES' : 'NO') . "\n";
echo "3. practice_id on dev: " . var_export($dev->practice_id, true) . "\n";
echo "4. dev practice rel: " . var_export($dev->practice, true) . "\n";

// Let's check Filament tenant middleware:
// Filament uses \Filament\Http\Middleware\IdentifyTenant
$identifyTenant = new \Filament\Http\Middleware\IdentifyTenant();
$request = \Illuminate\Http\Request::create('/admin/1', 'GET');
$request->route = new \Illuminate\Routing\Route('GET', 'admin/{tenant}', []);
$request->route->bind($request);
$request->route->setParameter('tenant', 1);

try {
    // Check if Spatie permission or Filament policy is blocking
    echo "Roles on dev: " . $dev->getRoleNames()->join(', ') . "\n";
} catch (\Exception $e) {
    echo "Exception: " . $e->getMessage() . "\n";
}
