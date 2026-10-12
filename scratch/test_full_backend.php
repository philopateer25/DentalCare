<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=======================================================\n";
echo "   DENTALCARE DEVELOPER 1 BACKEND VERIFICATION SUITE   \n";
echo "=======================================================\n\n";

$passCount = 0;
$testCount = 0;

function assertTest(bool $condition, string $message) {
    global $passCount, $testCount;
    $testCount++;
    if ($condition) {
        $passCount++;
        echo " [PASS] {$message}\n";
    } else {
        echo " [FAIL] {$message}\n";
    }
}

// 1. Clinical Presets Service
$anesthetics = \App\Services\ClinicalPresets::anestheticAgents();
assertTest(count($anesthetics) >= 4, "ClinicalPresets::anestheticAgents has " . count($anesthetics) . " items");

$tools = \App\Services\ClinicalPresets::toolsAndEquipment();
assertTest(in_array('Rubber Dam (Medium/Heavy)', $tools), "ClinicalPresets::toolsAndEquipment contains Rubber Dam");

$materials = \App\Services\ClinicalPresets::clinicalMaterials();
assertTest(in_array('Composite Shade A2', $materials), "ClinicalPresets::clinicalMaterials contains Composite Shade A2");

$hba1c = \App\Services\ClinicalPresets::hba1cLevels();
assertTest(isset($hba1c['<6.5%']), "ClinicalPresets::hba1cLevels contains <6.5%");

// 2. Patient & Detailed Medical History
$practice = \App\Models\Practice::first() ?: \App\Models\Practice::create(['name' => 'Main Practice', 'email' => 'test@practice.com', 'phone' => '01000000000']);
$patient = \App\Models\Patient::create([
    'practice_id' => $practice->id,
    'file_number' => 'VERIFY-' . time(),
    'first_name' => 'Adel',
    'last_name' => 'Ibrahim',
    'gender' => 'male',
    'phone' => '012' . rand(10000000, 99999999),
    'status' => 'active',
]);

assertTest($patient->exists, "Patient model created with ID #{$patient->id}");

$history = $patient->medicalHistory()->create([
    'blood_type' => 'AB+',
    'diabetic_status' => true,
    'diabetes_type' => 'type_2',
    'hba1c_level' => '7.6-8.5%',
    'blood_sugar_reading' => 172,
    'diabetes_medications' => ['Metformin 1000mg', 'Januvia 100mg'],
    'hypoglycemia_history' => false,
    'hypertension_status' => true,
    'blood_pressure_stage' => 'stage_2',
    'typical_blood_pressure' => '145/92 mmHg',
    'cardiac_history' => true,
    'cardiac_conditions_json' => ['Pacemaker / AICD (Caution with ultrasonic scaler)'],
    'requires_antibiotic_prophylaxis' => true,
    'bleeding_disorder' => true,
    'anticoagulant_type' => 'warfarin',
    'inr_level' => 2.45,
    'bisphosphonate_therapy' => true,
    'bisphosphonate_details' => 'Prolia (Denosumab) subcutaneous injection every 6 months',
    'dental_anxiety_level' => 'severe_phobia',
    'severe_gag_reflex' => true,
    'penicillin_allergy' => true,
]);

assertTest($history->diabetic_status === true, "MedicalHistory diabetic_status cast to boolean true");
assertTest($history->hba1c_level === '7.6-8.5%', "MedicalHistory hba1c_level stored as 7.6-8.5%");
assertTest($history->blood_sugar_reading === 172, "MedicalHistory blood_sugar_reading stored as 172 mg/dL");
assertTest(is_array($history->diabetes_medications) && count($history->diabetes_medications) === 2, "MedicalHistory diabetes_medications cast to array");
assertTest($history->inr_level == 2.45, "MedicalHistory inr_level stored as 2.45");
assertTest($history->bisphosphonate_therapy === true, "MedicalHistory bisphosphonate_therapy MRONJ flag stored");

// 3. External Treatments History
$ext = $patient->externalTreatments()->create([
    'procedure_title' => 'Dental Implant Placement',
    'tooth_number_fdi' => 36,
    'performed_year' => '2022',
    'clinic_or_doctor_name' => 'Alexandria Dental Center',
    'notes' => 'Straumann BLT 4.1x10mm placed with healing abutment',
]);

assertTest($ext->exists, "ExternalTreatment record created with ID #{$ext->id}");
assertTest($patient->externalTreatments()->count() === 1, "Patient hasMany externalTreatments relationship works");
assertTest($ext->tooth_number_fdi === 36, "ExternalTreatment tooth FDI is 36");

// 4. Treatment Plan & Procedure Execution Details
$plan = $patient->treatmentPlans()->create([
    'title' => 'Comprehensive Care Plan',
    'status' => 'approved',
    'total_amount' => 1500,
    'net_amount' => 1500,
]);

$phase = $plan->phases()->create([
    'name' => 'Phase 1 - Restorative',
    'sequence' => 1,
]);

$procCode = \App\Models\ProcedureCode::first() ?: \App\Models\ProcedureCode::create([
    'category_id' => \App\Models\ProcedureCategory::first()->id ?? 1,
    'code' => 'D2391',
    'title' => 'Resin-based composite - one surface, posterior',
    'standard_fee' => 500,
]);

$procedure = $phase->procedures()->create([
    'tooth_number_fdi' => 46,
    'surface' => 'O',
    'procedure_code_id' => $procCode->id,
    'fee' => 500,
    'discount' => 0,
    'net_amount' => 500,
    'status' => 'planned',
]);

// Execute / complete procedure with clinical details
$procedure->update([
    'status' => 'completed',
    'completed_at' => now(),
    'anesthetic_agent' => 'Articaine 4% (1:100,000 Epinephrine)',
    'anesthetic_dosage' => '2.0 carpules (3.6 ml)',
    'anesthetic_technique' => 'Inferior Alveolar Nerve Block (IANB)',
    'tools_used_json' => ['Rubber Dam (Medium/Heavy)', 'High-Speed Turbine', 'Curing Light (High Intensity)'],
    'materials_used_json' => ['Composite Shade A2', 'Universal Bonding Agent (8th Gen)', 'Phosphoric Acid Etchant 37%'],
    'hemostasis_achieved' => true,
    'chairside_notes' => 'Good isolation with rubber dam. No pulp exposure. Patient comfortable.',
]);

$freshProc = $procedure->fresh();
assertTest($freshProc->status === 'completed', "Procedure status updated to completed");
assertTest($freshProc->anesthetic_agent === 'Articaine 4% (1:100,000 Epinephrine)', "Anesthetic agent stored");
assertTest($freshProc->anesthetic_dosage === '2.0 carpules (3.6 ml)', "Anesthetic dosage stored as 2.0 carpules");
assertTest(is_array($freshProc->tools_used_json) && in_array('Rubber Dam (Medium/Heavy)', $freshProc->tools_used_json), "Tools used array cast and stored correctly");
assertTest(is_array($freshProc->materials_used_json) && in_array('Composite Shade A2', $freshProc->materials_used_json), "Materials used array cast and stored correctly");
assertTest($freshProc->hemostasis_achieved === true, "Hemostasis achieved flag is true");

// 5. Clean up test patient
$patient->delete();
echo "\n=======================================================\n";
echo "   RESULTS: {$passCount}/{$testCount} TESTS PASSED (" . round(($passCount / $testCount) * 100) . "%)\n";
echo "=======================================================\n";

if ($passCount === $testCount) {
    echo " ALL BACKEND TASKS VERIFIED SUCCESSFULLY!\n";
    exit(0);
} else {
    echo " SOME TESTS FAILED.\n";
    exit(1);
}
