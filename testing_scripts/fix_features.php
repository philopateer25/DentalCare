<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$p = App\Models\Practice::first();
$p->features = ['finance', 'inventory', 'insurance', 'labs', '3d_model', 'whatsapp'];
$p->save();
echo "Done";
