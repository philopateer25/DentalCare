<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "PATIENT 1 FINDINGS:\n";
$findings = \App\Models\ToothFinding::whereHas('examination', fn($q) => $q->where('patient_id', 1))->get();
foreach ($findings as $f) {
    echo "Exam Tooth: " . $f->tooth_number_fdi . ", Type: " . $f->finding_type . ", Notes: " . $f->notes . "\n";
}

echo "\nPATIENT 1 TEETH:\n";
$teeth = \App\Models\PatientTooth::where('patient_id', 1)->get();
foreach ($teeth as $t) {
    echo "Patient Tooth: " . $t->tooth_number . ", Condition: " . $t->condition . ", Notes: " . $t->notes . "\n";
}
