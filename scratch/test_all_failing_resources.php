<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$user = App\Models\User::where('email', 'admin@clinic.com')->first();
auth()->login($user);
$practice = App\Models\Practice::first();
\Filament\Facades\Filament::setTenant($practice);

$resources = [
    App\Filament\Resources\ClinicExpenseResource::class,
    App\Filament\Resources\InsuranceClaimResource::class,
    App\Filament\Resources\InsuranceProviderResource::class,
    App\Filament\Resources\LabOrderResource::class,
    App\Filament\Resources\PatientInsurancePolicyResource::class,
    App\Filament\Resources\PayrollSlipResource::class,
    App\Filament\Resources\StaffMemberResource::class,
    App\Filament\Resources\SupplierResource::class,
];

foreach ($resources as $resource) {
    try {
        $count = $resource::getEloquentQuery()->count();
        echo "✔ " . class_basename($resource) . " query count: {$count}\n";
    } catch (\Throwable $e) {
        echo "❌ " . class_basename($resource) . " FAILED: " . $e->getMessage() . "\n";
    }
}
