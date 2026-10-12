<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$patient = \App\Models\Patient::first();
$livewire = new \App\Filament\Resources\PatientResource\RelationManagers\TreatmentPlansRelationManager();
$livewire->ownerRecord = $patient;

$form = new \Filament\Forms\Form($livewire);
$form = $livewire->form($form);

echo "SUCCESS! TreatmentPlansRelationManager form schema built without error. Components: " . count($form->getComponents()) . "\n";
