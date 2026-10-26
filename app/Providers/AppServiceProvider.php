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
        //
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

        \Illuminate\Support\Facades\Gate::before(function ($user, $ability) {
            return $user->hasAnyRole(['developer', 'super_admin']) ? true : null;
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
