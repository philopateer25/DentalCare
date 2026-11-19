<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\AppointmentResource;
use App\Filament\Resources\MaintenanceResource;
use App\Filament\Resources\PatientResource;
use App\Models\Appointment;
use App\Models\Branch;
use App\Models\MaintenanceRecord;
use App\Models\Operatory;
use App\Models\Patient;
use App\Models\User;
use App\Services\Scheduling\RoomAvailabilityService;
use Filament\Facades\Filament;
use Filament\Widgets\Widget;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Renderless;

/**
 * Dashboard appointments calendar rendered by React + FullCalendar
 * (resources/js/filament/appointments-calendar.jsx). React talks to this
 * component through $wire calls; every method enforces tenant + role scope.
 */
class AppointmentsCalendarWidget extends Widget
{
    protected static string $view = 'filament.widgets.appointments-calendar';

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    /** Render eagerly so the React mount point exists on first paint. */
    protected static bool $isLazy = false;

    public const STATUSES = [
        'booked' => 'Booked',
        'arrived' => 'Arrived (Waiting)',
        'in_chair' => 'In Chair (Active)',
        'completed' => 'Completed',
        'cancelled' => 'Cancelled',
        'no_show' => 'No Show',
    ];

    /** Allowed one-click transitions (mirrors the old table widget actions). */
    public const TRANSITIONS = [
        'booked' => ['arrived', 'cancelled', 'no_show'],
        'arrived' => ['in_chair', 'cancelled'],
        'in_chair' => ['completed'],
        'completed' => [],
        'cancelled' => ['booked'],
        'no_show' => ['booked'],
    ];

    public static function canView(): bool
    {
        return auth()->user()?->hasAnyRole(['clinic_admin', 'super_admin', 'developer', 'secretary', 'doctor']) ?? false;
    }

    protected function practiceId(): int
    {
        return (int) (Filament::getTenant()?->id ?? auth()->user()?->practice_id);
    }

    protected function isDoctorOnly(): bool
    {
        $user = auth()->user();

        return $user && $user->hasRole('doctor') && ! $user->hasAnyRole(['clinic_admin', 'super_admin', 'developer', 'secretary']);
    }

    public function getHeading(): string
    {
        $user = auth()->user();

        if ($this->isDoctorOnly()) {
            return 'My Appointments Calendar';
        }

        if ($user && $user->hasRole('secretary') && ! $user->hasAnyRole(['clinic_admin', 'super_admin', 'developer'])) {
            return 'Reception & Clinic Calendar';
        }

        return 'Clinic Appointments Calendar';
    }

    /** Static config handed to React via a data attribute. */
    public function getConfig(): array
    {
        $tenant = Filament::getTenant();

        return [
            'heading' => $this->getHeading(),
            'isDoctorOnly' => $this->isDoctorOnly(),
            'currentUserId' => auth()->id(),
            'canBook' => true,
            'statuses' => self::STATUSES,
            'transitions' => self::TRANSITIONS,
            'locale' => app()->getLocale(),
            'urls' => [
                'appointments' => AppointmentResource::getUrl('index', tenant: $tenant),
                'maintenance' => MaintenanceResource::getUrl('index', tenant: $tenant),
                'patientView' => PatientResource::getUrl('view', ['record' => '__ID__'], tenant: $tenant),
                'appointmentEdit' => AppointmentResource::getUrl('edit', ['record' => '__ID__'], tenant: $tenant),
            ],
        ];
    }

    #[Renderless]
    public function fetchOptions(): array
    {
        $practiceId = $this->practiceId();

        $doctors = User::where('practice_id', $practiceId)
            ->whereHas('roles', fn ($q) => $q->where('name', 'doctor'))
            ->when($this->isDoctorOnly(), fn ($q) => $q->whereKey(auth()->id()))
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn ($d) => ['id' => $d->id, 'name' => $d->name])
            ->values();

        $rooms = Operatory::whereHas('branch', fn ($q) => $q->where('practice_id', $practiceId))
            ->with('branch:id,name')
            ->orderBy('branch_id')->orderBy('name')
            ->get()
            ->map(fn ($o) => ['id' => $o->id, 'name' => $o->name, 'branch' => $o->branch?->name, 'branch_id' => $o->branch_id])
            ->values();

        return ['doctors' => $doctors, 'rooms' => $rooms];
    }

    #[Renderless]
    public function searchPatients(string $query = ''): array
    {
        $query = trim($query);

        return Patient::where('practice_id', $this->practiceId())
            ->when($query !== '', function ($q) use ($query) {
                $q->where(function ($w) use ($query) {
                    $w->where('first_name', 'like', "%{$query}%")
                        ->orWhere('last_name', 'like', "%{$query}%")
                        ->orWhere('file_number', 'like', "%{$query}%")
                        ->orWhere('phone', 'like', "%{$query}%");
                });
            })
            ->orderBy('first_name')
            ->limit(20)
            ->get(['id', 'first_name', 'last_name', 'file_number', 'phone'])
            ->map(fn ($p) => [
                'id' => $p->id,
                'name' => trim("{$p->first_name} {$p->last_name}"),
                'file_number' => $p->file_number,
                'phone' => $p->phone,
            ])
            ->all();
    }

    #[Renderless]
    public function fetchEvents(string $start, string $end, array $filters = []): array
    {
        $practiceId = $this->practiceId();
        $from = RoomAvailabilityService::parse($start);
        $to = RoomAvailabilityService::parse($end);
        $userId = auth()->id();
        $doctorOnly = $this->isDoctorOnly();

        $appointments = Appointment::query()
            ->where('practice_id', $practiceId)
            ->when($doctorOnly, fn ($q) => $q->where('doctor_id', $userId))
            ->when(! $doctorOnly && ! empty($filters['doctor_id']), fn ($q) => $q->where('doctor_id', (int) $filters['doctor_id']))
            ->when(! empty($filters['operatory_id']), fn ($q) => $q->where('operatory_id', (int) $filters['operatory_id']))
            ->when(! empty($filters['status']) && isset(self::STATUSES[$filters['status']]), fn ($q) => $q->where('status', $filters['status']))
            ->where('start_time', '<', $to)
            ->where('end_time', '>', $from)
            ->with(['patient', 'doctor', 'operatory'])
            ->orderBy('start_time')
            ->get()
            ->map(fn (Appointment $a) => [
                'id' => (string) $a->id,
                'title' => $a->patient?->full_name ?? 'Patient',
                'start' => $a->start_time?->toIso8601String(),
                'end' => $a->end_time?->toIso8601String(),
                'editable' => ! $doctorOnly || $a->doctor_id === $userId,
                'extendedProps' => [
                    'kind' => 'appointment',
                    'appointmentId' => $a->id,
                    'status' => $a->status ?? 'booked',
                    'patientId' => $a->patient_id,
                    'patientName' => $a->patient?->full_name,
                    'fileNumber' => $a->patient?->file_number,
                    'phone' => $a->patient?->phone,
                    'doctorId' => $a->doctor_id,
                    'doctorName' => $a->doctor?->name,
                    'operatoryId' => $a->operatory_id,
                    'operatoryName' => $a->operatory?->name,
                    'complaint' => $a->chief_complaint,
                    'procedure' => $a->procedure_name,
                    'notes' => $a->notes,
                    'type' => $a->type,
                ],
            ])
            ->values()
            ->all();

        $roomFilter = ! empty($filters['operatory_id']) ? Operatory::find((int) $filters['operatory_id']) : null;

        $maintenance = MaintenanceRecord::query()
            ->where('practice_id', $practiceId)
            ->active()
            ->overlapping($from, $to)
            ->when($roomFilter, fn ($q) => $q->affectingOperatory($roomFilter))
            ->with(['operatories:id,name', 'branch:id,name'])
            ->get()
            ->map(function (MaintenanceRecord $m) {
                $rooms = $m->impact === MaintenanceRecord::IMPACT_CLOSES_BRANCH || $m->operatories->isEmpty()
                    ? 'All rooms'.($m->branch ? " · {$m->branch->name}" : '')
                    : $m->operatories->pluck('name')->implode(', ');

                return [
                    'id' => 'm-'.$m->id,
                    'title' => ($m->closesRoom() ? 'Closed: ' : 'Maintenance: ').$m->title,
                    'start' => $m->starts_at->toIso8601String(),
                    'end' => $m->ends_at->toIso8601String(),
                    'display' => 'background',
                    'classNames' => [$m->closesRoom() ? 'dc-maint-closed' : 'dc-maint-partial'],
                    'extendedProps' => [
                        'kind' => 'maintenance',
                        'maintenanceId' => $m->id,
                        'impact' => $m->impact,
                        'impactLabel' => MaintenanceRecord::IMPACTS[$m->impact] ?? $m->impact,
                        'rooms' => $rooms,
                        'equipment' => $m->equipment_name,
                        'unavailable' => $m->unavailable_items,
                        'status' => $m->status,
                    ],
                ];
            })
            ->values()
            ->all();

        return [
            'events' => array_merge($appointments, $maintenance),
            'maintenance' => array_map(fn ($m) => [
                'id' => $m['extendedProps']['maintenanceId'],
                'title' => $m['title'],
                'start' => $m['start'],
                'end' => $m['end'],
                ...$m['extendedProps'],
            ], $maintenance),
        ];
    }

    /** Pre-flight check used by the booking modal and drag/drop. */
    #[Renderless]
    public function checkSlot(?int $operatoryId, ?string $start, ?string $end, ?int $doctorId = null, ?int $ignoreId = null): array
    {
        if (! $operatoryId || ! $start || ! $end) {
            return ['appointment_conflict' => false, 'closures' => [], 'warnings' => [], 'operatory_name' => null];
        }

        if ($this->isDoctorOnly()) {
            $doctorId = auth()->id();
        }

        return app(RoomAvailabilityService::class)->assess(
            $this->practiceId(),
            $operatoryId,
            RoomAvailabilityService::parse($start),
            RoomAvailabilityService::parse($end),
            $doctorId,
            $ignoreId,
        );
    }

    #[Renderless]
    public function bookAppointment(array $data, bool $acknowledged = false): array
    {
        $practiceId = $this->practiceId();

        $patient = Patient::where('practice_id', $practiceId)->find($data['patient_id'] ?? null);
        $doctorId = $this->isDoctorOnly() ? auth()->id() : (int) ($data['doctor_id'] ?? 0);
        $doctor = User::where('practice_id', $practiceId)->find($doctorId);
        $operatory = Operatory::whereHas('branch', fn ($q) => $q->where('practice_id', $practiceId))->find($data['operatory_id'] ?? null);

        if (! $patient || ! $doctor || ! $operatory || empty($data['start'])) {
            return ['ok' => false, 'message' => 'Please choose a patient, doctor, room and time.'];
        }

        $start = RoomAvailabilityService::parse($data['start']);
        $end = ! empty($data['end'])
            ? RoomAvailabilityService::parse($data['end'])
            : $start->copy()->addMinutes(max(5, (int) ($data['duration'] ?? 30)));

        if ($end->lte($start)) {
            return ['ok' => false, 'message' => 'End time must be after the start time.'];
        }

        if ($blocked = $this->guard($practiceId, $operatory->id, $start, $end, $doctor->id, null, $acknowledged)) {
            return $blocked;
        }

        $appointment = Appointment::create([
            'practice_id' => $practiceId,
            'branch_id' => $operatory->branch_id ?? Branch::where('practice_id', $practiceId)->value('id'),
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'operatory_id' => $operatory->id,
            'start_time' => $start,
            'end_time' => $end,
            'chief_complaint' => $data['chief_complaint'] ?? null,
            'status' => 'booked',
        ]);

        return ['ok' => true, 'message' => 'Appointment booked', 'id' => $appointment->id];
    }

    #[Renderless]
    public function rescheduleAppointment(int $id, string $start, string $end, bool $acknowledged = false): array
    {
        $appointment = $this->ownedAppointment($id);
        $startAt = RoomAvailabilityService::parse($start);
        $endAt = RoomAvailabilityService::parse($end);

        if ($endAt->lte($startAt)) {
            return ['ok' => false, 'message' => 'End time must be after the start time.'];
        }

        if ($blocked = $this->guard($this->practiceId(), (int) $appointment->operatory_id, $startAt, $endAt, (int) $appointment->doctor_id, $appointment->id, $acknowledged)) {
            return $blocked;
        }

        $appointment->update(['start_time' => $startAt, 'end_time' => $endAt]);

        return ['ok' => true, 'message' => 'Appointment moved to '.$startAt->format('D d M, h:i A')];
    }

    #[Renderless]
    public function updateStatus(int $id, string $status): array
    {
        $appointment = $this->ownedAppointment($id);

        if (! in_array($status, self::TRANSITIONS[$appointment->status] ?? [], true)) {
            return ['ok' => false, 'message' => 'That status change is not allowed.'];
        }

        $appointment->update(['status' => $status]);

        return ['ok' => true, 'message' => 'Status updated to '.(self::STATUSES[$status] ?? $status)];
    }

    protected function ownedAppointment(int $id): Appointment
    {
        $appointment = Appointment::findOrFail($id);

        abort_unless((int) $appointment->practice_id === $this->practiceId(), 403, 'Unauthorized cross-tenant access.');
        abort_if($this->isDoctorOnly() && (int) $appointment->doctor_id !== (int) auth()->id(), 403, 'Doctors can only manage their own appointments.');

        return $appointment;
    }

    /** Returns a failure payload for React, or null when the slot is bookable. */
    protected function guard(int $practiceId, int $operatoryId, $start, $end, ?int $doctorId, ?int $ignoreId, bool $acknowledged): ?array
    {
        try {
            app(RoomAvailabilityService::class)->assertBookable($practiceId, $operatoryId, $start, $end, $doctorId, $ignoreId, $acknowledged);
        } catch (ValidationException $e) {
            $assessment = app(RoomAvailabilityService::class)->assess($practiceId, $operatoryId, $start, $end, $doctorId, $ignoreId);
            $needsAck = empty($assessment['closures']) && ! $assessment['appointment_conflict'] && ! empty($assessment['warnings']);

            return [
                'ok' => false,
                'requiresAcknowledgement' => $needsAck,
                'message' => collect($e->errors())->flatten()->first(),
                'assessment' => $assessment,
            ];
        }

        return null;
    }
}
