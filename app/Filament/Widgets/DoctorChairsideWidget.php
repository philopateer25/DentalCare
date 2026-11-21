<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\InvoiceResource;
use App\Filament\Resources\PatientResource;
use App\Filament\Support\PatientSnapshot;
use App\Models\Appointment;
use App\Models\FollowUpRequest;
use App\Services\CurrencyHelper;
use App\Services\FollowUpRequests;
use App\Services\WhatsAppLink;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Widgets\Widget;
use Illuminate\Support\Carbon;

/**
 * Doctor's chairside panel (dashboard, doctors only): the patient in the chair (or the next one)
 * with one-click access to everything needed during the visit, plus today's queue.
 * Status changes are limited to the doctor's own appointments in the current clinic.
 */
class DoctorChairsideWidget extends Widget implements HasActions, HasForms
{
    use InteractsWithActions;
    use InteractsWithForms;

    protected static string $view = 'filament.widgets.doctor-chairside';

    protected static ?int $sort = -5;

    protected int|string|array $columnSpan = 'full';

    protected static bool $isLazy = false;

    /** Relation-manager tab indexes on the patient page (see PatientResource::getRelations()). */
    protected const TAB_TREATMENT_PLANS = 1;

    protected const TAB_PRESCRIPTIONS = 4;

    public static function canView(): bool
    {
        return auth()->user()?->hasRole('doctor') ?? false;
    }

    protected function practiceId(): int
    {
        return (int) (Filament::getTenant()?->id ?? auth()->user()->practice_id);
    }

    protected function todayQuery()
    {
        return Appointment::query()
            ->where('practice_id', $this->practiceId())
            ->where('doctor_id', auth()->id())
            ->whereDate('start_time', Carbon::today())
            ->whereNotIn('status', ['cancelled', 'no_show'])
            ->with(['patient.medicalHistory', 'operatory:id,name'])
            ->orderBy('start_time');
    }

    /** One-click status changes for the doctor's own appointments. */
    public function setStatus(int $appointmentId, string $status): void
    {
        abort_unless(in_array($status, ['arrived', 'in_chair', 'completed'], true), 422);

        $appointment = Appointment::where('practice_id', $this->practiceId())
            ->where('doctor_id', auth()->id())
            ->find($appointmentId);
        abort_unless($appointment, 403, 'You can only update your own appointments.');

        $allowed = [
            'booked' => ['arrived', 'in_chair'],
            'confirmed' => ['arrived', 'in_chair'],
            'arrived' => ['in_chair'],
            'in_chair' => ['completed'],
        ];
        abort_unless(in_array($status, $allowed[$appointment->status] ?? [], true), 422, 'That status change is not allowed.');

        $appointment->update(['status' => $status]);

        Notification::make()
            ->title(match ($status) {
                'in_chair' => 'Patient seated',
                'completed' => 'Visit completed',
                default => 'Patient arrived',
            })
            ->success()
            ->send();
    }

    /**
     * Doctor asks reception to book a follow-up (shows up live on the reception dashboard).
     * Arguments: appointment (the visit it follows).
     */
    public function requestFollowUpAction(): Action
    {
        return Action::make('requestFollowUp')
            ->label(__('Request follow-up'))
            ->modalHeading(__('Request a follow-up from reception'))
            ->modalDescription(__('Reception sees this on their dashboard right away and books the appointment.'))
            ->modalSubmitActionLabel(__('Send to reception'))
            ->modalWidth('lg')
            ->form([
                Forms\Components\ToggleButtons::make('timeframe')
                    ->label(__('When'))
                    ->options(collect(FollowUpRequest::TIMEFRAMES)->map(fn ($t) => __($t[0]))->all())
                    ->default('2_weeks')
                    ->inline()
                    ->live()
                    ->required(),
                Forms\Components\DatePicker::make('preferred_date')
                    ->label(__('Date'))
                    ->native(false)
                    ->minDate(today())
                    ->visible(fn (Forms\Get $get) => $get('timeframe') === 'date')
                    ->required(fn (Forms\Get $get) => $get('timeframe') === 'date'),
                Forms\Components\ToggleButtons::make('duration_minutes')
                    ->label(__('Visit length'))
                    ->options(['15' => '15m', '30' => '30m', '45' => '45m', '60' => '60m', '90' => '90m'])
                    ->default('30')
                    ->inline()
                    ->required(),
                Forms\Components\TextInput::make('procedure_name')
                    ->label(__('For'))
                    ->placeholder(__('e.g. Suture removal, crown fitting'))
                    ->maxLength(255),
                Forms\Components\Textarea::make('notes')
                    ->label(__('Note to reception'))
                    ->rows(2)
                    ->maxLength(1000),
                Forms\Components\Toggle::make('urgent')->label(__('Urgent')),
            ])
            ->action(function (array $data, array $arguments) {
                $appointment = Appointment::where('practice_id', $this->practiceId())
                    ->where('doctor_id', auth()->id())
                    ->find($arguments['appointment'] ?? 0);
                abort_unless($appointment, 403);

                app(FollowUpRequests::class)->request(auth()->user(), $data + [
                    'patient_id' => $appointment->patient_id,
                    'source_appointment_id' => $appointment->id,
                    'procedure_name' => $data['procedure_name'] ?? null,
                ]);

                Notification::make()->title(__('Sent to reception'))->body(__('You will be notified when it is booked.'))->success()->send();
            });
    }

    protected function getViewData(): array
    {
        $today = $this->todayQuery()->get();

        // Current patient: in the chair, else arrived, else the next upcoming visit today.
        $current = $today->firstWhere('status', 'in_chair')
            ?? $today->firstWhere('status', 'arrived')
            ?? $today->first(fn ($a) => ! in_array($a->status, ['completed'], true) && $a->end_time >= now());

        $tools = [];
        $patientInfo = null;
        if ($current && ($patient = $current->patient)) {
            $balance = (float) $patient->invoices()
                ->where('practice_id', $this->practiceId())
                ->whereNotIn('status', ['cancelled', 'void', 'draft'])
                ->sum('balance_due');

            $view = PatientResource::getUrl('view', ['record' => $patient]);
            $chart = PatientResource::getUrl('chart', ['record' => $patient]);

            $tools = array_filter([
                ['label' => __('Dental chart'), 'icon' => 'heroicon-o-squares-2x2', 'url' => $chart, 'primary' => true],
                ['label' => __('Gum chart'), 'icon' => 'heroicon-o-chart-bar', 'url' => $chart.'?view=perio'],
                ['label' => __('Treatment plan'), 'icon' => 'heroicon-o-clipboard-document-list', 'url' => $view.'?activeRelationManager='.self::TAB_TREATMENT_PLANS],
                ['label' => __('Prescription'), 'icon' => 'heroicon-o-beaker', 'url' => $view.'?activeRelationManager='.self::TAB_PRESCRIPTIONS],
                ['label' => __('Patient file'), 'icon' => 'heroicon-o-user', 'url' => $view],
                ['label' => __('New invoice'), 'icon' => 'heroicon-o-document-text', 'url' => InvoiceResource::getUrl('create')],
                (Filament::getTenant()?->hasFeature('whatsapp') ?? false) && ($wa = WhatsAppLink::forPatient($patient, 'post_op_checkup'))
                    ? ['label' => __('WhatsApp'), 'icon' => 'heroicon-o-chat-bubble-left-right', 'url' => $wa, 'external' => true]
                    : null,
            ]);

            $patientInfo = [
                'name' => trim($patient->first_name.' '.$patient->last_name),
                'initials' => mb_strtoupper(mb_substr($patient->first_name ?? '', 0, 1).mb_substr($patient->last_name ?? '', 0, 1)),
                'meta' => collect([$patient->file_number ? '#'.$patient->file_number : null, $patient->dob ? $patient->dob->age.' yrs' : null, $patient->gender ? ucfirst($patient->gender) : null])->filter()->implode(' · '),
                'alerts' => PatientSnapshot::alerts($patient->medicalHistory),
                'balance' => $balance > 0 ? CurrencyHelper::format($balance) : null,
            ];
        }

        $requests = FollowUpRequest::where('practice_id', $this->practiceId())
            ->where('doctor_id', auth()->id())
            ->where(fn ($q) => $q->where('status', FollowUpRequest::STATUS_PENDING)->orWhere('handled_at', '>=', now()->subDay()))
            ->with(['patient:id,first_name,last_name', 'bookedAppointment:id,start_time'])
            ->latest()
            ->limit(6)
            ->get();

        return [
            'requests' => $requests,
            'current' => $current,
            'patient' => $patientInfo,
            'tools' => $tools,
            'queue' => $today,
            'chartUrl' => fn ($a) => PatientResource::getUrl('chart', ['record' => $a->patient_id]),
        ];
    }
}
