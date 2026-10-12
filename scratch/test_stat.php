<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

try {
    $stat = Filament\Widgets\StatsOverviewWidget\Stat::make('Test', '100')
        ->icon('heroicon-o-trophy');
    echo "Stat::icon exists!" . PHP_EOL;
} catch (\Throwable $e) {
    echo "Stat::icon error: " . $e->getMessage() . PHP_EOL;
}
