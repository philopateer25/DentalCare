<?php

namespace Tests\Feature;

use App\Filament\Resources\OperatoryResource;
use App\Filament\Resources\OperatoryResource\Pages\CreateOperatory;
use App\Models\Branch;
use App\Models\Operatory;
use App\Models\Practice;
use App\Models\User;
use App\Services\Licensing\ClinicTelemetry;
use App\Services\Licensing\RemoteCommandRunner;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/** Rooms & chairs: managed by the clinic and remotely by the central app, within the chair limit. */
class RoomsTest extends TestCase
{
    use RefreshDatabase;

    private Practice $practice;

    private Branch $branch;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['clinic_admin', 'doctor'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }
        $this->practice = Practice::create(['name' => 'Smile', 'currency' => 'EGP', 'is_active' => true, 'chair_limit' => 2, 'onboarding_completed_at' => now(), 'onboarding_step' => 6]);
        $this->branch = Branch::create(['practice_id' => $this->practice->id, 'name' => 'Main', 'is_active' => true]);
        $this->admin = User::create(['name' => 'Admin', 'email' => 'admin@smile.test', 'password' => bcrypt('x'), 'practice_id' => $this->practice->id, 'role' => 'clinic_admin']);
        $this->admin->assignRole('clinic_admin');
    }

    private function room(string $name, bool $active = true): Operatory
    {
        return Operatory::create(['branch_id' => $this->branch->id, 'name' => $name, 'is_active' => $active]);
    }

    #[Test]
    public function only_active_rooms_count_towards_the_chair_limit(): void
    {
        $this->room('Room 1');
        $this->room('Room 2');

        try {
            $this->room('Room 3');
            $this->fail('A third active room must be refused with a limit of 2.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('Chair limit reached', collect($e->errors())->flatten()->first());
        }

        $this->room('Spare', active: false);
        $this->assertSame(2, $this->practice->activeChairCount());

        // Switching one off frees a chair; switching another on is then allowed.
        Operatory::where('name', 'Room 2')->first()->update(['is_active' => false]);
        Operatory::where('name', 'Spare')->first()->update(['is_active' => true]);
        $this->assertSame(2, $this->practice->activeChairCount());

        $this->expectException(ValidationException::class);
        Operatory::where('name', 'Room 2')->first()->update(['is_active' => true]);
    }

    #[Test]
    public function clinic_admins_add_rooms_on_the_rooms_screen_and_see_the_limit(): void
    {
        $this->actingAs($this->admin);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Filament::setTenant($this->practice);

        $this->get(OperatoryResource::getUrl('index'))->assertOk()->assertSee('0 of 2 chairs in use');

        Livewire::test(CreateOperatory::class)
            ->fillForm(['name' => 'Surgery', 'branch_id' => $this->branch->id, 'is_active' => true])
            ->call('create')
            ->assertHasNoFormErrors();
        $this->assertDatabaseHas('operatories', ['branch_id' => $this->branch->id, 'name' => 'Surgery', 'is_active' => true]);

        $this->room('Room 1');
        Livewire::test(CreateOperatory::class)
            ->fillForm(['name' => 'Third', 'branch_id' => $this->branch->id, 'is_active' => true])
            ->call('create')
            ->assertNotified();
        $this->assertDatabaseMissing('operatories', ['name' => 'Third']);
    }

    #[Test]
    public function rooms_of_another_clinic_are_not_listed_or_usable(): void
    {
        $other = Practice::create(['name' => 'Other', 'currency' => 'EGP', 'is_active' => true, 'onboarding_completed_at' => now(), 'onboarding_step' => 6]);
        $otherBranch = Branch::create(['practice_id' => $other->id, 'name' => 'Theirs', 'is_active' => true]);
        $theirs = Operatory::create(['branch_id' => $otherBranch->id, 'name' => 'Their room']);
        $this->room('Our room');

        $this->actingAs($this->admin);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Filament::setTenant($this->practice);

        $this->get(OperatoryResource::getUrl('index'))->assertSee('Our room')->assertDontSee('Their room');
        $this->get(OperatoryResource::getUrl('edit', ['record' => $theirs]))->assertNotFound();

        Livewire::test(CreateOperatory::class)
            ->fillForm(['name' => 'Sneaky', 'branch_id' => $otherBranch->id])
            ->call('create')
            ->assertHasFormErrors(['branch_id']);
    }

    #[Test]
    public function doctors_do_not_manage_rooms(): void
    {
        $doctor = User::create(['name' => 'Dr', 'email' => 'dr@smile.test', 'password' => bcrypt('x'), 'practice_id' => $this->practice->id, 'role' => 'doctor']);
        $doctor->assignRole('doctor');
        $this->actingAs($doctor);

        $this->assertFalse(OperatoryResource::canAccess());
    }

    #[Test]
    public function the_central_app_adds_renames_and_switches_rooms_within_the_limit(): void
    {
        $runner = app(RemoteCommandRunner::class);

        $results = $runner->run([
            ['id' => 'c1', 'type' => 'add_room', 'args' => ['name' => 'Room 1']],
            ['id' => 'c2', 'type' => 'add_room', 'args' => ['name' => 'Ortho', 'branch' => 'Main']],
            ['id' => 'c3', 'type' => 'add_room', 'args' => ['name' => 'Room 3']],
            ['id' => 'c4', 'type' => 'rename_room', 'args' => ['from' => 'Room 1', 'to' => 'Surgery']],
            ['id' => 'c5', 'type' => 'set_room_active', 'args' => ['name' => 'Ortho', 'active' => false]],
            ['id' => 'c6', 'type' => 'add_room', 'args' => ['name' => 'Room 3']],
            ['id' => 'c7', 'type' => 'rename_room', 'args' => ['from' => 'Nope', 'to' => 'X']],
        ]);
        $ok = collect($results)->pluck('ok', 'id');

        $this->assertTrue($ok['c1'] && $ok['c2'] && $ok['c4'] && $ok['c5'] && $ok['c6']);
        $this->assertFalse($ok['c3'], 'Third active room refused (limit 2).');
        $this->assertStringContainsString('Chair limit reached', collect($results)->firstWhere('id', 'c3')['output']);
        $this->assertFalse($ok['c7']);

        $this->assertEqualsCanonicalizing(
            [['name' => 'Ortho', 'branch' => 'Main', 'active' => false], ['name' => 'Room 3', 'branch' => 'Main', 'active' => true], ['name' => 'Surgery', 'branch' => 'Main', 'active' => true]],
            app(ClinicTelemetry::class)->health()['rooms'],
        );
    }
}
