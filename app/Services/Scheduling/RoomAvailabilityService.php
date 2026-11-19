<?php

namespace App\Services\Scheduling;

use App\Models\Appointment;
use App\Models\MaintenanceRecord;
use App\Models\Operatory;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Single source of truth for "can this room / doctor take an appointment
 * in this time window?" — combines the classic doctor/chair overlap rule
 * with room maintenance (closures block, partial outages warn).
 */
class RoomAvailabilityService
{
    public const CONFLICT_MESSAGE = 'Time slot conflict: The selected doctor or operatory/chair is already booked during this time interval.';

    /**
     * @return array{
     *     appointment_conflict: bool,
     *     closures: array<int, array<string, mixed>>,
     *     warnings: array<int, array<string, mixed>>,
     *     operatory_name: ?string
     * }
     */
    public function assess(
        int $practiceId,
        int $operatoryId,
        CarbonInterface $start,
        CarbonInterface $end,
        ?int $doctorId = null,
        ?int $ignoreAppointmentId = null,
    ): array {
        $start = self::parse($start);
        $end = self::parse($end);

        $operatory = Operatory::whereHas('branch', fn ($q) => $q->where('practice_id', $practiceId))
            ->find($operatoryId);

        $result = [
            'appointment_conflict' => $this->hasAppointmentConflict($practiceId, $operatoryId, $start, $end, $doctorId, $ignoreAppointmentId),
            'closures' => [],
            'warnings' => [],
            'operatory_name' => $operatory?->name,
        ];

        if (! $operatory) {
            return $result;
        }

        foreach ($this->maintenanceFor($practiceId, $operatory, $start, $end) as $record) {
            $payload = $this->present($record);
            if ($record->closesRoom()) {
                $result['closures'][] = $payload;
            } else {
                $result['warnings'][] = $payload;
            }
        }

        return $result;
    }

    /**
     * Throws a ValidationException (keyed on operatory_id) when the slot is not
     * bookable. Maintenance warnings only block until they are acknowledged.
     */
    public function assertBookable(
        int $practiceId,
        int $operatoryId,
        CarbonInterface $start,
        CarbonInterface $end,
        ?int $doctorId = null,
        ?int $ignoreAppointmentId = null,
        bool $acknowledged = false,
    ): array {
        $assessment = $this->assess($practiceId, $operatoryId, $start, $end, $doctorId, $ignoreAppointmentId);

        if ($message = $this->closureMessage($assessment)) {
            throw ValidationException::withMessages(['operatory_id' => $message]);
        }

        if ($assessment['appointment_conflict']) {
            throw ValidationException::withMessages(['operatory_id' => self::CONFLICT_MESSAGE]);
        }

        if (! $acknowledged && ! empty($assessment['warnings'])) {
            throw ValidationException::withMessages(['operatory_id' => $this->warningMessage($assessment)]);
        }

        return $assessment;
    }

    public function closureMessage(array $assessment): ?string
    {
        if (empty($assessment['closures'])) {
            return null;
        }

        $c = $assessment['closures'][0];
        $room = $assessment['operatory_name'] ?? 'This room';

        return "{$room} is closed for maintenance: {$c['title']} ({$c['starts_at_label']} – {$c['ends_at_label']}).";
    }

    public function warningMessage(array $assessment): ?string
    {
        if (empty($assessment['warnings'])) {
            return null;
        }

        $room = $assessment['operatory_name'] ?? 'this room';
        $items = collect($assessment['warnings'])
            ->map(fn ($w) => $w['unavailable_items'] ?: ($w['equipment_name'] ?: $w['title']))
            ->implode('; ');

        return "Maintenance in {$room}: {$items}. Please confirm to book anyway.";
    }

    /** Active maintenance affecting a room in a time window. */
    public function maintenanceFor(int $practiceId, Operatory $operatory, CarbonInterface $start, CarbonInterface $end): Collection
    {
        return MaintenanceRecord::query()
            ->where('practice_id', $practiceId)
            ->active()
            ->overlapping($start, $end)
            ->affectingOperatory($operatory)
            ->orderBy('starts_at')
            ->get();
    }

    /** Non-cancelled appointments that fall inside a maintenance record's rooms & window. */
    public function affectedAppointments(MaintenanceRecord $record): Collection
    {
        $roomIds = $record->affectedOperatoryIds();
        if (empty($roomIds) || ! $record->starts_at || ! $record->ends_at) {
            return collect();
        }

        return Appointment::query()
            ->where('practice_id', $record->practice_id)
            ->whereIn('operatory_id', $roomIds)
            ->whereNotIn('status', ['cancelled', 'no_show'])
            ->where('start_time', '<', $record->ends_at)
            ->where('end_time', '>', $record->starts_at)
            ->with(['patient', 'doctor', 'operatory'])
            ->orderBy('start_time')
            ->get();
    }

    /** Same overlap rule CalendarController has always used. */
    public function hasAppointmentConflict(
        int $practiceId,
        int $operatoryId,
        CarbonInterface $start,
        CarbonInterface $end,
        ?int $doctorId = null,
        ?int $ignoreAppointmentId = null,
    ): bool {
        return Appointment::where('practice_id', $practiceId)
            ->when($ignoreAppointmentId, fn ($q) => $q->where('id', '!=', $ignoreAppointmentId))
            ->where('status', '!=', 'cancelled')
            ->where(function ($q) use ($doctorId, $operatoryId) {
                $q->where('operatory_id', $operatoryId)
                    ->when($doctorId, fn ($q) => $q->orWhere('doctor_id', $doctorId));
            })
            ->where('start_time', '<', $end)
            ->where('end_time', '>', $start)
            ->exists();
    }

    public function present(MaintenanceRecord $record): array
    {
        return [
            'id' => $record->id,
            'title' => $record->title,
            'category' => MaintenanceRecord::CATEGORIES[$record->category] ?? $record->category,
            'equipment_name' => $record->equipment_name,
            'unavailable_items' => $record->unavailable_items,
            'impact' => $record->impact,
            'impact_label' => MaintenanceRecord::IMPACTS[$record->impact] ?? $record->impact,
            'status' => $record->status,
            'starts_at' => $record->starts_at?->toIso8601String(),
            'ends_at' => $record->ends_at?->toIso8601String(),
            'starts_at_label' => $record->starts_at?->format('d M h:i A'),
            'ends_at_label' => $record->ends_at?->format('d M h:i A'),
        ];
    }

    /**
     * Parses a date (e.g. an ISO string with "Z" from the browser) and converts
     * it to the app timezone — datetimes are stored as naive app-local values,
     * so comparing a UTC Carbon directly would shift the window by the offset.
     */
    public static function parse(mixed $value): Carbon
    {
        $date = $value instanceof CarbonInterface ? Carbon::instance($value) : Carbon::parse($value);

        return $date->setTimezone(config('app.timezone'));
    }
}
