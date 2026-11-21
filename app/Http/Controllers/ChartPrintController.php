<?php

namespace App\Http\Controllers;

use App\Filament\Support\PatientSnapshot;
use App\Http\Controllers\Api\ChartWorkspaceController;
use App\Models\Patient;
use App\Services\CurrencyHelper;

/**
 * Printable dental chart summary ("Export PDF" in the Chart workspace). The browser's
 * print dialog saves it as a PDF, which staff can then send on WhatsApp.
 */
class ChartPrintController extends Controller
{
    public function show(Patient $patient)
    {
        abort_unless($patient->practice_id === auth()->user()->practice_id, 403, 'Unauthorized cross-tenant patient access.');

        $patient->load(['medicalHistory', 'practice']);
        $exam = $patient->examinations()->orderByDesc('id')->first();
        $records = $exam ? ChartWorkspaceController::records($exam, $patient) : [];
        ksort($records, SORT_NATURAL);

        $plan = $patient->treatmentPlans()
            ->whereNotIn('status', ['cancelled', 'rejected'])
            ->with(['phases' => fn ($q) => $q->orderBy('sequence'), 'phases.procedures.procedureCode'])
            ->latest()
            ->first();

        return view('chart.print', [
            'patient' => $patient,
            'practice' => $patient->practice,
            'exam' => $exam,
            'records' => collect($records)->reject(fn ($r) => $r['condition'] === 'healthy' && $r['status'] === 'existing' && ! $r['notes'] && empty((array) $r['surfaces']) && empty((array) $r['clinical_details'])),
            'alerts' => PatientSnapshot::alerts($patient->medicalHistory),
            'plan' => $plan,
            'money' => fn ($v) => CurrencyHelper::format((float) $v),
        ]);
    }
}
