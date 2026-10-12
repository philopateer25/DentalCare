<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$admin = App\Models\User::where('email', 'admin@clinic.com')->first();
auth()->login($admin);
$practice = $admin->practice;
\Filament\Facades\Filament::setTenant($practice);

$widget1 = new App\Filament\Widgets\AdminTopTreatmentsWidget();
$table1 = $widget1->table(new \Filament\Tables\Table($widget1));
$records1 = $table1->getQuery()->get();
foreach ($records1 as $r) {
    echo "Top Treatment: '{$r->description}', times: {$r->times_performed}, rev: {$r->generated_revenue}\n";
}
