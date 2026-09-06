<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Practice;
use App\Models\User;
use App\Models\Branch;
use Illuminate\Support\Facades\Hash;

class MultiClinicSeeder extends Seeder
{
    public function run(): void
    {
        // Clinic 2: Advanced Dental Center
        $clinic2 = Practice::firstOrCreate(
            ['name' => 'Advanced Dental Center'],
            [
                'tax_id' => '987654321',
                'currency' => 'EGP',
                'timezone' => 'Africa/Cairo',
                'is_active' => true,
                'license_status' => 'active',
                'features' => ['whatsapp' => true, '3d_model' => false], // No 3D model feature
            ]
        );

        $branch2 = Branch::firstOrCreate(
            ['practice_id' => $clinic2->id, 'name' => 'Downtown Branch'],
            [
                'address' => 'Downtown Cairo',
                'phone' => '01099999999',
            ]
        );

        $this->seedUsersForPractice($clinic2->id, [
            ['name' => 'Dr. Khaled', 'email' => 'dr@advanced.com', 'role' => 'doctor'],
            ['name' => 'Ahmed Admin', 'email' => 'admin@advanced.com', 'role' => 'clinic_admin'],
        ]);


        // Clinic 3: Smile Care Clinic (Suspended License)
        $clinic3 = Practice::firstOrCreate(
            ['name' => 'Smile Care Clinic'],
            [
                'tax_id' => '112233445',
                'currency' => 'USD',
                'timezone' => 'America/New_York',
                'is_active' => true,
                'license_status' => 'suspended', // Demonstrates the middleware 403
                'features' => ['whatsapp' => false, '3d_model' => true],
            ]
        );

        $branch3 = Branch::firstOrCreate(
            ['practice_id' => $clinic3->id, 'name' => 'Main Branch'],
            [
                'address' => 'New York, NY',
                'phone' => '01088888888',
            ]
        );

        $this->seedUsersForPractice($clinic3->id, [
            ['name' => 'Dr. Jessica', 'email' => 'dr@smilecare.com', 'role' => 'doctor'],
            ['name' => 'Super Smile', 'email' => 'admin@smilecare.com', 'role' => 'clinic_admin'],
        ]);
    }

    private function seedUsersForPractice(int $practiceId, array $users): void
    {
        foreach ($users as $userData) {
            $roleName = $userData['role'];
            unset($userData['role']);
            
            $user = User::firstOrCreate(
                ['email' => $userData['email']],
                array_merge($userData, [
                    'password' => Hash::make('password'),
                    'email_verified_at' => now(),
                    'practice_id' => $practiceId,
                ])
            );
            
            $user->assignRole($roleName);
        }
    }
}
