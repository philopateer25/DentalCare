<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\DentalLab;
use App\Models\Practice;

class DentalLabSeeder extends Seeder
{
    public function run(): void
    {
        $practice = Practice::first();

        if ($practice) {
            $labs = [
                [
                    'name' => 'SmileCrafters Digital Lab',
                    'contact_person' => 'John Doe',
                    'phone' => '+1234567890',
                    'email' => 'lab@smilecrafters.com',
                    'address' => '123 Dental Tech Ave',
                    'is_active' => true,
                ],
                [
                    'name' => 'Precision Crown & Bridge',
                    'contact_person' => 'Jane Smith',
                    'phone' => '+0987654321',
                    'email' => 'orders@precisionlab.com',
                    'address' => '456 Technician Blvd',
                    'is_active' => true,
                ]
            ];

            foreach ($labs as $lab) {
                DentalLab::firstOrCreate(
                    ['email' => $lab['email']],
                    array_merge($lab, ['practice_id' => $practice->id])
                );
            }
        }
    }
}
