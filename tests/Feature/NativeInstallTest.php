<?php

namespace Tests\Feature;

use App\Models\Practice;
use App\Models\User;
use App\Services\HardwareFingerprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Phase 6 — native Windows install: first-run setup and a stable PC fingerprint. */
class NativeInstallTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function clinic_install_creates_one_clinic_and_an_admin_with_a_random_password(): void
    {
        $file = storage_path('framework/testing/first-login-'.uniqid().'.txt');

        $this->artisan('clinic:install', [
            '--clinic' => 'Smile Dental',
            '--admin-email' => 'Owner@Smile.test',
            '--admin-name' => 'Dr Rafik',
            '--credentials-file' => $file,
        ])->assertSuccessful();

        $practice = Practice::sole();
        $this->assertSame('Smile Dental', $practice->name);
        $this->assertFalse($practice->isOnboarded(), 'The onboarding wizard runs at first login.');

        $admin = User::sole();
        $this->assertSame('owner@smile.test', $admin->email);
        $this->assertTrue($admin->hasRole('clinic_admin'));
        $this->assertSame($practice->id, (int) $admin->practice_id);

        $text = File::get($file);
        $this->assertMatchesRegularExpression('/Password: ([A-Za-z2-9]{4}-){2}[A-Za-z2-9]{4}/', $text);
        preg_match('/Password: (\S+)/', $text, $m);
        $this->assertTrue(Hash::check($m[1], $admin->password));
        $this->assertFalse(Hash::check('password', $admin->password), 'Never the demo password.');
        File::delete($file);

        // Running it again changes nothing.
        $this->artisan('clinic:install', ['--clinic' => 'Other', '--admin-email' => 'x@y.test'])->assertSuccessful();
        $this->assertSame(1, Practice::count());
        $this->assertSame(1, User::count());
    }

    #[Test]
    public function clinic_install_needs_a_name_and_a_valid_email(): void
    {
        $this->artisan('clinic:install', ['--clinic' => 'Smile', '--admin-email' => 'not-an-email'])->assertFailed();
        $this->artisan('clinic:install', ['--admin-email' => 'owner@smile.test'])->assertFailed();
        $this->assertSame(0, Practice::count());
    }

    #[Test]
    public function license_activate_reports_failure_without_a_server(): void
    {
        config(['license.server_url' => 'https://license.invalid']);
        Http::fake(['*' => Http::response(['message' => 'Unknown enrollment code.'], 404)]);

        $this->artisan('license:activate', ['code' => 'DXA-AAAA-BBBB-CCCC'])->assertFailed();
    }

    #[Test]
    public function the_fingerprint_is_stable_and_docker_id_only_counts_inside_a_container(): void
    {
        $first = HardwareFingerprint::generate();
        $this->assertSame($first, HardwareFingerprint::generate(), 'Same PC, same fingerprint.');
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $first);

        config(['license.machine_id' => 'fixed-docker-id']);
        $withId = HardwareFingerprint::generate();

        if (HardwareFingerprint::insideContainer()) {
            $this->assertSame(hash('sha256', 'docker|fixed-docker-id'), $withId);
        } else {
            $this->assertSame($first, $withId, 'Outside Docker the .env id is ignored (no cloning by copying .env).');
        }

        if (PHP_OS_FAMILY === 'Windows' && ($guid = HardwareFingerprint::windowsMachineGuid())) {
            $this->assertSame(hash('sha256', 'windows|'.strtolower($guid)), $first, 'Windows uses MachineGuid only.');
        }
    }

    #[Test]
    public function the_passwordless_quick_login_does_not_exist_on_clinic_installs(): void
    {
        // Routes are registered for this (non-local) environment, like a clinic PC.
        $this->get('/quick-login')->assertNotFound();
        $this->get('/register')->assertRedirect(route('filament.admin.auth.login'));
        $this->assertFalse((bool) config('app.demo_data'), 'No demo data outside developer installs.');
    }
}
