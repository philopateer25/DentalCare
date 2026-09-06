<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            'developer',
            'doctor',
            'secretary',
            'clinic_admin',
            'super_admin',
        ];

        foreach ($roles as $role) {
            Role::firstOrCreate(['name' => $role]);
        }

        // Create Developer (No practice assigned, accesses /system)
        $dev = User::firstOrCreate([
            'email' => 'dev@clinic.com'
        ], [
            'name' => 'System Developer',
            'password' => Hash::make('password'),
            'practice_id' => null,
        ]);
        $dev->assignRole('developer');

        // Create Clinic Users (Assigned to Practice 1)
        $accounts = [
            'doctor' => [
                'name' => 'Dr. Ahmed',
                'email' => 'dr@clinic.com',
                'practice_id' => 1,
            ],
            'secretary' => [
                'name' => 'Receptionist',
                'email' => 'secretary@clinic.com',
                'practice_id' => 1,
            ],
            'clinic_admin' => [
                'name' => 'Clinic Admin',
                'email' => 'admin@clinic.com',
                'practice_id' => 1,
            ],
            'super_admin' => [
                'name' => 'Super Admin',
                'email' => 'super@clinic.com',
                'practice_id' => 1,
            ],
        ];

        foreach ($accounts as $role => $data) {
            $user = User::firstOrCreate([
                'email' => $data['email']
            ], [
                'name' => $data['name'],
                'password' => Hash::make('password'),
                'practice_id' => $data['practice_id'],
            ]);
            $user->assignRole($role);
        }
    }
}
