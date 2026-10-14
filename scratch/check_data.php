<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "PATIENT 1 APPOINTMENTS:\n";
$apts = \App\Models\Appointment::where('patient_id', 1)->get();
foreach ($apts as $a) {
    echo "Apt #{$a->id}, start_time: {$a->start_time}, status: {$a->status}, type: {$a->type}, tooth: {$a->tooth_number}, proc: {$a->procedure_name}\n";
}

echo "\nPATIENT 1 PRESCRIPTIONS:\n";
$rxs = \App\Models\Prescription::where('patient_id', 1)->get();
foreach ($rxs as $r) {
    echo "Rx #{$r->id}, med: {$r->medication_name}, date: {$r->date_prescribed}, status: {$r->status}\n";
}

echo "\nPATIENT 1 PROCEDURES:\n";
$procs = \App\Models\TreatmentProcedure::whereHas('phase.plan', fn($q) => $q->where('patient_id', 1))->get();
foreach ($procs as $p) {
    echo "Proc #{$p->id}, tooth: {$p->tooth_number_fdi}, status: {$p->status}, completed_at: {$p->completed_at}\n";
}
