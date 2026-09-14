<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Patient;
use App\Models\Appointment;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Practice;
use Illuminate\Support\Carbon;

$practice = Practice::first();
if (!$practice) {
    echo "No practice found.\n";
    exit;
}
$practiceId = $practice->id;
$branchId = \App\Models\Branch::where('practice_id', $practiceId)->first()->id ?? 1;

echo "Seeding Dashboard Data for Practice ID: $practiceId...\n";

// Seed 10 Patients
$patients = [];
for ($i = 1; $i <= 10; $i++) {
    $patients[] = Patient::create([
        'practice_id' => $practiceId,
        'branch_id' => $branchId,
        'first_name' => "Fake",
        'last_name' => "Patient $i",
        'phone' => "010000000$i",
        'status' => 'active',
        'gender' => $i % 2 == 0 ? 'male' : 'female',
    ]);
}

// Seed Appointments (Some past, some future)
$statuses = ['scheduled', 'completed', 'cancelled', 'no_show'];
foreach ($patients as $index => $patient) {
    // Past appointment
    $operatoryId = \App\Models\Operatory::where('branch_id', $branchId)->first()->id ?? 1;
    $doctorId = \App\Models\StaffMember::where('practice_id', $practiceId)->where('role', 'doctor')->first()->id ?? 1;
    Appointment::create([
        'practice_id' => $practiceId,
        'branch_id' => $branchId,
        'operatory_id' => $operatoryId,
        'doctor_id' => $doctorId,
        'patient_id' => $patient->id,
        'start_time' => Carbon::now()->subDays(rand(1, 10))->setHour(rand(9, 16)),
        'end_time' => Carbon::now()->subDays(rand(1, 10))->setHour(rand(10, 17)),
        'status' => 'completed',
        'procedure_name' => 'General Checkup',
    ]);

    // Future appointment
    Appointment::create([
        'practice_id' => $practiceId,
        'branch_id' => $branchId,
        'operatory_id' => $operatoryId,
        'doctor_id' => $doctorId,
        'patient_id' => $patient->id,
        'start_time' => Carbon::now()->addDays(rand(1, 7))->setHour(rand(9, 16)),
        'end_time' => Carbon::now()->addDays(rand(1, 7))->setHour(rand(10, 17)),
        'status' => 'scheduled',
        'procedure_name' => 'Root Canal',
    ]);
}

// Seed Finances (Invoices & Payments)
foreach ($patients as $index => $patient) {
    $amount = rand(500, 3000);
    $invoice = Invoice::create([
        'practice_id' => $practiceId,
        'patient_id' => $patient->id,
        'invoice_number' => 'INV-' . strtoupper(uniqid()),
        'issue_date' => Carbon::now()->subDays(rand(1, 10)),
        'total_amount' => $amount,
        'balance_due' => $amount / 2, // Half paid
        'status' => 'partially_paid',
    ]);

    Payment::create([
        'practice_id' => $practiceId,
        'invoice_id' => $invoice->id,
        'patient_id' => $patient->id,
        'amount' => $amount / 2,
        'paid_at' => Carbon::now()->subDays(rand(1, 5)),
        'payment_method' => 'cash',
    ]);
}

echo "Seeding Complete!\n";
