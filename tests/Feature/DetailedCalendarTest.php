<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Branch;
use App\Models\Operatory;
use App\Models\Patient;
use App\Models\Practice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DetailedCalendarTest extends TestCase
{
    use RefreshDatabase;

    protected Practice $practiceA;
    protected Practice $practiceB;
    protected User $doctorA;
    protected User $doctorB;
    protected Branch $branchA;
    protected Branch $branchB;
    protected Operatory $operatoryA1;
    protected Operatory $operatoryA2;
    protected Operatory $operatoryB;
    protected Patient $patientA;
    protected Patient $patientB;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'doctor']);
        Role::firstOrCreate(['name' => 'clinic_admin']);

        $this->practiceA = Practice::create(['name' => 'Practice Alpha', 'is_active' => true]);
        $this->practiceB = Practice::create(['name' => 'Practice Beta', 'is_active' => true]);

        $this->branchA = Branch::create(['practice_id' => $this->practiceA->id, 'name' => 'Alpha Main']);
        $this->branchB = Branch::create(['practice_id' => $this->practiceB->id, 'name' => 'Beta Main']);

        $this->operatoryA1 = Operatory::create(['branch_id' => $this->branchA->id, 'name' => 'Chair A1']);
        $this->operatoryA2 = Operatory::create(['branch_id' => $this->branchA->id, 'name' => 'Chair A2']);
        $this->operatoryB = Operatory::create(['branch_id' => $this->branchB->id, 'name' => 'Chair B1']);

        $this->doctorA = User::create([
            'name' => 'Dr. Alice Alpha',
            'email' => 'alice@calendar.test',
            'password' => bcrypt('password'),
            'practice_id' => $this->practiceA->id,
            'branch_id' => $this->branchA->id,
            'role' => 'doctor',
        ]);
        $this->doctorA->assignRole('doctor');

        $this->doctorB = User::create([
            'name' => 'Dr. Bob Beta',
            'email' => 'bob@calendar.test',
            'password' => bcrypt('password'),
            'practice_id' => $this->practiceB->id,
            'branch_id' => $this->branchB->id,
            'role' => 'doctor',
        ]);
        $this->doctorB->assignRole('doctor');

        $this->patientA = Patient::create([
            'practice_id' => $this->practiceA->id,
            'first_name' => 'John',
            'last_name' => 'Alpha',
            'phone' => '1111111111',
            'gender' => 'male',
        ]);

        $this->patientB = Patient::create([
            'practice_id' => $this->practiceB->id,
            'first_name' => 'Jane',
            'last_name' => 'Beta',
            'phone' => '2222222222',
            'gender' => 'female',
        ]);
    }

    /** @test */
    public function correct_appointments_returned_with_date_range_and_filters()
    {
        $this->actingAs($this->doctorA);

        $app1 = Appointment::create([
            'practice_id' => $this->practiceA->id,
            'branch_id' => $this->branchA->id,
            'operatory_id' => $this->operatoryA1->id,
            'patient_id' => $this->patientA->id,
            'doctor_id' => $this->doctorA->id,
            'start_time' => now()->startOfDay()->addHours(10),
            'end_time' => now()->startOfDay()->addHours(11),
            'status' => 'booked',
        ]);

        $response = $this->getJson('/api/calendar/appointments?' . http_build_query([
            'start' => now()->startOfMonth()->toDateString(),
            'end' => now()->endOfMonth()->toDateString(),
            'doctor_id' => $this->doctorA->id,
            'operatory_id' => $this->operatoryA1->id,
        ]));

        $response->assertStatus(200)
            ->assertJsonCount(1, 'appointments')
            ->assertJsonPath('appointments.0.id', $app1->id);
    }

    /** @test */
    public function unauthorized_cross_tenant_calendar_access_rejected()
    {
        $appB = Appointment::create([
            'practice_id' => $this->practiceB->id,
            'branch_id' => $this->branchB->id,
            'operatory_id' => $this->operatoryB->id,
            'patient_id' => $this->patientB->id,
            'doctor_id' => $this->doctorB->id,
            'start_time' => now()->addHours(2),
            'end_time' => now()->addHours(3),
            'status' => 'booked',
        ]);

        // Doctor A attempts to reschedule Doctor B's appointment
        $this->actingAs($this->doctorA);
        $response = $this->patchJson("/api/calendar/appointments/{$appB->id}/reschedule", [
            'start_time' => now()->addHours(4)->toIso8601String(),
            'end_time' => now()->addHours(5)->toIso8601String(),
        ]);

        $response->assertStatus(403);
    }

    /** @test */
    public function conflict_prevention_rejects_overlapping_appointments_for_doctor_or_operatory()
    {
        $this->actingAs($this->doctorA);

        $start = now()->addDays(2)->setHour(14)->setMinute(0);
        $end = (clone $start)->addHour();

        // Existing appointment 14:00 - 15:00 for Doctor A in Chair A1
        Appointment::create([
            'practice_id' => $this->practiceA->id,
            'branch_id' => $this->branchA->id,
            'operatory_id' => $this->operatoryA1->id,
            'patient_id' => $this->patientA->id,
            'doctor_id' => $this->doctorA->id,
            'start_time' => $start,
            'end_time' => $end,
            'status' => 'booked',
        ]);

        // Attempt overlapping appointment 14:30 - 15:30 for same doctor
        $responseConflict = $this->postJson('/api/calendar/appointments', [
            'patient_id' => $this->patientA->id,
            'doctor_id' => $this->doctorA->id,
            'operatory_id' => $this->operatoryA2->id, // different operatory, same doctor!
            'start_time' => (clone $start)->addMinutes(30)->toIso8601String(),
            'end_time' => (clone $end)->addMinutes(30)->toIso8601String(),
            'chief_complaint' => 'Overlapping appointment',
        ]);

        $responseConflict->assertStatus(422)
            ->assertJsonPath('message', 'Time slot conflict: The selected doctor or operatory/chair is already booked during this time interval.');
    }

    /** @test */
    public function appointment_rescheduling_and_status_transitions()
    {
        $this->actingAs($this->doctorA);

        $start = now()->addDays(3)->setHour(9)->setMinute(0);
        $end = (clone $start)->addHour();

        $app = Appointment::create([
            'practice_id' => $this->practiceA->id,
            'branch_id' => $this->branchA->id,
            'operatory_id' => $this->operatoryA1->id,
            'patient_id' => $this->patientA->id,
            'doctor_id' => $this->doctorA->id,
            'start_time' => $start,
            'end_time' => $end,
            'status' => 'booked',
        ]);

        // Reschedule
        $newStart = (clone $start)->addHours(2);
        $newEnd = (clone $end)->addHours(2);

        $rescheduleResponse = $this->patchJson("/api/calendar/appointments/{$app->id}/reschedule", [
            'start_time' => $newStart->toIso8601String(),
            'end_time' => $newEnd->toIso8601String(),
            'operatory_id' => $this->operatoryA2->id,
        ]);

        $rescheduleResponse->assertStatus(200);
        $app->refresh();
        $this->assertEquals($this->operatoryA2->id, $app->operatory_id);

        // Status transition: arrived -> in_chair -> completed
        $statusResponse = $this->patchJson("/api/calendar/appointments/{$app->id}/status", [
            'status' => 'in_chair',
        ]);
        $statusResponse->assertStatus(200);

        $this->assertEquals('in_chair', $app->fresh()->status);
    }
}
