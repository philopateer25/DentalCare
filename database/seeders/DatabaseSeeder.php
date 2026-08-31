<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::firstOrCreate(
            ['email' => 'admin@dentalcare.com'],
            [
                'name' => 'Dr. Michael Admin',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );

        $this->call([
            DentalInventorySeeder::class,
            DentalLabSeeder::class,
            DentalFinanceSeeder::class,
            DentalStaffAndInsuranceSeeder::class,
        ]);
    }
}
