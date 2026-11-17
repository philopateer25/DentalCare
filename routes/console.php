<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('license:tick')->everyTenMinutes();
Schedule::command('license:heartbeat')->everyFourHours();

// Automated Daily Clinic Backups (Protected AES-256 at 02:00 AM)
Schedule::command('backup:run')
    ->dailyAt('02:00')
    ->withoutOverlapping()
    ->runInBackground();

// Prune expired backups according to retention policy (03:00 AM)
Schedule::command('backup:clean')
    ->dailyAt('03:00')
    ->withoutOverlapping();

