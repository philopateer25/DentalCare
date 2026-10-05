<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$user = App\Models\User::where('email', 'admin@dentalcare.com')->first();
if (!$user) {
    echo "User not found\n";
    exit;
}

echo "Roles: " . $user->roles->pluck('name')->join(', ') . "\n";
echo "Practice: " . $user->practice_id . "\n";
echo "HasRole: " . ($user->hasAnyRole(['doctor', 'secretary', 'clinic_admin', 'super_admin']) ? 'true' : 'false') . "\n";
