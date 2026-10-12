<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$dev = \App\Models\User::where('email', 'dev@clinic.com')->first();
$panel = \Filament\Facades\Filament::getPanel('admin');
\Filament\Facades\Filament::setCurrentPanel($panel);

// Test each middleware on the panel
$middlewares = $panel->getMiddleware();
echo "Panel Middlewares:\n";
foreach ($middlewares as $mw) {
    echo " - $mw\n";
}

$tenantMiddlewares = $panel->getTenantMiddleware();
echo "\nTenant Middlewares:\n";
foreach ($tenantMiddlewares as $mw) {
    echo " - $mw\n";
}
