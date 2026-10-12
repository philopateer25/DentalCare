<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$admin = App\Models\User::where('email', 'admin@clinic.com')->first();
$dr = App\Models\User::where('email', 'dr@clinic.com')->first();

echo "Admin can create ExternalTreatment: " . var_export($admin->can('create', App\Models\ExternalTreatment::class), true) . PHP_EOL;
echo "Doctor can create ExternalTreatment: " . var_export($dr->can('create', App\Models\ExternalTreatment::class), true) . PHP_EOL;

$patient = App\Models\Patient::first();
if ($patient) {
    echo "Doctor can view patient: " . var_export($dr->can('view', $patient), true) . PHP_EOL;
    $treatment = App\Models\ExternalTreatment::first() ?? new App\Models\ExternalTreatment(['patient_id' => $patient->id]);
    $treatment->patient = $patient;
    echo "Doctor can update external treatment: " . var_export($dr->can('update', $treatment), true) . PHP_EOL;
    echo "Doctor can delete external treatment: " . var_export($dr->can('delete', $treatment), true) . PHP_EOL;
}
