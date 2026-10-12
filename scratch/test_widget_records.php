<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$admin = App\Models\User::where('email', 'admin@clinic.com')->first();
auth()->login($admin);
$practice = $admin->practice;
\Filament\Facades\Filament::setTenant($practice);

echo "--- 1. Testing AdminTopTreatmentsWidget Record Columns ---\n";
$widget1 = new App\Filament\Widgets\AdminTopTreatmentsWidget();
$table1 = $widget1->table(new \Filament\Tables\Table($widget1));
$records1 = $table1->getQuery()->get();
foreach ($records1 as $r) {
    echo "Record invoiceable_id: {$r->invoiceable_id}, times: {$r->times_performed}, rev: {$r->generated_revenue}\n";
    echo "  invoiceable: " . ($r->invoiceable ? get_class($r->invoiceable) : 'NULL') . "\n";
    echo "  invoiceable->name: " . var_export($r->invoiceable?->name, true) . "\n";
}

echo "\n--- 2. Testing ActionRequiredWidget Record Columns ---\n";
$widget2 = new App\Filament\Widgets\ActionRequiredWidget();
$table2 = $widget2->table(new \Filament\Tables\Table($widget2));
$records2 = $table2->getQuery()->get();
foreach ($records2 as $r) {
    echo "Invoice ID: {$r->id}, num: {$r->invoice_number}\n";
    echo "  remaining_balance: " . var_export($r->remaining_balance, true) . "\n";
    echo "  balance_due: " . var_export($r->balance_due, true) . "\n";
}

echo "\n--- 3. Testing DoctorPendingLabsWidget Record Columns ---\n";
$dr = App\Models\User::where('email', 'dr@clinic.com')->first();
auth()->login($dr);
\Filament\Facades\Filament::setTenant($practice);
$widget3 = new App\Filament\Widgets\DoctorPendingLabsWidget();
$table3 = $widget3->table(new \Filament\Tables\Table($widget3));
$records3 = $table3->getQuery()->get();
foreach ($records3 as $r) {
    echo "LabOrder ID: {$r->id}, Patient: {$r->patient?->full_name}, Lab: {$r->dentalLab?->name}, Status: {$r->status}\n";
    echo "  isOverdue(): " . var_export($r->isOverdue(), true) . "\n";
}
