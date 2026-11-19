<?php

namespace Tests\Feature;

use App\Filament\Widgets\AppointmentsCalendarWidget;
use App\Models\Appointment;
use App\Models\Branch;
use App\Models\MaintenanceRecord;
use App\Models\Operatory;
use App\Models\Patient;
use App\Models\Practice;
use App\Models\User;
use App\Services\Scheduling\RoomAvailabilityService;
use Carbon\Carbon;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class MaintenanceSchedulingTest extends TestCase
{
    use RefreshDatabase;

    protected Practice $practice;

    protected Practice $otherPractice;

    protected Branch $branch;

    protected Operatory $room1;

    protected Operatory $room2;

    protected Operatory $otherRoom;

    protected User $doctor;

    protected User $doctor2;

    protected User $secretary;

    protected Patient $patient;

    protected Carbon $slotStart;

    protected Carbon $slotEnd;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['doctor', 'secretary', 'clinic_admin', 'super_admin'] as $role) {
            Role::firstOrCreate(['name' => $role]);
        }

        $this->practice = Practice::create(['name' => 'Smile Clinic', 'is_active' => true]);
        $this->otherPractice = Practice::create(['name' => 'Other Clinic', 'is_active' => true]);
        $this->branch = Branch::create(['practice_id' => $this->practice->id, 'name' => 'Main']);
        $otherBranch = Branch::create(['practice_id' => $this->otherPractice->id, 'name' => 'Other Main']);

        $this->room1 = Operatory::create(['branch_id' => $this->branch->id, 'name' => 'Chair 1']);
        $this->room2 = Operatory::create(['branch_id' => $this->branch->id, 'name' => 'Chair 2']);
        $this->otherRoom = Operatory::create(['branch_id' => $otherBranch->id, 'name' => 'Other Chair']);

        $this->doctor = $this->makeUser('doc@maint.test', 'doctor');
        $this->doctor2 = $this->makeUser('doc2@maint.test', 'doctor');
        $this->secretary = $this->makeUser('sec@maint.test', 'secretary');

        $this->patient = Patient::create([
            'practice_id' => $this->practice->id, 'first_name' => 'Mona', 'last_name' => 'Ali',
            'phone' => '0100000000', 'gender' => 'female',
        ]);

        $this->slotStart = Carbon::parse('2026-12-01 10:00:00');
        $this->slotEnd = Carbon::parse('2026-12-01 10:30:00');
    }

    protected function makeUser(string $email, string $role): User
    {
        $user = User::create([
            'name' => ucfirst($role).' '.$email, 'email' => $email, 'password' => bcrypt('password'),
            'practice_id' => $this->practice->id, 'branch_id' => $this->branch->id, 'role' => $role,
        ]);
        $user->assignRole($role);

        return $user;
    }

    protected function maintenance(string $impact, array $rooms = [], array $overrides = []): MaintenanceRecord
    {
        $record = MaintenanceRecord::create(array_merge([
            'practice_id' => $this->practice->id,
            'branch_id' => $this->branch->id,
            'title' => 'Job '.$impact,
            'category' => 'imaging',
            'equipment_name' => 'X-Ray Unit',
            'impact' => $impact,
            'unavailable_items' => $impact === MaintenanceRecord::IMPACT_NO_CLOSURE ? 'No X-ray available' : null,
            'starts_at' => '2026-12-01 09:00:00',
            'ends_at' => '2026-12-01 12:00:00',
            'status' => MaintenanceRecord::STATUS_SCHEDULED,
        ], $overrides));
        $record->operatories()->sync(array_map(fn ($r) => $r->id, $rooms));

        return $record;
    }

    protected function assess(Operatory $room): array
    {
        return app(RoomAvailabilityService::class)->assess($this->practice->id, $room->id, $this->slotStart, $this->slotEnd);
    }

    // ── Service ────────────────────────────────────────────────

    #[Test]
    public function branch_closure_closes_every_room(): void
    {
        $this->maintenance(MaintenanceRecord::IMPACT_CLOSES_BRANCH);

        $this->assertNotEmpty($this->assess($this->room1)['closures']);
        $this->assertNotEmpty($this->assess($this->room2)['closures']);
    }

    #[Test]
    public function room_closure_only_closes_selected_rooms(): void
    {
        $this->maintenance(MaintenanceRecord::IMPACT_CLOSES_ROOMS, [$this->room1]);

        $this->assertNotEmpty($this->assess($this->room1)['closures']);
        $this->assertEmpty($this->assess($this->room2)['closures']);
        $this->assertEmpty($this->assess($this->room2)['warnings']);
    }

    #[Test]
    public function no_closure_returns_warnings_not_closures(): void
    {
        $this->maintenance(MaintenanceRecord::IMPACT_NO_CLOSURE, [$this->room1]);

        $a = $this->assess($this->room1);
        $this->assertEmpty($a['closures']);
        $this->assertCount(1, $a['warnings']);
        $this->assertSame('No X-ray available', $a['warnings'][0]['unavailable_items']);
        $this->assertEmpty($this->assess($this->room2)['warnings']);
    }

    #[Test]
    public function no_closure_without_rooms_warns_whole_branch(): void
    {
        $this->maintenance(MaintenanceRecord::IMPACT_NO_CLOSURE);

        $this->assertCount(1, $this->assess($this->room1)['warnings']);
        $this->assertCount(1, $this->assess($this->room2)['warnings']);
    }

    #[Test]
    public function completed_cancelled_and_out_of_window_jobs_are_ignored(): void
    {
        $this->maintenance(MaintenanceRecord::IMPACT_CLOSES_BRANCH, [], ['status' => MaintenanceRecord::STATUS_COMPLETED]);
        $this->maintenance(MaintenanceRecord::IMPACT_CLOSES_BRANCH, [], ['status' => MaintenanceRecord::STATUS_CANCELLED]);
        $this->maintenance(MaintenanceRecord::IMPACT_CLOSES_BRANCH, [], ['starts_at' => '2026-12-01 10:30:00', 'ends_at' => '2026-12-01 11:00:00']);

        $a = $this->assess($this->room1);
        $this->assertEmpty($a['closures']);
        $this->assertEmpty($a['warnings']);
    }

    #[Test]
    public function other_tenants_maintenance_has_no_effect(): void
    {
        $otherBranch = Branch::where('practice_id', $this->otherPractice->id)->first();
        MaintenanceRecord::create([
            'practice_id' => $this->otherPractice->id, 'branch_id' => $otherBranch->id, 'title' => 'Foreign',
            'impact' => MaintenanceRecord::IMPACT_CLOSES_BRANCH, 'starts_at' => '2026-12-01 09:00:00',
            'ends_at' => '2026-12-01 12:00:00', 'status' => 'scheduled',
        ]);

        $this->assertEmpty($this->assess($this->room1)['closures']);
    }

    #[Test]
    public function utc_iso_input_from_browser_is_compared_in_app_timezone(): void
    {
        config(['app.timezone' => 'Africa/Cairo']);
        $this->maintenance(MaintenanceRecord::IMPACT_CLOSES_ROOMS, [$this->room1], [
            'starts_at' => '2026-12-01 13:00:00', 'ends_at' => '2026-12-01 15:00:00',
        ]);

        // 13:30 Cairo (UTC+2) sent by the browser as 11:30Z
        $a = app(RoomAvailabilityService::class)->assess(
            $this->practice->id, $this->room1->id,
            RoomAvailabilityService::parse('2026-12-01T11:30:00Z'),
            RoomAvailabilityService::parse('2026-12-01T12:00:00Z'),
        );

        $this->assertNotEmpty($a['closures']);
    }

    #[Test]
    public function assert_bookable_requires_acknowledgement_for_warnings(): void
    {
        $this->maintenance(MaintenanceRecord::IMPACT_NO_CLOSURE, [$this->room1]);
        $service = app(RoomAvailabilityService::class);

        try {
            $service->assertBookable($this->practice->id, $this->room1->id, $this->slotStart, $this->slotEnd);
            $this->fail('Expected a ValidationException');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('operatory_id', $e->errors());
        }

        $result = $service->assertBookable($this->practice->id, $this->room1->id, $this->slotStart, $this->slotEnd, null, null, true);
        $this->assertCount(1, $result['warnings']);
    }

    #[Test]
    public function affected_appointments_lists_bookings_inside_closure(): void
    {
        $this->bookDirect($this->room1);
        $this->bookDirect($this->room2, '2026-12-01 11:00:00');
        $record = $this->maintenance(MaintenanceRecord::IMPACT_CLOSES_ROOMS, [$this->room1]);

        $affected = app(RoomAvailabilityService::class)->affectedAppointments($record);
        $this->assertCount(1, $affected);
        $this->assertSame($this->room1->id, $affected->first()->operatory_id);
    }

    // ── CalendarController API ────────────────────────────────

    protected function apiPayload(Operatory $room, array $extra = []): array
    {
        return array_merge([
            'patient_id' => $this->patient->id,
            'doctor_id' => $this->doctor->id,
            'operatory_id' => $room->id,
            'start_time' => $this->slotStart->toDateTimeString(),
            'end_time' => $this->slotEnd->toDateTimeString(),
        ], $extra);
    }

    #[Test]
    public function api_store_rejects_closed_room(): void
    {
        $this->maintenance(MaintenanceRecord::IMPACT_CLOSES_ROOMS, [$this->room1]);

        $this->actingAs($this->secretary)
            ->postJson('/api/calendar/appointments', $this->apiPayload($this->room1))
            ->assertStatus(422)
            ->assertJsonFragment(['message' => 'Chair 1 is closed for maintenance: Job closes_rooms (01 Dec 09:00 AM – 01 Dec 12:00 PM).']);

        $this->assertSame(0, Appointment::count());
    }

    #[Test]
    public function api_store_requires_acknowledgement_then_books(): void
    {
        $this->maintenance(MaintenanceRecord::IMPACT_NO_CLOSURE, [$this->room1]);

        $this->actingAs($this->secretary)
            ->postJson('/api/calendar/appointments', $this->apiPayload($this->room1))
            ->assertStatus(409)
            ->assertJsonPath('requires_acknowledgement', true)
            ->assertJsonPath('warnings.0.unavailable_items', 'No X-ray available');

        $this->actingAs($this->secretary)
            ->postJson('/api/calendar/appointments', $this->apiPayload($this->room1, ['acknowledge_maintenance' => true]))
            ->assertStatus(201);

        $this->assertSame(1, Appointment::count());
    }

    #[Test]
    public function api_still_rejects_doctor_overlap(): void
    {
        $this->bookDirect($this->room2);

        $this->actingAs($this->secretary)
            ->postJson('/api/calendar/appointments', $this->apiPayload($this->room1))
            ->assertStatus(422)
            ->assertJsonFragment(['message' => RoomAvailabilityService::CONFLICT_MESSAGE]);
    }

    #[Test]
    public function api_reschedule_into_closure_is_blocked(): void
    {
        $appointment = $this->bookDirect($this->room1, '2026-12-02 10:00:00');
        $this->maintenance(MaintenanceRecord::IMPACT_CLOSES_BRANCH);

        $this->actingAs($this->secretary)
            ->patchJson("/api/calendar/appointments/{$appointment->id}/reschedule", [
                'start_time' => $this->slotStart->toDateTimeString(),
                'end_time' => $this->slotEnd->toDateTimeString(),
            ])
            ->assertStatus(422);

        $this->assertTrue($appointment->fresh()->start_time->isSameDay('2026-12-02'));
    }

    // ── Policy ─────────────────────────────────────────────────

    #[Test]
    public function only_admin_and_secretary_manage_maintenance(): void
    {
        $record = $this->maintenance(MaintenanceRecord::IMPACT_NO_CLOSURE);

        $this->assertTrue($this->secretary->can('create', MaintenanceRecord::class));
        $this->assertTrue($this->secretary->can('update', $record));
        $this->assertFalse($this->secretary->can('delete', $record));

        $this->assertTrue($this->doctor->can('viewAny', MaintenanceRecord::class));
        $this->assertTrue($this->doctor->can('view', $record));
        $this->assertFalse($this->doctor->can('create', MaintenanceRecord::class));
        $this->assertFalse($this->doctor->can('update', $record));
    }

    // ── React calendar widget backend ─────────────────────────

    protected function widgetAs(User $user): AppointmentsCalendarWidget
    {
        $this->actingAs($user);
        Filament::setTenant($this->practice);

        return new AppointmentsCalendarWidget;
    }

    protected function bookDirect(Operatory $room, string $start = '2026-12-01 10:00:00', ?User $doctor = null): Appointment
    {
        return Appointment::create([
            'practice_id' => $this->practice->id, 'branch_id' => $this->branch->id, 'operatory_id' => $room->id,
            'patient_id' => $this->patient->id, 'doctor_id' => ($doctor ?? $this->doctor)->id,
            'start_time' => $start, 'end_time' => Carbon::parse($start)->addMinutes(30), 'status' => 'booked',
        ]);
    }

    #[Test]
    public function widget_events_are_scoped_to_doctor_and_include_maintenance(): void
    {
        $this->bookDirect($this->room1);
        $this->bookDirect($this->room2, '2026-12-01 10:00:00', $this->doctor2);
        $this->maintenance(MaintenanceRecord::IMPACT_CLOSES_ROOMS, [$this->room1]);

        $doctorView = $this->widgetAs($this->doctor)->fetchEvents('2026-12-01', '2026-12-02');
        $appts = array_filter($doctorView['events'], fn ($e) => $e['extendedProps']['kind'] === 'appointment');
        $this->assertCount(1, $appts);
        $this->assertCount(1, $doctorView['maintenance']);

        $secretaryView = $this->widgetAs($this->secretary)->fetchEvents('2026-12-01', '2026-12-02');
        $appts = array_filter($secretaryView['events'], fn ($e) => $e['extendedProps']['kind'] === 'appointment');
        $this->assertCount(2, $appts);
    }

    #[Test]
    public function widget_booking_blocks_closures_and_needs_ack_for_warnings(): void
    {
        $this->maintenance(MaintenanceRecord::IMPACT_CLOSES_ROOMS, [$this->room1]);
        $this->maintenance(MaintenanceRecord::IMPACT_NO_CLOSURE, [$this->room2]);
        $widget = $this->widgetAs($this->secretary);
        $data = fn (Operatory $room) => [
            'patient_id' => $this->patient->id, 'doctor_id' => $this->doctor->id, 'operatory_id' => $room->id,
            'start' => $this->slotStart->toIso8601String(), 'end' => $this->slotEnd->toIso8601String(),
        ];

        $closed = $widget->bookAppointment($data($this->room1));
        $this->assertFalse($closed['ok']);
        $this->assertFalse($closed['requiresAcknowledgement']);

        $partial = $widget->bookAppointment($data($this->room2));
        $this->assertFalse($partial['ok']);
        $this->assertTrue($partial['requiresAcknowledgement']);

        $this->assertTrue($widget->bookAppointment($data($this->room2), true)['ok']);
        $this->assertSame(1, Appointment::count());
    }

    #[Test]
    public function doctor_cannot_reschedule_another_doctors_appointment(): void
    {
        $appointment = $this->bookDirect($this->room2, '2026-12-01 14:00:00', $this->doctor2);

        $this->expectException(HttpException::class);
        $this->widgetAs($this->doctor)->rescheduleAppointment($appointment->id, '2026-12-01T15:00:00', '2026-12-01T15:30:00');
    }

    #[Test]
    public function widget_status_transitions_are_restricted(): void
    {
        $appointment = $this->bookDirect($this->room1);
        $widget = $this->widgetAs($this->secretary);

        $this->assertFalse($widget->updateStatus($appointment->id, 'completed')['ok']);
        $this->assertTrue($widget->updateStatus($appointment->id, 'arrived')['ok']);
        $this->assertSame('arrived', $appointment->fresh()->status);
    }
}
