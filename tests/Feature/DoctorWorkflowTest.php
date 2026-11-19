<?php

namespace Tests\Feature;

use App\Filament\Resources\PatientResource\Pages\ViewPatient;
use App\Filament\Resources\PatientResource\RelationManagers\PrescriptionsRelationManager;
use App\Filament\Widgets\DoctorChairsideWidget;
use App\Models\Appointment;
use App\Models\Branch;
use App\Models\Operatory;
use App\Models\Patient;
use App\Models\Practice;
use App\Models\Prescription;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DoctorWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected Practice $practice;

    protected User $doctor;

    protected Patient $patient;

    protected Operatory $room;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['doctor', 'secretary', 'clinic_admin'] as $r) {
            Role::firstOrCreate(['name' => $r]);
        }
        $this->practice = Practice::create(['name' => 'Flow Clinic', 'currency' => 'EGP', 'is_active' => true, 'features' => ['whatsapp']]);
        $branch = Branch::create(['practice_id' => $this->practice->id, 'name' => 'Main']);
        $this->room = Operatory::create(['branch_id' => $branch->id, 'name' => 'Room 1']);
        $this->doctor = User::create(['practice_id' => $this->practice->id, 'branch_id' => $branch->id, 'name' => 'Dr Flow', 'email' => 'flow@doc.test', 'password' => bcrypt('x'), 'role' => 'doctor']);
        $this->doctor->assignRole('doctor');
        $this->patient = Patient::create(['practice_id' => $this->practice->id, 'file_number' => 'F1', 'first_name' => 'Sara', 'last_name' => 'Nabil', 'phone' => '01055554444', 'gender' => 'female']);

        $this->actingAs($this->doctor);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Filament::setTenant($this->practice);
    }

    protected function appointment(string $status, int $minutesFromNow = 0, ?User $doctor = null): Appointment
    {
        return Appointment::create([
            'practice_id' => $this->practice->id, 'branch_id' => $this->room->branch_id, 'operatory_id' => $this->room->id,
            'patient_id' => $this->patient->id, 'doctor_id' => ($doctor ?? $this->doctor)->id, 'status' => $status,
            'start_time' => now()->addMinutes($minutesFromNow), 'end_time' => now()->addMinutes($minutesFromNow + 30),
        ]);
    }

    #[Test]
    public function doctors_can_create_prescriptions_from_the_patient_page(): void
    {
        Livewire::test(PrescriptionsRelationManager::class, ['ownerRecord' => $this->patient, 'pageClass' => ViewPatient::class])
            ->assertTableActionVisible('create')
            ->callTableAction('create', data: [
                'medication_name' => 'Amoxicillin', 'dosage' => '500mg', 'frequency' => 'Every 8 hours', 'duration' => '5 days',
            ])
            ->assertHasNoTableActionErrors();

        $this->assertDatabaseHas('prescriptions', ['patient_id' => $this->patient->id, 'doctor_id' => $this->doctor->id, 'medication_name' => 'Amoxicillin']);
    }

    #[Test]
    public function prescription_print_works_for_the_owning_clinic(): void
    {
        $rx = Prescription::create(['patient_id' => $this->patient->id, 'doctor_id' => $this->doctor->id, 'medication_name' => 'Ibuprofen', 'dosage' => '400mg', 'frequency' => 'Twice daily', 'duration' => '3 days']);
        $this->get(route('prescriptions.print', $rx))->assertOk();

        $other = Practice::create(['name' => 'Other', 'currency' => 'EGP', 'is_active' => true]);
        $outsider = User::create(['practice_id' => $other->id, 'name' => 'O', 'email' => 'o@o.test', 'password' => bcrypt('x'), 'role' => 'doctor']);
        $this->actingAs($outsider)->get(route('prescriptions.print', $rx))->assertForbidden();
    }

    #[Test]
    public function chairside_panel_shows_the_patient_in_the_chair_and_one_click_tools(): void
    {
        $this->appointment('booked', 60);
        $this->appointment('in_chair', -10);

        Livewire::test(DoctorChairsideWidget::class)
            ->assertSee('In the chair now')
            ->assertSee('Sara Nabil')
            ->assertSee('Dental chart')
            ->assertSee('Prescription')
            ->assertSee('Complete visit');
    }

    #[Test]
    public function doctors_change_status_only_on_their_own_appointments(): void
    {
        $mine = $this->appointment('arrived');
        Livewire::test(DoctorChairsideWidget::class)->call('setStatus', $mine->id, 'in_chair');
        $this->assertSame('in_chair', $mine->fresh()->status);

        $otherDoctor = User::create(['practice_id' => $this->practice->id, 'name' => 'Dr Other', 'email' => 'other@doc.test', 'password' => bcrypt('x'), 'role' => 'doctor']);
        $theirs = $this->appointment('arrived', 0, $otherDoctor);
        Livewire::test(DoctorChairsideWidget::class)->call('setStatus', $theirs->id, 'in_chair')->assertForbidden();
        $this->assertSame('arrived', $theirs->fresh()->status);

        // Cannot jump from booked straight to completed.
        $booked = $this->appointment('booked', 30);
        Livewire::test(DoctorChairsideWidget::class)->call('setStatus', $booked->id, 'completed')->assertStatus(422);
    }
}
