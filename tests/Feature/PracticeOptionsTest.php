<?php

namespace Tests\Feature;

use App\Filament\Resources\PatientResource\Pages\ViewPatient;
use App\Filament\Resources\PatientResource\RelationManagers\PrescriptionsRelationManager;
use App\Filament\Resources\PracticeOptionResource;
use App\Models\Patient;
use App\Models\Practice;
use App\Models\PracticeOption;
use App\Models\User;
use App\Services\PracticeOptions;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/** Phase 5 — dropdowns a clinic can add to, most used first. */
class PracticeOptionsTest extends TestCase
{
    use RefreshDatabase;

    private Practice $practice;

    private Practice $other;

    private User $doctor;

    private PracticeOptions $options;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['doctor', 'clinic_admin'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }
        $this->practice = Practice::create(['name' => 'Smile', 'currency' => 'EGP', 'is_active' => true, 'onboarding_completed_at' => now(), 'onboarding_step' => 6]);
        $this->other = Practice::create(['name' => 'Other', 'currency' => 'EGP', 'is_active' => true, 'onboarding_completed_at' => now(), 'onboarding_step' => 6]);
        $this->doctor = User::create(['practice_id' => $this->practice->id, 'name' => 'Dr Sara', 'email' => 'sara@smile.test', 'password' => bcrypt('x'), 'role' => 'doctor']);
        $this->doctor->assignRole('doctor');
        $this->options = app(PracticeOptions::class);
    }

    #[Test]
    public function a_new_clinic_starts_with_the_default_list(): void
    {
        $list = $this->options->options('rx_duration', $this->practice->id);

        $this->assertSame(array_combine(PracticeOptions::defaults('rx_duration'), PracticeOptions::defaults('rx_duration')), $list);
    }

    #[Test]
    public function typed_values_are_remembered_and_the_most_used_come_first(): void
    {
        $this->options->record('medication', 'Cataflam 50mg', $this->practice->id);
        $this->options->record('medication', 'Augmentin 1g', $this->practice->id);
        $this->options->record('medication', 'augmentin  1G ', $this->practice->id); // same value, other spelling

        $list = array_keys($this->options->options('medication', $this->practice->id));

        $this->assertSame(['Augmentin 1g', 'Cataflam 50mg'], array_slice($list, 0, 2));
        $this->assertSame(1, PracticeOption::where('value', 'Augmentin 1g')->count());
        $this->assertSame(2, PracticeOption::where('value', 'Augmentin 1g')->value('use_count'));
        $this->assertContains('Ibuprofen 400mg', $list, 'Defaults still follow.');
    }

    #[Test]
    public function recently_used_values_beat_old_favourites(): void
    {
        $this->travelTo(now()->subDays(200));
        foreach (range(1, 5) as $i) {
            $this->options->record('medication', 'Old Favourite', $this->practice->id);
        }
        $this->travelBack();
        $this->options->record('medication', 'New Choice', $this->practice->id);

        $this->assertSame('New Choice', array_key_first($this->options->options('medication', $this->practice->id)));
    }

    #[Test]
    public function each_clinic_only_sees_its_own_values(): void
    {
        $this->options->record('medication', 'Secret Mix', $this->other->id);

        $this->assertArrayNotHasKey('Secret Mix', $this->options->options('medication', $this->practice->id));
        $this->assertArrayHasKey('Secret Mix', $this->options->options('medication', $this->other->id));
    }

    #[Test]
    public function search_offers_to_add_what_was_typed(): void
    {
        $found = $this->options->search('medication', 'amox', $this->practice->id);
        $this->assertSame('amox', array_key_first($found), 'Typed text is offered first as a new value.');
        $this->assertArrayHasKey('Amoxicillin 500mg', $found);

        $exact = $this->options->search('medication', 'Amoxicillin 500mg', $this->practice->id);
        $this->assertCount(1, array_filter(array_keys($exact), fn ($k) => mb_strtolower($k) === 'amoxicillin 500mg'), 'No duplicate "Add" for an existing value.');
    }

    #[Test]
    public function a_medication_typed_once_appears_in_the_list_next_time(): void
    {
        $patient = Patient::create(['practice_id' => $this->practice->id, 'first_name' => 'Mona', 'last_name' => 'Adel', 'phone' => '01000000001', 'gender' => 'female']);
        $this->actingAs($this->doctor);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Filament::setTenant($this->practice);

        Livewire::test(PrescriptionsRelationManager::class, ['ownerRecord' => $patient, 'pageClass' => ViewPatient::class])
            ->callTableAction('create', data: [
                'medication_name' => '  Flagyl   500mg ', 'dosage' => '1 tablet', 'frequency' => 'Every 8 hours', 'duration' => '5 days',
            ])
            ->assertHasNoTableActionErrors();

        $this->assertDatabaseHas('prescriptions', ['patient_id' => $patient->id, 'medication_name' => 'Flagyl 500mg']);
        $this->assertSame('Flagyl 500mg', array_key_first($this->options->options('medication', $this->practice->id)));
        $this->assertSame('5 days', array_key_first($this->options->options('rx_duration', $this->practice->id)));
    }

    #[Test]
    public function admins_can_review_and_remove_their_values_only(): void
    {
        $admin = User::create(['practice_id' => $this->practice->id, 'name' => 'Admin', 'email' => 'admin@smile.test', 'password' => bcrypt('x'), 'role' => 'clinic_admin']);
        $admin->assignRole('clinic_admin');
        $this->options->record('medication', 'Typo Mediction', $this->practice->id);
        $this->options->record('medication', 'Other Clinic Drug', $this->other->id);

        $this->actingAs($admin)->get(PracticeOptionResource::getUrl('index', tenant: $this->practice))
            ->assertOk()->assertSee('Typo Mediction')->assertDontSee('Other Clinic Drug');

        $doctorView = $this->actingAs($this->doctor)->get(PracticeOptionResource::getUrl('index', tenant: $this->practice));
        $this->assertFalse(PracticeOptionResource::canAccess(), 'Doctors pick and add values but do not manage the lists.');
        $this->assertContains($doctorView->status(), [302, 403]);
    }

    #[Test]
    public function the_chart_workspace_api_learns_diagnoses_per_clinic(): void
    {
        $this->actingAs($this->doctor)
            ->postJson(route('practice-options.store'), ['list' => 'diagnosis', 'value' => '  Cracked tooth  syndrome '])
            ->assertOk()->assertJson(['value' => 'Cracked tooth syndrome']);
        $this->actingAs($this->doctor)->postJson(route('practice-options.store'), ['list' => 'diagnosis', 'value' => 'Irreversible Pulpitis']);
        $this->actingAs($this->doctor)->postJson(route('practice-options.store'), ['list' => 'diagnosis', 'value' => 'Irreversible Pulpitis']);

        $this->actingAs($this->doctor)
            ->getJson(route('practice-options.index', ['lists' => 'diagnosis,tooth_note,not_a_list']))
            ->assertOk()
            ->assertExactJson(['diagnosis' => ['Irreversible Pulpitis', 'Cracked tooth syndrome'], 'tooth_note' => []]);

        // Unknown lists are refused; other clinics see nothing of this.
        $this->actingAs($this->doctor)->postJson(route('practice-options.store'), ['list' => 'anything', 'value' => 'x'])->assertStatus(422);
        $outsider = User::create(['practice_id' => $this->other->id, 'name' => 'Out', 'email' => 'out@other.test', 'password' => bcrypt('x')]);
        $this->actingAs($outsider)->getJson(route('practice-options.index', ['lists' => 'diagnosis']))->assertExactJson(['diagnosis' => []]);
        $this->assertSame(0, PracticeOption::where('practice_id', $this->other->id)->count());
    }

    #[Test]
    public function the_api_needs_a_login(): void
    {
        $this->getJson(route('practice-options.index', ['lists' => 'diagnosis']))->assertUnauthorized();
        $this->postJson(route('practice-options.store'), ['list' => 'diagnosis', 'value' => 'x'])->assertUnauthorized();
    }
}
