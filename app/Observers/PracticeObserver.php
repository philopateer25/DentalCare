<?php

namespace App\Observers;

use App\Models\Practice;
use Database\Seeders\DentalCatalogSeeder;
use Database\Seeders\DentalInventorySeeder;
use Database\Seeders\DentalLabSeeder;
use Database\Seeders\EyeCatalogSeeder;
use Database\Seeders\EyeInventorySeeder;
use Database\Seeders\EyeLabSeeder;

class PracticeObserver
{
    /**
     * Handle the Practice "created" event.
     */
    public function created(Practice $practice): void
    {
        if (app()->runningUnitTests() || app()->environment('testing') || config('app.env') === 'testing' || (defined('PHPUNIT_COMPOSER_INSTALL') && ! defined('RUN_SEEDERS_IN_TESTS'))) {
            return;
        }

        // The procedure catalogue is real starting data for every new clinic.
        // The other seeders are DEMO data (a doctor login with the password
        // "password", fake patients, labs, lab orders, suppliers and stock) and
        // must never land in a real clinic: developer installs or DEMO_DATA=true only.
        $demo = (bool) config('app.demo_data');

        if ($practice->type === 'ophthalmology') {
            $this->runSeeder(EyeCatalogSeeder::class, $practice);
            if ($demo) {
                $this->runSeeder(EyeInventorySeeder::class, $practice);
                $this->runSeeder(EyeLabSeeder::class, $practice);
            }
        } else {
            // Default to dental
            $this->runSeeder(DentalCatalogSeeder::class, $practice);
            if ($demo) {
                $this->runSeeder(DentalInventorySeeder::class, $practice);
                $this->runSeeder(DentalLabSeeder::class, $practice);
            }
        }
    }

    private function runSeeder(string $seederClass, Practice $practice): void
    {
        if (class_exists($seederClass)) {
            $seeder = new $seederClass;
            if (property_exists($seeder, 'targetPractice')) {
                $seeder->targetPractice = $practice;
            }
            $seeder->run();
        }
    }

    /**
     * Handle the Practice "updated" event.
     */
    public function updated(Practice $practice): void
    {
        //
    }

    /**
     * Handle the Practice "deleted" event.
     */
    public function deleted(Practice $practice): void
    {
        //
    }

    /**
     * Handle the Practice "restored" event.
     */
    public function restored(Practice $practice): void
    {
        //
    }

    /**
     * Handle the Practice "force deleted" event.
     */
    public function forceDeleted(Practice $practice): void
    {
        //
    }
}
