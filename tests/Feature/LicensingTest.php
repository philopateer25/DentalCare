<?php

namespace Tests\Feature;

use App\Exceptions\ReadOnlyModeException;
use App\Models\Patient;
use App\Models\Practice;
use App\Models\User;
use App\Services\FeatureManager;
use App\Services\LicenseManager;
use App\Services\Licensing\LicenseEnvelope;
use App\Services\Licensing\UnlockCode;
use App\Services\TamperProofClock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class LicensingTest extends TestCase
{
    use RefreshDatabase;

    private array $keys;

    private string $unlockSecret;

    private string $dir;

    private Practice $practice;

    private Practice $otherPractice;

    private User $admin;

    private Patient $patient;

    /** @var list<HttpRequest> */
    private array $sent = [];

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'clinic_admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'doctor', 'guard_name' => 'web']);

        // Fixtures are created before enforcement switches on.
        $this->practice = Practice::create([
            'name' => 'Smile Clinic', 'currency' => 'EGP', 'is_active' => true,
            'features' => ['finance', 'inventory', 'labs'],
            'onboarding_completed_at' => now(), 'onboarding_step' => 6,
        ]);
        $this->otherPractice = Practice::create([
            'name' => 'Other Clinic', 'currency' => 'EGP', 'is_active' => true,
            'onboarding_completed_at' => now(), 'onboarding_step' => 6,
        ]);
        $this->admin = User::create([
            'name' => 'Clinic Admin', 'email' => 'admin@smile.test', 'password' => bcrypt('password'),
            'practice_id' => $this->practice->id, 'role' => 'clinic_admin',
        ]);
        $this->admin->assignRole('clinic_admin');
        $this->patient = Patient::create([
            'practice_id' => $this->practice->id, 'first_name' => 'Mona', 'last_name' => 'Adel',
            'phone' => '01000000001', 'gender' => 'female',
        ]);
        Patient::create([
            'practice_id' => $this->otherPractice->id, 'first_name' => 'Outsider', 'last_name' => 'Person',
            'phone' => '01000000002', 'gender' => 'male',
        ]);

        $this->keys = LicenseEnvelope::generateKeyPair();
        $this->unlockSecret = base64_encode(random_bytes(32));
        $this->dir = storage_path('framework/testing/license-'.uniqid());
        File::ensureDirectoryExists($this->dir);

        config([
            'license.enforce' => true,
            'license.server_url' => 'https://central.test',
            'license.public_keys' => ['k1' => $this->keys['public']],
            'license.license_file' => $this->dir.'/license.enc',
            'license.clock_file' => $this->dir.'/clock.enc',
        ]);

        $this->useClock(0);
        $this->registerFakeServer();
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->dir);
        parent::tearDown();
    }

    // â”€â”€ Helpers â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    /** A clock that reports a fixed offline time, so tests can travel in time freely. */
    private function useClock(float $offlineHours): void
    {
        $clock = new class extends TamperProofClock
        {
            public float $elapsed = 0;

            public function getElapsedSecondsSinceSync(): float
            {
                return $this->elapsed;
            }

            public function isTampered(): bool
            {
                return false;
            }

            public function resetAfterSync(string $serverTime): void
            {
                $this->elapsed = 0;
            }

            public function initialise(string $serverTime): void
            {
                $this->elapsed = 0;
            }
        };
        $clock->elapsed = $offlineHours * 3600;

        $this->app->instance(TamperProofClock::class, $clock);
        $this->app->forgetInstance(LicenseManager::class);
    }

    private function license(): LicenseManager
    {
        return app(LicenseManager::class);
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'v' => 1,
            'clinic_id' => 'clinic-1',
            'issued_at' => now()->toIso8601String(),
            'status' => 'active',
            'package_tier' => 'amigo_pro',
            'features' => ['finance', 'labs'],
            'rollout_stage' => 4,
            'chair_limit' => 3,
            'xray_limit' => 1,
            'paid_until' => now()->addMonth()->toDateString(),
            'unlock_secret' => $this->unlockSecret,
        ], $overrides);
    }

    /** What the fake central app answers with next (see setUp). */
    private array|\Closure $serverOverrides = [];

    private ?string $serverSecret = null;

    private bool $serverEchoNonce = true;

    /** Fake central app that signs the payload for each request (registered once in setUp). */
    private function registerFakeServer(): void
    {
        Http::fake(function (HttpRequest $request) {
            $this->sent[] = $request;
            $extra = $this->serverOverrides instanceof \Closure ? ($this->serverOverrides)($request) : $this->serverOverrides;
            $payload = $this->payload(array_merge(['nonce' => $this->serverEchoNonce ? $request['nonce'] : 'stale-nonce'], $extra));

            return Http::response(['license' => LicenseEnvelope::sign($payload, $this->serverSecret ?? $this->keys['secret'])]);
        });
    }

    private function fakeServer(array|\Closure $overrides = [], ?string $secret = null, bool $echoNonce = true): void
    {
        $this->serverOverrides = $overrides;
        $this->serverSecret = $secret;
        $this->serverEchoNonce = $echoNonce;
    }

    private function activate(array $overrides = []): void
    {
        $this->fakeServer($overrides);
        $result = $this->license()->activate('ENROLL-1234');
        $this->assertTrue($result['success'], $result['message']);
    }

    // â”€â”€ Signature & activation â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    #[Test]
    public function license_enforce_flag_wins_even_in_local(): void
    {
        $this->app['env'] = 'local';
        $this->assertTrue($this->license()->isEnforced());

        config(['license.enforce' => null]);
        $this->assertFalse($this->license()->isEnforced());
    }

    #[Test]
    public function no_license_means_locked(): void
    {
        $this->assertSame(LicenseManager::MODE_LOCKED, $this->license()->getMode());
    }

    #[Test]
    public function valid_signed_activation_gives_full_mode(): void
    {
        $this->activate();

        $this->assertSame(LicenseManager::MODE_FULL, $this->license()->getMode());
        $this->assertSame('clinic-1', $this->license()->getSummary()['clinic_id']);
        $this->assertSame(4, $this->license()->getRolloutStage());
    }

    #[Test]
    public function reply_signed_with_another_key_is_rejected(): void
    {
        $forger = LicenseEnvelope::generateKeyPair();
        $this->fakeServer([], $forger['secret']);

        $result = $this->license()->activate('ENROLL-1234');

        $this->assertFalse($result['success']);
        $this->assertSame(LicenseManager::MODE_LOCKED, $this->license()->getMode());
    }

    #[Test]
    public function replayed_reply_with_wrong_nonce_is_rejected(): void
    {
        $this->fakeServer([], null, echoNonce: false);

        $this->assertFalse($this->license()->activate('ENROLL-1234')['success']);
    }

    #[Test]
    public function tampered_payload_fails_verification(): void
    {
        $envelope = LicenseEnvelope::sign($this->payload(), $this->keys['secret']);
        $data = json_decode(LicenseEnvelope::decode($envelope['payload']), true);
        $data['features'][] = '3d_model';
        $envelope['payload'] = LicenseEnvelope::encode(json_encode($data));

        $this->assertNull(LicenseEnvelope::verify($envelope, ['k1' => $this->keys['public']]));
    }

    #[Test]
    public function heartbeat_reply_for_another_clinic_is_ignored(): void
    {
        $this->activate();
        $this->fakeServer(['clinic_id' => 'clinic-2', 'features' => ['finance', 'labs', '3d_model']]);

        $this->assertFalse($this->license()->sendHeartbeat()['success']);
        $this->assertFalse($this->license()->allowsFeature('3d_model'));
    }

    // â”€â”€ Features â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    #[Test]
    public function license_is_the_ceiling_for_features(): void
    {
        $this->activate(['features' => ['finance', 'labs']]);

        $practice = $this->practice->fresh();
        $this->assertTrue($practice->hasFeature('finance'));
        $this->assertFalse($practice->hasFeature('inventory'), 'Enabled in the DB but not licensed.');
        $this->assertFalse(FeatureManager::isEnabled('inventory', $practice));
    }

    #[Test]
    public function clinic_cannot_enable_unlicensed_features(): void
    {
        $this->activate(['features' => ['finance', 'labs', 'insurance']]);

        $features = FeatureManager::updatePracticeFeatures($this->practice->fresh(), ['finance', 'insurance', '3d_model'], $this->admin);

        $this->assertContains('insurance', $features);
        $this->assertNotContains('3d_model', $features);
        $this->assertContains('inventory', $features, 'Unlicensed stored value is kept for a future upgrade.');
        $this->assertFalse($this->practice->fresh()->hasFeature('inventory'));
    }

    #[Test]
    public function heartbeat_updates_features_from_central(): void
    {
        $this->activate(['features' => ['finance']]);
        $this->assertFalse($this->practice->fresh()->hasFeature('labs'));

        $this->fakeServer(['features' => ['finance', 'labs']]);
        $this->assertTrue($this->license()->sendHeartbeat()['success']);

        $this->assertTrue($this->practice->fresh()->hasFeature('labs'));
    }

    // â”€â”€ Rollout stages (Phase 3) â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    #[Test]
    public function rollout_stage_reveals_modules_week_by_week(): void
    {
        $this->activate(['features' => ['finance', 'labs', 'whatsapp'], 'rollout_stage' => 1]);
        $practice = $this->practice->fresh();
        $practice->update(['features' => ['finance', 'labs', 'whatsapp']]);

        $this->assertTrue($practice->hasFeature('whatsapp'), 'Stage 1: WhatsApp reminders are on.');
        $this->assertFalse($practice->hasFeature('finance'), 'Stage 1: licensed, but finance comes at stage 2.');
        $this->assertFalse(FeatureManager::stageAllows('clinical'));

        $this->fakeServer(['features' => ['finance', 'labs', 'whatsapp'], 'rollout_stage' => 2]);
        $this->assertTrue($this->license()->sendHeartbeat()['success']);
        $this->assertTrue($practice->hasFeature('finance'));
        $this->assertFalse($practice->hasFeature('labs'), 'Labs come at stage 4.');
        $this->assertFalse(FeatureManager::stageAllows('clinical'));

        $this->fakeServer(['features' => ['finance', 'labs', 'whatsapp'], 'rollout_stage' => 3]);
        $this->license()->sendHeartbeat();
        $this->assertTrue(FeatureManager::stageAllows('clinical'));
        $this->assertFalse($practice->hasFeature('labs'));

        $this->fakeServer(['features' => ['finance', 'labs', 'whatsapp'], 'rollout_stage' => 4]);
        $this->license()->sendHeartbeat();
        $this->assertTrue($practice->hasFeature('labs'));
    }

    #[Test]
    public function a_license_without_a_stage_shows_everything_licensed(): void
    {
        $this->activate(['features' => ['finance', 'labs'], 'rollout_stage' => null]);

        $this->assertTrue($this->practice->fresh()->hasFeature('labs'));
        $this->assertTrue(FeatureManager::stageAllows('clinical'));
    }

    #[Test]
    public function saving_settings_at_an_early_stage_keeps_later_modules_switched_on(): void
    {
        $this->activate(['features' => ['finance', 'labs', 'inventory'], 'rollout_stage' => 1]);

        $features = FeatureManager::updatePracticeFeatures($this->practice->fresh(), [], $this->admin);

        $this->assertEqualsCanonicalizing(['finance', 'inventory', 'labs'], $features);
    }

    #[Test]
    public function stage_pages_follow_the_rollout_stage(): void
    {
        $this->activate(['features' => ['finance'], 'rollout_stage' => 1]);
        $tenant = ['tenant' => $this->practice->id];

        $this->actingAs($this->admin)->get(route('filament.admin.pages.coming-next', $tenant))
            ->assertOk()->assertSee('Stage 2')->assertSee('Coming next');
        $this->actingAs($this->admin)->get(route('filament.admin.pages.todays-close', $tenant))->assertForbidden();

        $this->fakeServer(['features' => ['finance'], 'rollout_stage' => 2]);
        $this->license()->sendHeartbeat();
        $this->app->forgetInstance(LicenseManager::class);

        $this->actingAs($this->admin)->get(route('filament.admin.pages.todays-close', $tenant))
            ->assertOk()->assertSee('Cash in the drawer');
    }

    // â”€â”€ Subscription steps â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    #[Test]
    public function subscription_goes_warning_then_read_only_then_locked(): void
    {
        $this->travelTo(now()->setDate(2026, 12, 1)->setTime(10, 0));
        $this->activate(['paid_until' => '2026-12-05', 'read_only_after_days' => 3, 'lock_after_days' => 10]);

        $this->assertSame(LicenseManager::MODE_FULL, $this->license()->getMode());
        $this->assertContains('billing', array_column($this->license()->getBanners(), 'id'));

        // Paid until the 5th; read-only from the 9th at midnight.
        $this->travelTo(now()->setDate(2026, 12, 8)->setTime(23, 59));
        $this->assertSame(LicenseManager::MODE_FULL, $this->license()->getMode());

        $this->travelTo(now()->setDate(2026, 12, 9)->setTime(0, 1));
        $this->assertSame(LicenseManager::MODE_READ_ONLY, $this->license()->getMode());

        $this->travelTo(now()->setDate(2026, 12, 19)->setTime(0, 1));
        $this->assertSame(LicenseManager::MODE_LOCKED, $this->license()->getMode());
    }

    #[Test]
    public function suspension_waits_until_the_next_midnight(): void
    {
        $this->travelTo(now()->setDate(2026, 12, 1)->setTime(11, 0));
        $this->activate();

        $this->fakeServer(['status' => 'suspended']);
        $this->license()->sendHeartbeat();

        $this->assertSame(LicenseManager::MODE_FULL, $this->license()->getMode());
        $this->assertContains('suspension', array_column($this->license()->getBanners(), 'id'));

        $this->travelTo(now()->setDate(2026, 12, 2)->setTime(0, 5));
        $this->assertSame(LicenseManager::MODE_READ_ONLY, $this->license()->getMode());
    }

    #[Test]
    public function revoked_license_locks_the_system(): void
    {
        $this->activate();
        $this->fakeServer(['status' => 'revoked']);
        $this->license()->sendHeartbeat();

        $this->assertSame(LicenseManager::MODE_LOCKED, $this->license()->getMode());
    }

    // â”€â”€ Read-only & locked enforcement â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    #[Test]
    public function read_only_mode_blocks_model_writes(): void
    {
        $this->travelTo(now()->setDate(2026, 12, 20)->setTime(10, 0));
        $this->activate(['paid_until' => '2026-12-01', 'read_only_after_days' => 3, 'lock_after_days' => 60]);
        $this->assertSame(LicenseManager::MODE_READ_ONLY, $this->license()->getMode());

        $this->expectException(ReadOnlyModeException::class);
        Patient::create(['practice_id' => $this->practice->id, 'first_name' => 'New', 'last_name' => 'Patient', 'phone' => '010', 'gender' => 'male']);
    }

    #[Test]
    public function read_only_mode_refuses_api_writes_but_allows_reads(): void
    {
        $this->travelTo(now()->setDate(2026, 12, 20)->setTime(10, 0));
        $this->activate(['paid_until' => '2026-12-01', 'read_only_after_days' => 3, 'lock_after_days' => 60]);

        $this->actingAs($this->admin)
            ->postJson("/api/patients/{$this->patient->id}/examinations", [])
            ->assertStatus(423)
            ->assertJson(['license_mode' => 'read_only']);

        $this->actingAs($this->admin)
            ->getJson("/api/patients/{$this->patient->id}/examinations")
            ->assertStatus(200);
    }

    #[Test]
    public function locked_mode_redirects_pages_to_the_license_page(): void
    {
        $response = $this->actingAs($this->admin)->get('/dashboard');

        $response->assertRedirect();
        $this->assertStringContainsString('/license', $response->headers->get('Location'));
    }

    /** POST /livewire/update for the named components (the payload is fake; only the lock's decision matters). */
    private function livewireUpdate(string ...$names): TestResponse
    {
        $components = array_map(fn ($name) => [
            'snapshot' => json_encode(['data' => [], 'memo' => ['id' => 'x', 'name' => $name], 'checksum' => 'x']),
            'updates' => [],
            'calls' => [],
        ], $names);

        return $this->actingAs($this->admin)->withHeader('X-Livewire', '1')->postJson('/livewire/update', ['components' => $components]);
    }

    #[Test]
    public function locked_mode_lets_the_license_page_livewire_calls_through_but_nothing_else(): void
    {
        $this->assertSame(LicenseManager::MODE_LOCKED, $this->license()->getMode());

        // License page + notifications: not stopped by the lock (no redirect loop).
        $allowed = $this->livewireUpdate('app.filament.pages.license-page', 'filament.livewire.notifications');
        $this->assertNotContains($allowed->status(), [302, 423]);

        // Any other component is refused, also when mixed with an allowed one.
        $this->livewireUpdate('app.filament.resources.patient-resource.pages.list-patients')->assertStatus(423);
        $this->livewireUpdate('app.filament.pages.license-page', 'app.filament.pages.backup-manager')->assertStatus(423);
    }

    #[Test]
    public function read_only_mode_lets_livewire_calls_through(): void
    {
        $this->travelTo(now()->setDate(2026, 12, 20)->setTime(10, 0));
        $this->activate(['paid_until' => '2026-12-01', 'read_only_after_days' => 3, 'lock_after_days' => 60]);

        $response = $this->livewireUpdate('app.filament.resources.patient-resource.pages.list-patients');
        $this->assertNotContains($response->status(), [302, 423]);
    }

    #[Test]
    public function export_works_while_locked_and_only_contains_own_clinic(): void
    {
        $this->assertSame(LicenseManager::MODE_LOCKED, $this->license()->getMode());

        $response = $this->actingAs($this->admin)->get('/license/export');
        $response->assertOk();

        $zip = new \ZipArchive;
        $this->assertTrue($zip->open($response->baseResponse->getFile()->getPathname()) === true);
        $patients = $zip->getFromName('data/patients.csv');
        $users = $zip->getFromName('data/users.csv');
        $zip->close();
        @unlink($response->baseResponse->getFile()->getPathname());

        $this->assertStringContainsString('Mona', $patients);
        $this->assertStringNotContainsString('Outsider', $patients);
        $this->assertStringNotContainsString('password', (string) $users);
    }

    #[Test]
    public function export_requires_an_admin(): void
    {
        $doctor = User::create([
            'name' => 'Doc', 'email' => 'doc@smile.test', 'password' => bcrypt('password'),
            'practice_id' => $this->practice->id, 'role' => 'doctor',
        ]);
        $doctor->assignRole('doctor');

        $this->actingAs($doctor)->get('/license/export')->assertForbidden();
    }

    // â”€â”€ Offline grace & unlock codes â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    #[Test]
    public function offline_too_long_locks_and_unlock_code_reopens(): void
    {
        $this->activate();
        $this->useClock(80); // 80h offline > 72h grace

        $this->assertSame(LicenseManager::MODE_LOCKED, $this->license()->getMode());

        $code = UnlockCode::generate($this->unlockSecret, 'clinic-1', now()->addHours(48));
        $result = $this->license()->redeemUnlockCode($code);

        $this->assertTrue($result['success'], $result['message']);
        $this->assertSame(LicenseManager::MODE_FULL, $this->license()->getMode());
    }

    #[Test]
    public function unlock_codes_are_bound_to_clinic_and_time(): void
    {
        $code = UnlockCode::generate($this->unlockSecret, 'clinic-1', now()->addHours(24));

        $this->assertNotNull(UnlockCode::verify($code, $this->unlockSecret, 'clinic-1'));
        $this->assertNotNull(UnlockCode::verify(strtolower(str_replace('-', ' ', $code)), $this->unlockSecret, 'clinic-1'));
        $this->assertNull(UnlockCode::verify($code, $this->unlockSecret, 'clinic-2'));
        $this->assertNull(UnlockCode::verify($code, base64_encode(random_bytes(32)), 'clinic-1'));
        $this->assertNull(UnlockCode::verify($code, $this->unlockSecret, 'clinic-1', now()->addHours(25)));

        $tooLong = UnlockCode::generate($this->unlockSecret, 'clinic-1', now()->addDays(30));
        $this->assertNull(UnlockCode::verify($tooLong, $this->unlockSecret, 'clinic-1'));
    }

    #[Test]
    public function unlock_code_does_not_lift_a_billing_lock(): void
    {
        $this->travelTo(now()->setDate(2026, 12, 20)->setTime(10, 0));
        $this->activate(['paid_until' => '2026-10-01']);
        $this->assertSame(LicenseManager::MODE_LOCKED, $this->license()->getMode());

        $this->license()->redeemUnlockCode(UnlockCode::generate($this->unlockSecret, 'clinic-1', now()->addHours(24)));

        $this->assertSame(LicenseManager::MODE_LOCKED, $this->license()->getMode());
    }

    // â”€â”€ Heartbeat contents, commands, server address â”€â”€

    #[Test]
    public function heartbeat_sends_health_and_counts_but_no_patient_data(): void
    {
        $this->activate();
        $this->sent = [];
        $this->fakeServer();

        $this->license()->sendHeartbeat();

        $body = $this->sent[0]->data();
        $this->assertSame('clinic-1', $body['clinic_id']);
        $this->assertArrayHasKey('disk_free_gb', $body['health']);
        $this->assertSame(2, $body['usage']['total_patients']);
        $this->assertStringNotContainsString('Mona', json_encode($body));
        $this->assertStringNotContainsString('01000000001', json_encode($body));
    }

    #[Test]
    public function remote_commands_run_once_and_report_back(): void
    {
        $this->activate();

        $commands = [
            ['id' => 'cmd-1', 'type' => 'sync'],
            ['id' => 'cmd-2', 'type' => 'rm -rf /'],
        ];
        $this->fakeServer(['commands' => $commands]);
        $this->license()->sendHeartbeat();

        // Next heartbeat carries the results; the same commands are not run again.
        $this->sent = [];
        $this->fakeServer(['commands' => $commands]);
        $this->license()->sendHeartbeat();

        $results = collect($this->sent[0]->data()['command_results'])->keyBy('id');
        $this->assertTrue($results['cmd-1']['ok']);
        $this->assertFalse($results['cmd-2']['ok']);

        $this->sent = [];
        $this->fakeServer();
        $this->license()->sendHeartbeat();
        $this->assertSame([], $this->sent[0]->data()['command_results']);
    }

    #[Test]
    public function central_app_can_move_clinics_to_a_new_https_address(): void
    {
        $this->activate(['server_url' => 'https://license.doxamigo.com']);

        $this->sent = [];
        $this->fakeServer(['server_url' => 'http://insecure.example']);
        $this->license()->sendHeartbeat();

        $this->assertStringStartsWith('https://license.doxamigo.com/', $this->sent[0]->url());
        $this->assertSame('https://license.doxamigo.com', $this->license()->getSummary()['server_url']);
    }
}
