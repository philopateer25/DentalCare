<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
$user = \App\Models\User::first();
echo "User: " . $user->email . "\n";
echo "Clinic: " . $user->practice->name . "\n";
echo "License Status: " . $user->practice->license_status . "\n";
