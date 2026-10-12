<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$patient = App\Models\Patient::first();
$external = $patient->externalTreatments()->create([
    'procedure_title' => 'Dental Implant (Straumann)',
    'tooth_number_fdi' => 46,
    'performed_year' => '2023',
    'clinic_or_doctor_name' => 'Alexandria Dental Center',
    'notes' => 'Bone graft was placed simultaneously.',
]);

echo "Created External Treatment ID: " . $external->id . PHP_EOL;
echo "Patient External Treatments Count: " . $patient->externalTreatments()->count() . PHP_EOL;
