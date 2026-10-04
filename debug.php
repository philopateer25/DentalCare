<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$user = \App\Models\User::where('email', 'dr@clinic.com')->first();
echo "User exists: " . ($user ? 'Yes' : 'No') . "\n";
if ($user) {
    echo "Has doctor role: " . ($user->hasRole('doctor') ? 'Yes' : 'No') . "\n";
    echo "Practice ID: " . $user->practice_id . "\n";
    
    $practices = \App\Models\Practice::pluck('id')->toArray();
    echo "Available Practices: " . implode(', ', $practices) . "\n";
}
