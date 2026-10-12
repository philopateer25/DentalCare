<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$dr = App\Models\User::where('email', 'dr@clinic.com')->first();
auth()->login($dr);
$patient = App\Models\Patient::where('practice_id', $dr->practice_id)->first();

$rm = new App\Filament\Resources\PatientResource\RelationManagers\ExternalTreatmentsRelationManager();
$rm->ownerRecord = $patient;

echo "RM isReadOnly: " . var_export($rm->isReadOnly(), true) . PHP_EOL;
echo "RM canViewForRecord: " . var_export($rm::canViewForRecord($patient, App\Filament\Resources\PatientResource\Pages\ViewPatient::class), true) . PHP_EOL;
echo "Doctor can create ExternalTreatment: " . var_export($dr->can('create', App\Models\ExternalTreatment::class), true) . PHP_EOL;
