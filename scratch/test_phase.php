<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$practice = \App\Models\Practice::first();
if (!$practice) {
    echo "No practice found. Creating test practice...\n";
    $practice = \App\Models\Practice::create(['name' => 'Main Clinic', 'email' => 'clinic@dental.com', 'phone' => '01000000000']);
}

$patient = \App\Models\Patient::firstOrCreate(
    ['file_number' => 'TEST-MED-001'],
    [
        'practice_id' => $practice->id,
        'first_name' => 'Kareem',
        'last_name' => 'El-Sayed',
        'gender' => 'male',
        'phone' => '01012345678',
        'status' => 'active',
    ]
);

$history = $patient->medicalHistory ?: new \App\Models\MedicalHistory(['patient_id' => $patient->id]);
$history->fill([
    'blood_type' => 'O+',
    'diabetic_status' => true,
    'diabetes_type' => 'type_2',
    'hba1c_level' => '7.6-8.5%',
    'blood_sugar_reading' => 165,
    'diabetes_medications' => ['Metformin 500mg', 'Glimepiride 2mg'],
    'hypoglycemia_history' => false,
    'hypertension_status' => true,
    'blood_pressure_stage' => 'stage_1',
    'typical_blood_pressure' => '135/85 mmHg',
    'cardiac_history' => false,
    'bleeding_disorder' => true,
    'anticoagulant_type' => 'aspirin',
    'inr_level' => 2.2,
    'bisphosphonate_therapy' => true,
    'bisphosphonate_details' => 'Fosamax 70mg oral weekly for 2 years',
    'dental_anxiety_level' => 'moderate',
    'severe_gag_reflex' => true,
    'penicillin_allergy' => true,
]);
$history->save();

echo "SUCCESS! Patient #{$patient->id} ({$patient->full_name}) medical history saved successfully.\n";
echo "Diabetic: " . ($history->diabetic_status ? 'YES' : 'NO') . " | HbA1c: {$history->hba1c_level} | Sugar: {$history->blood_sugar_reading} mg/dL\n";
echo "Blood Thinner: {$history->anticoagulant_type} | INR: {$history->inr_level}\n";
echo "MRONJ Risk (Bisphosphonate): {$history->bisphosphonate_details}\n";
