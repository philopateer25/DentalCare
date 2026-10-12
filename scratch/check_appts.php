<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$statuses = App\Models\Appointment::distinct()->pluck('status')->toArray();
$types = App\Models\Appointment::distinct()->pluck('type')->toArray();
echo "Statuses in DB: " . implode(', ', $statuses) . PHP_EOL;
echo "Types in DB: " . implode(', ', $types) . PHP_EOL;
