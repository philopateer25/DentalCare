<?php

namespace App\Services;

use App\Filament\Resources\PatientResource;
use App\Models\Appointment;
use App\Models\FollowUpRequest;
use App\Models\Operatory;
use App\Models\User;
use App\Services\Scheduling\RoomAvailabilityService;
use Filament\Notifications\Actions\Action as NotificationAction;
use Filament\Notifications\Notification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Doctor → reception follow-up requests.
 *
 * - request(): the doctor asks from the chair; reception is notified (bell) and the request
 *   appears on the reception dashboard within seconds (the widget polls).
 * - book(): reception books the appointment (same room/clash/maintenance rules as everywhere);
 *   the doctor is notified.
 * - dismiss(): reception closes a request without booking, with a reason the doctor sees.
 */
class FollowUpRequests
{
    public const RECEPTION_ROLES = ['secretary', 'clinic_admin', 'super_admin'];

    public function __construct(protected RoomAvailabilityService $availability) {}

    public function request(User $doctor, array $data): FollowUpRequest
    {
        $request = FollowUpRequest::create([
            'practice_id' => $doctor->practice_id,
            'patient_id' => $data['patient_id'],
            'doctor_id' => $doctor->id,
            'source_appointment_id' => $data['source_appointment_id'] ?? null,
            'timeframe' => $data['timeframe'],
            'preferred_date' => $data['timeframe'] === 'date' ? ($data['preferred_date'] ?? null) : null,
            'duration_minutes' => (int) ($data['duration_minutes'] ?? 30),
            'procedure_name' => $data['procedure_name'] ?? null,
            'notes' => $data['notes'] ?? null,
            'urgent' => (bool) ($data['urgent'] ?? false),
            'status' => FollowUpRequest::STATUS_PENDING,
        ]);

        $request->load('patient');
        $receptionists = User::where('practice_id', $doctor->practice_id)->role(self::RECEPTION_ROLES)->get();
        if ($receptionists->isNotEmpty()) {
            Notification::make()
                ->title(($request->urgent ? '🔴 Urgent ' : '').'Follow-up request: '.trim($request->patient->first_name.' '.$request->patient->last_name))
                ->body("{$doctor->name} · {$request->timeframeLabel()}".($request->procedure_name ? " · {$request->procedure_name}" : ''))
                ->icon('heroicon-o-calendar-days')
                ->color($request->urgent ? 'danger' : 'info')
                ->sendToDatabase($receptionists);
        }

        return $request;
    }

    /**
     * @param  array{start_time: mixed, duration_minutes: int|string, doctor_id: int|string, operatory_id: int|string, procedure_name?: ?string, notes?: ?string}  $data
     * @return array{appointment: Appointment, warning: ?string}
     *
     * @throws ValidationException room closed / clash
     */
    public function book(FollowUpRequest $request, User $handler, array $data): array
    {
        abort_unless($request->practice_id === $handler->practice_id, 403);
        abort_unless($request->status === FollowUpRequest::STATUS_PENDING, 422, 'This request was already handled.');

        $start = RoomAvailabilityService::parse($data['start_time']);
        $end = $start->copy()->addMinutes((int) $data['duration_minutes']);
        $operatory = Operatory::whereKey($data['operatory_id'])
            ->whereHas('branch', fn ($q) => $q->where('practice_id', $request->practice_id))
            ->firstOrFail();

        // Closures and clashes throw; missing equipment is allowed but reported back.
        $assessment = $this->availability->assertBookable(
            $request->practice_id, $operatory->id, $start, $end, (int) $data['doctor_id'], null, true
        );

        $appointment = DB::transaction(function () use ($request, $handler, $data, $start, $end, $operatory) {
            $appointment = Appointment::create([
                'practice_id' => $request->practice_id,
                'branch_id' => $operatory->branch_id,
                'operatory_id' => $operatory->id,
                'patient_id' => $request->patient_id,
                'doctor_id' => (int) $data['doctor_id'],
                'status' => 'booked',
                'start_time' => $start,
                'end_time' => $end,
                'type' => 'follow_up',
                'parent_appointment_id' => $request->source_appointment_id,
                'procedure_name' => $data['procedure_name'] ?? $request->procedure_name,
                'notes' => $data['notes'] ?? $request->notes,
            ]);

            $request->update([
                'status' => FollowUpRequest::STATUS_BOOKED,
                'booked_appointment_id' => $appointment->id,
                'handled_by' => $handler->id,
                'handled_at' => now(),
            ]);

            return $appointment;
        });

        $this->notifyDoctor($request, 'Follow-up booked', $start->format('D j M, h:i A').' · '.$operatory->name.' · by '.$handler->name, 'success');

        return ['appointment' => $appointment, 'warning' => $this->availability->warningMessage($assessment)];
    }

    public function dismiss(FollowUpRequest $request, User $handler, ?string $reason): void
    {
        abort_unless($request->practice_id === $handler->practice_id, 403);
        abort_unless($request->status === FollowUpRequest::STATUS_PENDING, 422, 'This request was already handled.');

        $request->update([
            'status' => FollowUpRequest::STATUS_CANCELLED,
            'handled_by' => $handler->id,
            'handled_at' => now(),
            'handled_note' => $reason,
        ]);

        $this->notifyDoctor($request, 'Follow-up not booked', ($reason ?: 'No reason given').' · by '.$handler->name, 'warning');
    }

    protected function notifyDoctor(FollowUpRequest $request, string $title, string $body, string $color): void
    {
        $request->loadMissing(['doctor', 'patient']);
        if (! $request->doctor) {
            return;
        }

        $notification = Notification::make()
            ->title($title.': '.trim($request->patient->first_name.' '.$request->patient->last_name))
            ->body($body)
            ->color($color)
            ->icon('heroicon-o-calendar-days');

        try {
            $notification->actions([
                NotificationAction::make('open')->label('Open patient')
                    ->url(PatientResource::getUrl('view', ['record' => $request->patient_id], tenant: $request->patient->practice)),
            ]);
        } catch (\Throwable) {
            // No panel context (e.g. queue/CLI): send without the link.
        }

        $notification->sendToDatabase($request->doctor);
    }

    /** Suggested start for the booking form: the request's target date at 10:00, never in the past. */
    public static function suggestedStart(FollowUpRequest $request): Carbon
    {
        $date = $request->suggestedDate();

        return $date->isPast() ? now()->addHour()->startOfHour() : $date;
    }
}
