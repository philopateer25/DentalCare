<?php

namespace Database\Seeders;

use App\Models\Practice;
use App\Models\User;
use Illuminate\Database\Seeder;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $practice = Practice::create([
            'name' => 'El-Nile Dental Clinic & Polyclinic',
            'tax_id' => '987-654-321',
            'currency' => 'EGP',
            'timezone' => 'Africa/Cairo',
        ]);

        User::create([
            'practice_id' => $practice->id,
            'name' => 'Dr. Admin Lead',
            'email' => 'admin@dentalcare.eg',
            'password' => bcrypt('password123'),
            'role' => 'admin',
            'phone' => '01000000000',
        ]);
    }
}
