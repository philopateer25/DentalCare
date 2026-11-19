<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\AppointmentResource;
use App\Filament\Resources\InvoiceResource;
use App\Filament\Resources\PatientResource;
use App\Filament\Support\PatientSnapshot;
use App\Models\Appointment;
use App\Services\CurrencyHelper;
use App\Services\WhatsAppLink;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Widgets\Widget;
use Illuminate\Support\Carbon;

/**
 * Doctor's chairside panel (dashboard, doctors only): the patient in the chair (or the next one)
 * with one-click access to everything needed during the visit, plus today's queue.
 * Status changes are limited to the doctor's own appointments in the current clinic.
 */
class DoctorChairsideWidget extends Widget
{
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
                ['label' => __('Book follow-up'), 'icon' => 'heroicon-o-calendar-days', 'url' => AppointmentResource::getUrl('create')],
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

        return [
            'current' => $current,
            'patient' => $patientInfo,
            'tools' => $tools,
            'queue' => $today,
            'chartUrl' => fn ($a) => PatientResource::getUrl('chart', ['record' => $a->patient_id]),
        ];
    }
}
