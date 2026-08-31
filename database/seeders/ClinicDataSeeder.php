<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Practice;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class ClinicDataSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Ensure Roles exist
        $roles = ['doctor', 'secretary', 'clinic_admin', 'super_admin'];
        foreach ($roles as $role) {
            Role::firstOrCreate(['name' => $role]);
        }

        // 2. Create Practice
        $practice = Practice::firstOrCreate(
            ['name' => 'DentalCare Main Clinic'],
            [
                'tax_id' => '123456789',
                'currency' => 'EGP',
                'timezone' => 'Africa/Cairo',
                'is_active' => true,
            ]
        );

        // 3. Create Branch
        $branch = Branch::firstOrCreate(
            ['name' => 'Downtown Branch', 'practice_id' => $practice->id],
            [
                'code' => 'DT-01',
                'phone' => '01000000001',
                'email' => 'downtown@dentalcare.com',
                'address' => 'Downtown Square, Cairo, Egypt',
                'is_active' => true,
            ]
        );

        // 4. Create Users
        $password = Hash::make('password');

        // Super Admin (already created in RoleSeeder, but ensure Practice is linked)
        $admin = User::firstOrCreate(
            ['email' => 'admin@clinic.com'],
            [
                'name' => 'Super Admin',
                'password' => $password,
                'role' => 'super_admin',
                'practice_id' => $practice->id,
                'branch_id' => $branch->id,
            ]
        );
        $admin->assignRole('super_admin');

        // Doctors
        $doctor1 = User::firstOrCreate(
            ['email' => 'dr.ahmed@clinic.com'],
            [
                'name' => 'Dr. Ahmed Hassan',
                'password' => $password,
                'role' => 'dentist',
                'practice_id' => $practice->id,
                'branch_id' => $branch->id,
            ]
        );
        $doctor1->assignRole('doctor');

        $doctor2 = User::firstOrCreate(
            ['email' => 'dr.sara@clinic.com'],
            [
                'name' => 'Dr. Sara Mahmoud',
                'password' => $password,
                'role' => 'dentist',
                'practice_id' => $practice->id,
                'branch_id' => $branch->id,
            ]
        );
        $doctor2->assignRole('doctor');

        // Receptionist
        $receptionist = User::firstOrCreate(
            ['email' => 'reception@clinic.com'],
            [
                'name' => 'Front Desk (Mona)',
                'password' => $password,
                'role' => 'secretary',
                'practice_id' => $practice->id,
                'branch_id' => $branch->id,
            ]
        );
        $receptionist->assignRole('secretary');
    }
}
