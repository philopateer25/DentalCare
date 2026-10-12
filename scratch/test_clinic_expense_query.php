<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$user = App\Models\User::where('email', 'admin@clinic.com')->first();
auth()->login($user);
$practice = App\Models\Practice::first();
\Filament\Facades\Filament::setTenant($practice);

$expenses = $practice->clinicExpenses()->get();
echo "Clinic Expenses for Practice: " . $expenses->count() . " records\n";

$resource = App\Filament\Resources\ClinicExpenseResource::class;
$query = $resource::getEloquentQuery();
echo "ClinicExpenseResource query count: " . $query->count() . "\n";
