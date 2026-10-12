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
    App\Filament\Resources\AppointmentResource::class,
    App\Filament\Resources\ClinicExpenseResource::class,
    App\Filament\Resources\DentalLabResource::class,
    App\Filament\Resources\DoctorCommissionResource::class,
    App\Filament\Resources\InstallmentPlanResource::class,
    App\Filament\Resources\InsuranceClaimResource::class,
    App\Filament\Resources\InsuranceProviderResource::class,
    App\Filament\Resources\InventoryResource::class,
    App\Filament\Resources\InvoiceResource::class,
    App\Filament\Resources\LabOrderResource::class,
    App\Filament\Resources\OperationResource::class,
    App\Filament\Resources\PatientInsurancePolicyResource::class,
    App\Filament\Resources\PatientResource::class,
    App\Filament\Resources\PaymentResource::class,
    App\Filament\Resources\PayrollSlipResource::class,
    App\Filament\Resources\StaffMemberResource::class,
    App\Filament\Resources\SupplierResource::class,
    App\Filament\Resources\UserResource::class,
];

foreach ($resources as $resource) {
    $modelClass = $resource::getModel();
    
    // Filament method to get tenant relationship
    try {
        $reflection = new ReflectionClass($resource);
        $getTenantRelationship = $reflection->getMethod('getTenantRelationship');
        $getTenantRelationship->setAccessible(true);
        $rel = $getTenantRelationship->invoke(null, $practice);
        echo "Resource: " . class_basename($resource) . " -> OK (relationship: " . get_class($rel) . ")\n";
    } catch (\Throwable $e) {
        echo "❌ Resource: " . class_basename($resource) . " -> FAILED: " . $e->getMessage() . "\n";
    }
}
