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
            'doctor',
            'secretary',
            'clinic_admin',
            'super_admin',
        ];

        foreach ($roles as $role) {
            Role::firstOrCreate(['name' => $role]);
        }

        // Create accounts
        $accounts = [
            'doctor' => [
                'name' => 'Dr. Ahmed',
                'email' => 'dr@clinic.com'
            ],
            'secretary' => [
                'name' => 'Receptionist',
                'email' => 'secretary@clinic.com'
            ],
            'clinic_admin' => [
                'name' => 'Clinic Admin',
                'email' => 'admin@clinic.com'
            ],
            'super_admin' => [
                'name' => 'Super Admin',
                'email' => 'super@clinic.com'
            ],
        ];

        foreach ($accounts as $role => $data) {
            $user = User::firstOrCreate([
                'email' => $data['email']
            ], [
                'name' => $data['name'],
                'password' => Hash::make('password'),
            ]);
            $user->assignRole($role);
        }
    }
}
