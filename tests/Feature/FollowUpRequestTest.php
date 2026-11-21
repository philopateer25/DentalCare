<?php

namespace Tests\Feature;

use App\Filament\Widgets\DoctorChairsideWidget;
use App\Filament\Widgets\FollowUpRequestsWidget;
use App\Models\Appointment;
use App\Models\Branch;
use App\Models\FollowUpRequest;
use App\Models\MaintenanceRecord;
use App\Models\Operatory;
use App\Models\Patient;
use App\Models\Practice;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class FollowUpRequestTest extends TestCase
{
    use RefreshDatabase;

    protected Practice $practice;

    protected User $doctor;

    protected User $secretary;

    protected Patient $patient;

    protected Operatory $room;

    protected Appointment $visit;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['doctor', 'secretary', 'clinic_admin', 'super_admin'] as $r) {
            Role::firstOrCreate(['name' => $r]);
        }
        $this->practice = Practice::create(['name' => 'FU Clinic', 'currency' => 'EGP', 'is_active' => true]);
        $branch = Branch::create(['practice_id' => $this->practice->id, 'name' => 'Main']);
        $this->room = Operatory::create(['branch_id' => $branch->id, 'name' => 'Room 1']);
        $this->doctor = $this->user('doc@fu.test', 'doctor');
        $this->secretary = $this->user('sec@fu.test', 'secretary');
        $this->patient = Patient::create(['practice_id' => $this->practice->id, 'file_number' => 'FU1', 'first_name' => 'Laila', 'last_name' => 'Fawzy', 'phone' => '01012345678', 'gender' => 'female']);
        $this->visit = Appointment::create([
            'practice_id' => $this->practice->id, 'branch_id' => $branch->id, 'operatory_id' => $this->room->id,
            'patient_id' => $this->patient->id, 'doctor_id' => $this->doctor->id, 'status' => 'in_chair',
            'start_time' => now()->subMinutes(20), 'end_time' => now()->addMinutes(10),
        ]);

        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($this->secretary);
        Filament::setTenant($this->practice);
    }

    protected function user(string $email, string $role, ?Practice $practice = null): User
    {
        $u = User::create(['practice_id' => ($practice ?? $this->practice)->id, 'name' => ucfirst($role).' '.$email, 'email' => $email, 'password' => bcrypt('x'), 'role' => $role]);
        $u->assignRole($role);

        return $u;
    }

    protected function requestFollowUp(array $data = []): FollowUpRequest
    {
        $this->actingAs($this->doctor);
        Livewire::test(DoctorChairsideWidget::class)
            ->callAction('requestFollowUp', $data + ['timeframe' => '2_weeks', 'duration_minutes' => '30', 'procedure_name' => 'Suture removal', 'notes' => 'Check healing'], ['appointment' => $this->visit->id])
            ->assertHasNoActionErrors();

        return FollowUpRequest::latest('id')->firstOrFail();
    }

    #[Test]
    public function a_doctor_request_reaches_reception_immediately(): void
    {
        $request = $this->requestFollowUp(['urgent' => true]);

        $this->assertSame('pending', $request->status);
        $this->assertSame($this->patient->id, $request->patient_id);
        $this->assertSame($this->visit->id, $request->source_appointment_id);
        $this->assertTrue($request->urgent);
        $this->assertSame(1, $this->secretary->notifications()->count());   // bell notification

        $this->actingAs($this->secretary);
        Livewire::test(FollowUpRequestsWidget::class)
            ->assertSee('Laila Fawzy')
            ->assertSee('Suture removal')
            ->assertSee('Urgent')
            ->assertSeeHtml('wire:poll.5s');                                  // live, no refresh
    }

    #[Test]
    public function reception_books_it_and_the_doctor_is_notified(): void
    {
        $request = $this->requestFollowUp();
        $start = now()->addDays(14)->setTime(11, 0);

        $this->actingAs($this->secretary);
        Livewire::test(FollowUpRequestsWidget::class)
            ->callAction('book', [
                'start_time' => $start->format('Y-m-d H:i:s'), 'duration_minutes' => '30',
                'doctor_id' => $this->doctor->id, 'operatory_id' => $this->room->id, 'procedure_name' => 'Suture removal',
            ], ['request' => $request->id])
            ->assertHasNoActionErrors();

        $request->refresh();
        $this->assertSame('booked', $request->status);
        $this->assertSame($this->secretary->id, $request->handled_by);
        $appointment = $request->bookedAppointment;
        $this->assertSame('follow_up', $appointment->type);
        $this->assertSame($this->visit->id, $appointment->parent_appointment_id);
        $this->assertSame($start->format('Y-m-d H:i'), $appointment->start_time->format('Y-m-d H:i'));
        $this->assertSame(1, $this->doctor->notifications()->count());
    }

    #[Test]
    public function a_closed_room_cannot_be_booked_and_the_request_stays_pending(): void
    {
        $request = $this->requestFollowUp();
        $start = now()->addDays(3)->setTime(12, 0);
        MaintenanceRecord::create([
            'practice_id' => $this->practice->id, 'branch_id' => $this->room->branch_id, 'title' => 'AC repair', 'category' => 'hvac',
            'impact' => MaintenanceRecord::IMPACT_CLOSES_BRANCH, 'starts_at' => $start->copy()->subHour(), 'ends_at' => $start->copy()->addHours(2),
            'status' => MaintenanceRecord::STATUS_SCHEDULED,
        ]);

        $this->actingAs($this->secretary);
        Livewire::test(FollowUpRequestsWidget::class)->callAction('book', [
            'start_time' => $start->format('Y-m-d H:i:s'), 'duration_minutes' => '30', 'doctor_id' => $this->doctor->id, 'operatory_id' => $this->room->id,
        ], ['request' => $request->id]);

        $this->assertSame('pending', $request->fresh()->status);
        $this->assertSame(1, Appointment::count());
    }

    #[Test]
    public function reception_can_close_a_request_with_a_reason(): void
    {
        $request = $this->requestFollowUp();

        $this->actingAs($this->secretary);
        Livewire::test(FollowUpRequestsWidget::class)->callAction('dismiss', ['reason' => 'Patient will call back'], ['request' => $request->id]);

        $request->refresh();
        $this->assertSame('cancelled', $request->status);
        $this->assertSame('Patient will call back', $request->handled_note);
        $this->assertSame(1, $this->doctor->notifications()->count());
    }

    #[Test]
    public function requests_are_scoped_to_the_clinic_and_the_doctor(): void
    {
        $request = $this->requestFollowUp();

        // Another clinic's receptionist cannot see or book it.
        $other = Practice::create(['name' => 'Other', 'currency' => 'EGP', 'is_active' => true]);
        $outsider = $this->user('sec@other.test', 'secretary', $other);
        $this->actingAs($outsider);
        Filament::setTenant($other);
        Livewire::test(FollowUpRequestsWidget::class)->assertDontSee('Laila Fawzy')
            ->call('mountAction', 'book', ['request' => $request->id])
            ->assertStatus(404);
        $this->assertSame('pending', $request->fresh()->status);

        // A doctor cannot request a follow-up for another doctor's visit.
        $otherDoctor = $this->user('doc2@fu.test', 'doctor');
        $this->actingAs($otherDoctor);
        Filament::setTenant($this->practice);
        Livewire::test(DoctorChairsideWidget::class)
            ->call('mountAction', 'requestFollowUp', ['appointment' => $this->visit->id])
            ->set('mountedActionsData.0', ['timeframe' => '1_week', 'duration_minutes' => '30'])
            ->call('callMountedAction')
            ->assertForbidden();
        $this->assertSame(1, FollowUpRequest::count());

        // Doctors do not get the reception panel.
        $this->assertFalse(FollowUpRequestsWidget::canView());
    }
}
