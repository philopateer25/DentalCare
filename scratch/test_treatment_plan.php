<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$admin = App\Models\User::where('email', 'admin@clinic.com')->first();
$dr = App\Models\User::where('email', 'dr@clinic.com')->first();
$patient = App\Models\Patient::first();

echo "Admin can create TreatmentPlan? " . var_export($admin->can('create', App\Models\TreatmentPlan::class), true) . PHP_EOL;
echo "Doctor can create TreatmentPlan? " . var_export($dr->can('create', App\Models\TreatmentPlan::class), true) . PHP_EOL;

$plan = App\Models\TreatmentPlan::first() ?? new App\Models\TreatmentPlan(['patient_id' => $patient->id]);
$plan->patient = $patient;

echo "Doctor can update TreatmentPlan? " . var_export($dr->can('update', $plan), true) . PHP_EOL;
echo "Doctor can view TreatmentPlan? " . var_export($dr->can('view', $plan), true) . PHP_EOL;
