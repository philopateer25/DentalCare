<?php

namespace Tests\Feature;

use App\Filament\Pages\BackupManager;
use App\Models\Practice;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schedule;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * A backup holds the whole install (every clinic on the PC + the .env key), so
 * clinics sharing a computer must not see, download or restore it.
 */
class BackupAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['developer', 'super_admin', 'clinic_admin', 'doctor', 'secretary'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }
    }

    private function practice(string $name): Practice
    {
        return Practice::create(['name' => $name, 'currency' => 'EGP', 'is_active' => true, 'onboarding_completed_at' => now(), 'onboarding_step' => 6]);
    }

    private function user(?Practice $practice, string $role): User
    {
        $user = User::create([
            'name' => ucfirst($role), 'email' => $role.uniqid().'@test.test', 'password' => bcrypt('x'),
            'practice_id' => $practice?->id, 'role' => $role,
        ]);
        $user->assignRole($role);

        return $user;
    }

    private function canOpen(User $user, Practice $tenant): bool
    {
        $this->actingAs($user);

        return BackupManager::canAccess();
    }

    #[Test]
    public function on_a_one_clinic_pc_the_clinic_admin_manages_backups_but_doctors_do_not(): void
    {
        $clinic = $this->practice('Smile');

        $this->assertTrue($this->canOpen($this->user($clinic, 'clinic_admin'), $clinic));
        $this->assertFalse($this->canOpen($this->user($clinic, 'doctor'), $clinic), 'A restore overwrites everything; doctors no longer reach it.');
        $this->assertFalse($this->canOpen($this->user($clinic, 'secretary'), $clinic));
    }

    #[Test]
    public function on_a_shared_install_clinic_admins_cannot_see_the_backups(): void
    {
        $a = $this->practice('Clinic A');
        $b = $this->practice('Clinic B');
        $adminB = $this->user($b, 'clinic_admin');

        $this->assertFalse($this->canOpen($adminB, $b));
        $this->assertFalse($this->canOpen($this->user($a, 'clinic_admin'), $a));

        // The page itself refuses (not only the menu).
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($adminB)->get(BackupManager::getUrl(tenant: $b))->assertForbidden();

        // System admins still manage the install's backups.
        $this->assertTrue($this->canOpen($this->user($a, 'super_admin'), $a));
        $this->assertTrue($this->canOpen($this->user(null, 'developer'), $a));
    }

    #[Test]
    public function the_daily_backup_is_scheduled_at_two_and_pruning_at_three(): void
    {
        $events = collect(Schedule::events())->mapWithKeys(fn ($e) => [trim(preg_replace('/^.*artisan[\'"]?\s+/', '', $e->command)) => $e->expression]);

        $this->assertSame('0 2 * * *', $events['backup:run'] ?? null);
        $this->assertSame('0 3 * * *', $events['backup:clean'] ?? null);
    }
}
