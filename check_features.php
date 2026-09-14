<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
$p = \App\Models\Practice::find(1);
echo "Features:\n";
print_r($p->features);
echo "hasFeature('inventory') = " . ($p->hasFeature('inventory') ? 'YES' : 'NO') . "\n";
