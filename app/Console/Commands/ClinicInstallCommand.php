<?php

namespace App\Console\Commands;

use App\Models\Practice;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Phase 6 — first-run setup of a real clinic install (used by the Windows
 * installer). Unlike the demo seeders it creates no sample data and no known
 * passwords: one clinic, the roles, and one admin with a random password.
 * The onboarding wizard (branches, chairs…) runs at the admin's first login.
 */
class ClinicInstallCommand extends Command
{
    protected $signature = 'clinic:install
        {--clinic= : Clinic name}
        {--admin-name=Clinic Admin : Name of the first admin}
        {--admin-email= : Login email of the first admin}
        {--currency=EGP : Clinic currency}
        {--credentials-file= : Write the login to this file (the installer shows it once, then deletes it)}';

    protected $description = 'Set up a new clinic install: roles, the clinic and its first admin (random password)';

    public const ROLES = ['developer', 'doctor', 'secretary', 'clinic_admin', 'super_admin', 'accountant'];

    public function handle(): int
    {
        $clinic = trim((string) $this->option('clinic'));
        $email = strtolower(trim((string) $this->option('admin-email')));

        if ($clinic === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('Give --clinic="Clinic name" and a valid --admin-email.');

            return self::INVALID;
        }

        if (Practice::query()->exists()) {
            $this->warn('This install already has a clinic — nothing changed.');

            return self::SUCCESS;
        }

        $password = $this->password();

        DB::transaction(function () use ($clinic, $email, $password) {
            app(PermissionRegistrar::class)->forgetCachedPermissions();
            foreach (self::ROLES as $role) {
                Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
            }

            $practice = Practice::create([
                'name' => $clinic,
                'type' => 'dental',
                'currency' => strtoupper((string) $this->option('currency')) ?: 'EGP',
                'is_active' => true,
                // Not completed: the onboarding wizard runs at first login.
                'onboarding_step' => 1,
                'onboarding_completed_at' => null,
            ]);

            $admin = User::create([
                'name' => trim((string) $this->option('admin-name')) ?: 'Clinic Admin',
                'email' => $email,
                'password' => Hash::make($password),
                'practice_id' => $practice->id,
                'role' => 'clinic_admin',
                'email_verified_at' => now(),
            ]);
            $admin->assignRole('clinic_admin');
        });

        $text = "DOXamigo login\nEmail: {$email}\nPassword: {$password}\n\nChange the password after the first login.\n";

        if ($file = $this->option('credentials-file')) {
            File::ensureDirectoryExists(dirname($file));
            File::put($file, $text);
            $this->info("Clinic created. Login written to {$file}.");
        } else {
            $this->info('Clinic created.');
            $this->line($text);
        }

        return self::SUCCESS;
    }

    /** 12 characters, no look-alikes (0/O, 1/l/I), easy to read out over the phone. */
    private function password(): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789';
        $out = '';
        for ($i = 0; $i < 12; $i++) {
            $out .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }

        return Str::substr($out, 0, 4).'-'.Str::substr($out, 4, 4).'-'.Str::substr($out, 8, 4);
    }
}
