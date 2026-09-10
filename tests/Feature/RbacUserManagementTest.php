<?php

namespace Tests\Feature;

use App\Filament\Resources\UserResource;
use App\Models\Practice;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RbacUserManagementTest extends TestCase
{
    use RefreshDatabase;

    protected Practice $practiceA;
    protected Practice $practiceB;
    protected User $adminA;
    protected User $doctorA;
    protected User $secretaryA;
    protected User $adminB;
    protected User $doctorB;
    protected User $developer;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed roles
        Role::firstOrCreate(['name' => 'developer']);
        Role::firstOrCreate(['name' => 'super_admin']);
        Role::firstOrCreate(['name' => 'clinic_admin']);
        Role::firstOrCreate(['name' => 'doctor']);
        Role::firstOrCreate(['name' => 'secretary']);

        // Setup Practice A
        $this->practiceA = Practice::create(['name' => 'Practice Alpha', 'currency' => 'USD']);
        $this->adminA = User::create([
            'practice_id' => $this->practiceA->id,
            'name' => 'Admin Alpha',
            'email' => 'admin@practice-a.test',
            'password' => bcrypt('password'),
        ]);
        $this->adminA->assignRole('clinic_admin');

        $this->doctorA = User::create([
            'practice_id' => $this->practiceA->id,
            'name' => 'Doctor Alpha',
            'email' => 'doctor@practice-a.test',
            'password' => bcrypt('password'),
        ]);
        $this->doctorA->assignRole('doctor');

        $this->secretaryA = User::create([
            'practice_id' => $this->practiceA->id,
            'name' => 'Secretary Alpha',
            'email' => 'secretary@practice-a.test',
            'password' => bcrypt('password'),
        ]);
        $this->secretaryA->assignRole('secretary');

        // Setup Practice B
        $this->practiceB = Practice::create(['name' => 'Practice Beta', 'currency' => 'EUR']);
        $this->adminB = User::create([
            'practice_id' => $this->practiceB->id,
            'name' => 'Admin Beta',
            'email' => 'admin@practice-b.test',
            'password' => bcrypt('password'),
        ]);
        $this->adminB->assignRole('clinic_admin');

        $this->doctorB = User::create([
            'practice_id' => $this->practiceB->id,
            'name' => 'Doctor Beta',
            'email' => 'doctor@practice-b.test',
            'password' => bcrypt('password'),
        ]);
        $this->doctorB->assignRole('doctor');

        // Setup Developer
        $this->developer = User::create([
            'name' => 'System Developer',
            'email' => 'dev@system.test',
            'password' => bcrypt('password'),
            'practice_id' => null,
        ]);
        $this->developer->assignRole('developer');
    }

    public function test_practice_admin_can_view_and_manage_own_practice_users(): void
    {
        $this->assertTrue($this->adminA->can('viewAny', User::class));
        $this->assertTrue($this->adminA->can('view', $this->doctorA));
        $this->assertTrue($this->adminA->can('create', User::class));
        $this->assertTrue($this->adminA->can('update', $this->doctorA));
        $this->assertTrue($this->adminA->can('delete', $this->doctorA));
    }

    public function test_practice_admin_cannot_access_or_manage_another_practice_users(): void
    {
        $this->assertFalse($this->adminA->can('view', $this->doctorB));
        $this->assertFalse($this->adminA->can('update', $this->doctorB));
        $this->assertFalse($this->adminA->can('delete', $this->doctorB));
        $this->assertFalse($this->adminA->can('update', $this->adminB));

        // Test UserResource eloquent query tenant scoping
        $this->actingAs($this->adminA);
        $scopedUsers = UserResource::getEloquentQuery()->get();

        $this->assertTrue($scopedUsers->contains($this->adminA));
        $this->assertTrue($scopedUsers->contains($this->doctorA));
        $this->assertFalse($scopedUsers->contains($this->adminB));
        $this->assertFalse($scopedUsers->contains($this->doctorB));
    }

    public function test_practice_admin_cannot_assign_user_to_another_practice(): void
    {
        $this->actingAs($this->adminA);
        Filament::setTenant($this->practiceA);

        $page = new UserResource\Pages\CreateUser();
        $reflection = new \ReflectionClass($page);
        $method = $reflection->getMethod('mutateFormDataBeforeCreate');
        $method->setAccessible(true);

        $inputData = [
            'name' => 'New User',
            'email' => 'newuser@test.com',
            'practice_id' => $this->practiceB->id, // Attempting cross-tenant assignment
        ];

        $mutatedData = $method->invoke($page, $inputData);

        $this->assertEquals($this->practiceA->id, $mutatedData['practice_id']);
    }

    public function test_practice_admin_cannot_assign_developer_or_super_admin_roles(): void
    {
        $this->actingAs($this->adminA);

        // Check policy protection
        $this->assertFalse($this->adminA->can('update', $this->developer));
        $this->assertFalse($this->adminA->can('delete', $this->developer));

        // Super Admin protection
        $superAdmin = User::create([
            'practice_id' => $this->practiceA->id,
            'name' => 'Super Admin Alpha',
            'email' => 'super@practice-a.test',
            'password' => bcrypt('password'),
        ]);
        $superAdmin->assignRole('super_admin');

        $this->assertFalse($this->adminA->can('update', $superAdmin));
        $this->assertFalse($this->adminA->can('delete', $superAdmin));
    }

    public function test_multi_role_users_retain_combined_permissions(): void
    {
        // Give doctorA clinic_admin role as well
        $this->doctorA->assignRole('clinic_admin');

        $this->assertTrue($this->doctorA->hasRole('doctor'));
        $this->assertTrue($this->doctorA->hasRole('clinic_admin'));

        // Can access clinical features
        $this->assertTrue($this->doctorA->can('create', \App\Models\TreatmentPlan::class));

        // Can access administrative user management features
        $this->assertTrue($this->doctorA->can('viewAny', User::class));
        $this->assertTrue($this->doctorA->can('view', $this->secretaryA));
    }

    public function test_unauthorized_roles_cannot_perform_user_management(): void
    {
        // Doctor cannot manage users
        $this->assertFalse($this->doctorA->can('viewAny', User::class));
        $this->assertFalse($this->doctorA->can('create', User::class));
        $this->assertFalse($this->doctorA->can('update', $this->secretaryA));
        $this->assertFalse($this->doctorA->can('delete', $this->secretaryA));

        // Secretary cannot manage users
        $this->assertFalse($this->secretaryA->can('viewAny', User::class));
        $this->assertFalse($this->secretaryA->can('create', User::class));
        $this->assertFalse($this->secretaryA->can('update', $this->doctorA));
        $this->assertFalse($this->secretaryA->can('delete', $this->doctorA));
    }

    public function test_developer_can_access_all_users_across_practices(): void
    {
        $this->assertTrue($this->developer->can('viewAny', User::class));
        $this->assertTrue($this->developer->can('view', $this->adminA));
        $this->assertTrue($this->developer->can('view', $this->adminB));
        $this->assertTrue($this->developer->can('update', $this->adminA));
        $this->assertTrue($this->developer->can('update', $this->adminB));
    }
}
