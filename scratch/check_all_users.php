<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$users = App\Models\User::with('roles', 'practice')->get();
foreach ($users as $user) {
    echo "ID: {$user->id} | Email: {$user->email} | Practice ID: {$user->practice_id} (" . ($user->practice ? $user->practice->name : 'No Practice') . ") | Roles: " . $user->roles->pluck('name')->join(', ') . "\n";
}
