<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\DoctorProfile;
use App\Models\Operatory;
use App\Models\Practice;
use App\Models\User;
use App\Services\FeatureManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PracticeFeatureFlagsAndOnboardingTest extends TestCase
{
    use RefreshDatabase;

    protected Practice $practiceA;
    protected Practice $practiceB;
    protected User $adminA;
    protected User $adminB;
    protected User $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'clinic_admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'doctor', 'guard_name' => 'web']);

        $this->practiceA = Practice::create([
            'name' => 'Practice Alpha',
            'tax_id' => 'TAX-ALPHA',
            'currency' => 'USD',
            'is_active' => true,
            'features' => ['inventory', 'finance', 'labs'],
            'onboarding_completed_at' => now(),
            'onboarding_step' => 6,
        ]);

        $this->practiceB = Practice::create([
            'name' => 'Practice Beta',
            'tax_id' => 'TAX-BETA',
            'currency' => 'EUR',
            'is_active' => true,
            'features' => ['finance'], // Inventory disabled for Practice B
            'onboarding_completed_at' => now(),
            'onboarding_step' => 6,
        ]);

        $this->adminA = User::create([
            'name' => 'Admin Alpha',
            'email' => 'admin.alpha@example.com',
            'password' => bcrypt('password'),
            'practice_id' => $this->practiceA->id,
            'role' => 'clinic_admin',
        ]);
        $this->adminA->assignRole('clinic_admin');

        $this->adminB = User::create([
            'name' => 'Admin Beta',
            'email' => 'admin.beta@example.com',
            'password' => bcrypt('password'),
            'practice_id' => $this->practiceB->id,
            'role' => 'clinic_admin',
        ]);
        $this->adminB->assignRole('clinic_admin');

        $this->superAdmin = User::create([
            'name' => 'Super Admin',
            'email' => 'super@example.com',
            'password' => bcrypt('password'),
            'role' => 'super_admin',
        ]);
        $this->superAdmin->assignRole('super_admin');
    }

    /** @test */
    public function feature_enabled_page_and_navigation_are_available()
    {
        $this->actingAs($this->adminA);

        // Inventory is enabled for Practice A
        $response = $this->get('/inventory');
        $response->assertStatus(200);

        $this->assertTrue(FeatureManager::isEnabled('inventory', $this->practiceA));
        $this->assertTrue(\App\Filament\Resources\InventoryResource::canAccess());
    }

    /** @test */
    public function feature_disabled_navigation_is_hidden_and_direct_route_is_blocked()
    {
        $this->actingAs($this->adminB);

        // Inventory is disabled for Practice B
        $this->assertFalse(FeatureManager::isEnabled('inventory', $this->practiceB));

        // Direct web route is blocked with 403
        $response = $this->get('/inventory');
        $response->assertStatus(403);

        // Filament Resource canAccess() returns false
        $this->assertFalse(\App\Filament\Resources\InventoryResource::canAccess());
    }

    /** @test */
    public function practice_a_cannot_alter_practice_b_feature_flags()
    {
        $this->actingAs($this->adminA);

        // Admin A attempts to update features for Practice B directly
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);

        FeatureManager::updatePracticeFeatures($this->practiceB, ['inventory', '3d_model'], $this->adminA);
    }

    /** @test */
    public function system_only_feature_cannot_be_enabled_by_clinic_admin()
    {
        $this->actingAs($this->adminA);

        // Admin A attempts to enable '3d_model' (system-only feature) via API
        $response = $this->postJson('/api/practice/features', [
            'features' => ['inventory', 'finance', '3d_model', 'whatsapp'],
        ]);

        $response->assertStatus(200);

        // The system-only features must NOT be granted by clinic admin update
        $this->practiceA->refresh();
        $this->assertFalse($this->practiceA->hasFeature('3d_model'));
        $this->assertFalse($this->practiceA->hasFeature('whatsapp'));

        // Super Admin can enable system-only feature
        FeatureManager::updatePracticeFeatures($this->practiceA, ['inventory', 'finance', '3d_model'], $this->superAdmin);
        $this->practiceA->refresh();
        $this->assertTrue($this->practiceA->hasFeature('3d_model'));
    }

    /** @test */
    public function onboarding_step_validation()
    {
        $newPractice = Practice::create([
            'name' => 'Unboarded Clinic',
            'is_active' => false,
            'onboarding_step' => 1,
            'onboarding_completed_at' => null,
        ]);

        $newAdmin = User::create([
            'name' => 'New Admin',
            'email' => 'new.admin@example.com',
            'password' => bcrypt('password'),
            'practice_id' => $newPractice->id,
            'role' => 'clinic_admin',
        ]);
        $newAdmin->assignRole('clinic_admin');

        $this->actingAs($newAdmin);

        // Step 1: Blank practice name should fail validation
        $response = $this->postJson('/onboarding/step', [
            'step' => 1,
            'name' => '',
        ]);
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);

        // Step 1: Valid practice name succeeds
        $response = $this->postJson('/onboarding/step', [
            'step' => 1,
            'name' => 'New Smile Dental',
            'tax_id' => 'TAX-12345',
        ]);
        $response->assertStatus(200);

        $newPractice->refresh();
        $this->assertEquals('New Smile Dental', $newPractice->name);
        $this->assertEquals(2, $newPractice->onboarding_step);
    }

    /** @test */
    public function onboarding_multi_step_flow_and_completion()
    {
        $newPractice = Practice::create([
            'name' => 'Fresh Practice',
            'is_active' => false,
            'onboarding_step' => 1,
            'onboarding_completed_at' => null,
        ]);

        $newAdmin = User::create([
            'name' => 'Fresh Admin',
            'email' => 'fresh.admin@example.com',
            'password' => bcrypt('password'),
            'practice_id' => $newPractice->id,
            'role' => 'clinic_admin',
        ]);
        $newAdmin->assignRole('clinic_admin');

        $this->actingAs($newAdmin);

        // Step 1: Practice Basics
        $this->postJson('/onboarding/step', [
            'step' => 1,
            'name' => 'Fresh Smile Care',
        ])->assertStatus(200);

        // Step 2: Branch
        $this->postJson('/onboarding/step', [
            'step' => 2,
            'branch_name' => 'Main Downtown Branch',
            'code' => 'DT-01',
            'phone' => '123-456-7890',
        ])->assertStatus(200);

        // Step 3: Operatories
        $this->postJson('/onboarding/step', [
            'step' => 3,
            'operatories' => ['Chair 1', 'Chair 2 - Pedo'],
        ])->assertStatus(200);

        // Step 4: Doctor setup
        $this->postJson('/onboarding/step', [
            'step' => 4,
            'specialty' => 'Orthodontics',
            'license_number' => 'LIC-998877',
            'default_commission_percentage' => 45.0,
        ])->assertStatus(200);

        // Step 5: Currency & Preferences
        $this->postJson('/onboarding/step', [
            'step' => 5,
            'currency' => 'USD',
            'locale' => 'en',
            'timezone' => 'America/New_York',
        ])->assertStatus(200);

        // Complete onboarding
        $response = $this->postJson('/onboarding/complete');
        $response->assertStatus(200)
            ->assertJson(['redirect' => route('dashboard')]);

        $newPractice->refresh();
        $this->assertTrue($newPractice->isOnboarded());
        $this->assertTrue($newPractice->is_active);

        // Verify no duplicate branch or operatories created
        $this->assertEquals(1, $newPractice->branches()->count());
        $this->assertEquals(2, $newPractice->branches()->first()->operatories()->count());
        $this->assertNotNull($newAdmin->fresh()->doctorProfile);
    }

    /** @test */
    public function existing_practices_are_not_forced_through_onboarding_unexpectedly()
    {
        $this->actingAs($this->adminA);

        // Practice A is already onboarded
        $this->assertTrue($this->practiceA->isOnboarded());

        // Visiting dashboard should NOT redirect to onboarding
        $response = $this->get('/dashboard');
        $response->assertStatus(200);
    }
}
