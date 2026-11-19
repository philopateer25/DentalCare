<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\PatientResource;
use App\Models\Appointment;
use App\Models\FollowUpRequest;
use App\Models\Operatory;
use App\Models\User;
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
use Illuminate\Validation\ValidationException;

/**
 * Reception dashboard: follow-up requests sent by doctors from the chair. Polls every 5 s so
 * new requests appear without a refresh; reception books (or declines) each one.
 */
class FollowUpRequestsWidget extends Widget implements HasActions, HasForms
{
    use InteractsWithActions;
    use InteractsWithForms;

    protected static string $view = 'filament.widgets.follow-up-requests';

    protected static ?int $sort = -6;

    protected int|string|array $columnSpan = 'full';

    protected static bool $isLazy = false;

    public static function canView(): bool
    {
        return auth()->user()?->hasAnyRole(FollowUpRequests::RECEPTION_ROLES) ?? false;
    }

    protected function practiceId(): int
    {
        return (int) (Filament::getTenant()?->id ?? auth()->user()->practice_id);
    }

    protected function findRequest(mixed $id): FollowUpRequest
    {
        $request = FollowUpRequest::where('practice_id', $this->practiceId())->find($id);
        abort_unless($request, 404);

        return $request;
    }

    public function bookAction(): Action
    {
        return Action::make('book')
            ->label(__('Book'))
            ->modalHeading(fn (array $arguments) => __('Book follow-up').' – '.$this->patientName($arguments['request'] ?? null))
            ->modalSubmitActionLabel(__('Book appointment'))
            ->modalWidth('xl')
            ->fillForm(function (array $arguments) {
                $r = $this->findRequest($arguments['request'] ?? 0);

                return [
                    'start_time' => FollowUpRequests::suggestedStart($r)->format('Y-m-d H:i:s'),
                    'duration_minutes' => (string) $r->duration_minutes,
                    'doctor_id' => $r->doctor_id,
                    'operatory_id' => $r->bookedAppointment?->operatory_id
                        ?? Appointment::find($r->source_appointment_id)?->operatory_id,
                    'procedure_name' => $r->procedure_name,
                    'notes' => $r->notes,
                ];
            })
            ->form([
                Forms\Components\DateTimePicker::make('start_time')->label(__('Date & time'))->native(false)->seconds(false)->minutesStep(15)->required(),
                Forms\Components\ToggleButtons::make('duration_minutes')->label(__('Length'))
                    ->options(['15' => '15m', '30' => '30m', '45' => '45m', '60' => '60m', '90' => '90m'])->inline()->required(),
                Forms\Components\Select::make('doctor_id')->label(__('Doctor'))
                    ->options(fn () => User::where('practice_id', $this->practiceId())->role('doctor')->orderBy('name')->pluck('name', 'id'))
                    ->searchable()->required(),
                Forms\Components\Select::make('operatory_id')->label(__('Room'))
                    ->options(fn () => Operatory::whereHas('branch', fn ($q) => $q->where('practice_id', $this->practiceId()))->orderBy('name')->pluck('name', 'id'))
                    ->required(),
                Forms\Components\TextInput::make('procedure_name')->label(__('For'))->maxLength(255),
                Forms\Components\Textarea::make('notes')->label(__('Notes'))->rows(2),
            ])
            ->action(function (array $data, array $arguments, Action $action) {
                $request = $this->findRequest($arguments['request'] ?? 0);
                try {
                    $result = app(FollowUpRequests::class)->book($request, auth()->user(), $data);
                } catch (ValidationException $e) {
                    Notification::make()->title(__('Cannot book this slot'))->body(collect($e->errors())->flatten()->implode(' '))->danger()->send();
                    $action->halt();

                    return;
                }

                Notification::make()->title(__('Follow-up booked'))->body(__('The doctor has been notified.'))->success()->send();
                if ($result['warning']) {
                    Notification::make()->title(__('Something will be missing in this room'))->body($result['warning'])->warning()->persistent()->send();
                }
            });
    }

    public function dismissAction(): Action
    {
        return Action::make('dismiss')
            ->label(__("Can't book"))
            ->color('gray')
            ->modalHeading(__('Close this request without booking?'))
            ->modalSubmitActionLabel(__('Close request'))
            ->form([
                Forms\Components\Textarea::make('reason')->label(__('Reason (sent to the doctor)'))
                    ->placeholder(__('e.g. Patient will call back, no answer'))->rows(2)->maxLength(500),
            ])
            ->action(function (array $data, array $arguments) {
                app(FollowUpRequests::class)->dismiss($this->findRequest($arguments['request'] ?? 0), auth()->user(), $data['reason'] ?? null);
                Notification::make()->title(__('Request closed'))->success()->send();
            });
    }

    protected function patientName(mixed $id): string
    {
        $r = FollowUpRequest::where('practice_id', $this->practiceId())->with('patient:id,first_name,last_name')->find($id);

        return $r ? trim($r->patient->first_name.' '.$r->patient->last_name) : '';
    }

    protected function getViewData(): array
    {
        $pending = FollowUpRequest::where('practice_id', $this->practiceId())
            ->pending()
            ->with(['patient:id,first_name,last_name,phone,whatsapp_number,file_number,practice_id', 'doctor:id,name'])
            ->orderByDesc('urgent')
            ->oldest()
            ->get();

        $recent = FollowUpRequest::where('practice_id', $this->practiceId())
            ->where('status', '!=', FollowUpRequest::STATUS_PENDING)
            ->where('handled_at', '>=', now()->subHours(12))
            ->with(['patient:id,first_name,last_name', 'doctor:id,name', 'bookedAppointment:id,start_time', 'handler:id,name'])
            ->latest('handled_at')
            ->limit(5)
            ->get();

        $hasWhatsApp = Filament::getTenant()?->hasFeature('whatsapp') ?? false;

        return [
            'pending' => $pending,
            'recent' => $recent,
            'patientUrl' => fn ($r) => PatientResource::getUrl('view', ['record' => $r->patient_id]),
            'whatsapp' => fn ($r) => $hasWhatsApp && $r->patient ? WhatsAppLink::forPatient($r->patient, 'greeting') : null,
        ];
    }
}
