<?php

namespace App\Observers;

use App\Models\Practice;

class PracticeObserver
{
    /**
     * Handle the Practice "created" event.
     */
    public function created(Practice $practice): void
    {
        if ($practice->type === 'ophthalmology') {
            $this->runSeeder(\Database\Seeders\EyeInventorySeeder::class, $practice);
            $this->runSeeder(\Database\Seeders\EyeCatalogSeeder::class, $practice);
            $this->runSeeder(\Database\Seeders\EyeLabSeeder::class, $practice);
        } else {
            // Default to dental
            $this->runSeeder(\Database\Seeders\DentalInventorySeeder::class, $practice);
            $this->runSeeder(\Database\Seeders\DentalCatalogSeeder::class, $practice);
            $this->runSeeder(\Database\Seeders\DentalLabSeeder::class, $practice);
        }
    }

    private function runSeeder(string $seederClass, Practice $practice): void
    {
        if (class_exists($seederClass)) {
            $seeder = new $seederClass();
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

