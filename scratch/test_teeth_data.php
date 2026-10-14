<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "ToothFinding count: " . \App\Models\ToothFinding::count() . PHP_EOL;
echo "PatientTooth count: " . \App\Models\PatientTooth::count() . PHP_EOL;
$patient = \App\Models\Patient::first();
echo "Patient ID: " . ($patient ? $patient->id : 'none') . PHP_EOL;

if ($patient) {
    echo "Patient examinations count: " . $patient->examinations()->count() . PHP_EOL;
    foreach ($patient->examinations as $exam) {
        echo "  Exam ID {$exam->id}: " . $exam->toothFindings()->count() . " tooth findings" . PHP_EOL;
        foreach ($exam->toothFindings as $f) {
            echo "    Tooth #{$f->tooth_number_fdi}: {$f->finding_type} - Notes: {$f->notes}" . PHP_EOL;
        }
    }
    echo "Patient teeth count: " . $patient->teeth()->count() . PHP_EOL;
    foreach ($patient->teeth as $t) {
        echo "    Tooth #{$t->tooth_number}: {$t->condition} - Notes: {$t->notes}" . PHP_EOL;
    }
}
