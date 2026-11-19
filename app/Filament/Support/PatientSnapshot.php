<?php

namespace App\Filament\Support;

use App\Models\Appointment;
use App\Models\Patient;
use App\Services\CurrencyHelper;
use Filament\Facades\Filament;
use Illuminate\Support\HtmlString;

/**
 * Read-only clinical snapshot of a patient for booking forms (design/stitch/03-appointment-form):
 * identity, last visit, outstanding balance, medical alerts. Tenant-scoped.
 */
class PatientSnapshot
{
    /** Medical-history booleans that should be surfaced as alerts at booking time. */
    protected const ALERTS = [
        'penicillin_allergy' => ['Penicillin allergy', 'danger'],
        'latex_allergy' => ['Latex allergy', 'danger'],
        'local_anesthetic_allergy' => ['Local anesthetic allergy', 'danger'],
        'bleeding_disorder' => ['Bleeding disorder', 'danger'],
        'requires_antibiotic_prophylaxis' => ['Needs antibiotic prophylaxis', 'danger'],
        'cardiac_history' => ['Cardiac history', 'warning'],
        'hypertension_status' => ['Hypertension', 'warning'],
        'diabetic_status' => ['Diabetic', 'warning'],
        'bisphosphonate_therapy' => ['Bisphosphonate therapy', 'warning'],
    ];

    /** @return \Illuminate\Support\Collection<int, array{label: string, tone: string}> */
    public static function alerts(?\App\Models\MedicalHistory $history): \Illuminate\Support\Collection
    {
        $alerts = collect(self::ALERTS)
            ->filter(fn ($meta, $field) => $history && $history->{$field})
            ->map(fn ($meta) => ['label' => $meta[0], 'tone' => $meta[1]])
            ->values();

        if ($history?->pregnancy_trimester) {
            $alerts->push(['label' => 'Pregnant ('.$history->pregnancy_trimester.')', 'tone' => 'warning']);
        }

        return $alerts;
    }

    public static function render(mixed $patientId): ?HtmlString
    {
        if (! $patientId) {
            return null;
        }

        $practiceId = Filament::getTenant()?->id ?? auth()->user()?->practice_id;

        $patient = Patient::query()
            ->where('practice_id', $practiceId)
            ->with('medicalHistory')
            ->withSum(['invoices as balance_total' => fn ($q) => $q->where('invoices.practice_id', $practiceId)->whereNotIn('status', ['cancelled', 'void', 'draft'])], 'balance_due')
            ->find($patientId);

        if (! $patient) {
            return null;
        }

        $lastVisit = Appointment::query()
            ->where('practice_id', $practiceId)
            ->where('patient_id', $patient->id)
            ->where('status', 'completed')
            ->latest('start_time')
            ->first(['start_time', 'procedure_name']);

        $history = $patient->medicalHistory;
        $alerts = self::alerts($history);

        return new HtmlString(view('filament.components.patient-snapshot', [
            'patient' => $patient,
            'lastVisit' => $lastVisit,
            'balance' => (float) $patient->balance_total,
            'balanceText' => CurrencyHelper::format((float) $patient->balance_total),
            'alerts' => $alerts,
            'bloodType' => $history?->blood_type,
        ])->render());
    }
}
