<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

foreach (\App\Models\Practice::all() as $p) {
    echo "Practice #{$p->id}: {$p->name} | Onboarded: " . ($p->isOnboarded() ? 'YES' : 'NO') . " | Step: {$p->onboarding_step} | Completed At: {$p->onboarding_completed_at}\n";
}
