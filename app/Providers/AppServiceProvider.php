<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // One license/clock instance per request or process, so the signed
        // payload is read and verified once rather than on every feature check.
        $this->app->singleton(\App\Services\TamperProofClock::class);
        $this->app->singleton(\App\Services\LicenseManager::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if (str_contains(config('app.url'), 'https://')) {
            \Illuminate\Support\Facades\URL::forceScheme('https');
        }
        
        \App\Models\TreatmentProcedure::observe(\App\Observers\TreatmentProcedureObserver::class);
        \App\Models\Appointment::observe(\App\Observers\AppointmentObserver::class);
        \App\Models\Practice::observe(\App\Observers\PracticeObserver::class);

        // Read-only / locked license mode. Registered first: Gate::before
        // callbacks run in order and the super-admin rule below would
        // otherwise allow every write.
        \App\Services\Licensing\ReadOnlyGuard::register();

        \Illuminate\Support\Facades\Gate::before(function ($user, $ability) {
            return $user->hasAnyRole(['developer', 'super_admin']) ? true : null;
        });

        \Filament\Tables\Table::configureUsing(function (\Filament\Tables\Table $table): void {
            $table->searchDebounce('750ms');
        });

        if (class_exists(\Illuminate\Foundation\Console\ServeCommand::class)) {
            \Illuminate\Foundation\Console\ServeCommand::$passthroughVariables = array_merge(
                \Illuminate\Foundation\Console\ServeCommand::$passthroughVariables,
                [
                    'SystemRoot',
                    'SystemDrive',
                    'windir',
                    'TEMP',
                    'TMP',
                    'USERPROFILE',
                    'LOCALAPPDATA',
                    'APPDATA',
                    'ProgramData',
                    'ALLUSERSPROFILE',
                    'COMPUTERNAME',
                    'ComSpec',
                ]
            );
        }
    }
}
