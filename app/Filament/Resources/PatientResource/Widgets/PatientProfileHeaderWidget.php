<?php

namespace App\Filament\Resources\PatientResource\Widgets;

use App\Filament\Resources\AppointmentResource;
use App\Filament\Resources\InvoiceResource;
use App\Filament\Resources\PatientResource;
use App\Filament\Resources\PatientResource\Pages\ChartWorkspace;
use App\Filament\Support\PatientSnapshot;
use App\Models\Appointment;
use App\Models\Invoice;
use App\Models\Patient;
use App\Services\CurrencyHelper;
use App\Services\WhatsAppLink;
use Filament\Facades\Filament;
use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Model;

/**
 * Patient profile header + overview (design/stitch/04-patient-profile):
 * identity card, balance, quick actions, medical alerts, upcoming visit, recent invoices.
 * Read-only; every query is scoped to the current practice.
 */
class PatientProfileHeaderWidget extends Widget
{
    protected static string $view = 'filament.resources.patient-resource.widgets.patient-profile-header';

    public ?Model $record = null;

    protected int|string|array $columnSpan = 'full';

    protected static bool $isLazy = false;

    protected function getViewData(): array
    {
        /** @var Patient $patient */
        $patient = $this->record;
        $practiceId = (int) (Filament::getTenant()?->id ?? auth()->user()?->practice_id);

        abort_unless((int) $patient->practice_id === $practiceId, 403, 'Unauthorized cross-tenant access.');

        $patient->loadMissing('medicalHistory');

        $appointments = Appointment::query()
            ->where('practice_id', $practiceId)
            ->where('patient_id', $patient->id)
            ->with(['doctor:id,name', 'operatory:id,name']);

        $upcoming = (clone $appointments)
            ->where('start_time', '>=', now()->startOfDay())
            ->whereNotIn('status', ['cancelled', 'no_show', 'completed'])
            ->orderBy('start_time')
            ->first();

        $previous = (clone $appointments)
            ->where('status', 'completed')
            ->latest('start_time')
            ->first();

        $invoices = Invoice::query()
            ->where('practice_id', $practiceId)
            ->where('patient_id', $patient->id)
            ->latest('issue_date')
            ->latest('id')
            ->limit(4)
            ->get(['id', 'invoice_number', 'issue_date', 'total_amount', 'balance_due', 'status']);

        $openInvoices = Invoice::query()
            ->where('practice_id', $practiceId)
            ->where('patient_id', $patient->id)
            ->whereNotIn('status', ['cancelled', 'void', 'draft']);

        $balance = (float) (clone $openInvoices)->sum('balance_due');
        $lifetime = (float) (clone $openInvoices)->sum('total_amount');

        $whatsapp = [];
        if ((Filament::getTenant()?->hasFeature('whatsapp') ?? false) && WhatsAppLink::phone($patient->whatsapp_number ?: $patient->phone)) {
            $whatsapp['General message'] = WhatsAppLink::forPatient($patient, 'greeting');
            if ($upcoming) {
                $whatsapp['Appointment reminder'] = WhatsAppLink::forAppointment($upcoming, 'appointment_reminder');
            }
            if ($balance > 0) {
                $whatsapp['Balance reminder'] = WhatsAppLink::forPatient($patient, 'balance_reminder', ['balance' => CurrencyHelper::format($balance)]);
            }
            if ($previous) {
                $whatsapp['Post-op check-up'] = WhatsAppLink::forPatient($patient, 'post_op_checkup');
            }
        }

        return [
            'patient' => $patient,
            'history' => $patient->medicalHistory,
            'alerts' => PatientSnapshot::alerts($patient->medicalHistory),
            'upcoming' => $upcoming,
            'previous' => $previous,
            'invoices' => $invoices,
            'balance' => $balance,
            'money' => fn ($v) => CurrencyHelper::format((float) $v),
            'lifetime' => $lifetime,
            'bookUrl' => AppointmentResource::getUrl('create'),
            'invoiceUrl' => InvoiceResource::canAccess() ? InvoiceResource::getUrl('create') : null,
            // Opens the "Scan paper card" dialog on the patient page (Phase 4).
            'scanUrl' => PatientResource::getUrl('view', ['record' => $patient]).'?scan=1',
            'invoicesUrl' => InvoiceResource::getUrl('index'),
            'editUrl' => PatientResource::getUrl('edit', ['record' => $patient]),
            'chartUrl' => ChartWorkspace::canUseChart()
                ? PatientResource::getUrl('chart', ['record' => $patient]) : null,
            'appointmentUrl' => $upcoming ? AppointmentResource::getUrl('edit', ['record' => $upcoming]) : null,
            'whatsapp' => array_filter($whatsapp),
        ];
    }
}
