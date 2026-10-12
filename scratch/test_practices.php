<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

foreach (App\Models\User::all() as $u) {
    echo $u->email . " -> practice_id: " . $u->practice_id . " (" . ($u->practice?->name ?? 'NULL') . ")\n";
}
echo "\nPractices:\n";
foreach (App\Models\Practice::all() as $p) {
    echo "Practice #{$p->id}: {$p->name} (type: {$p->type})\n";
}
