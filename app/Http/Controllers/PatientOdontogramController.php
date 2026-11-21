<?php

namespace App\Http\Controllers;

use App\Models\Patient;
use App\Models\PatientTooth;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PatientOdontogramController extends Controller
{
    /**
     * Display the 3D Odontogram test page for a patient.
     */
    protected function authorizePatientAccess(?Patient $patient): Patient
    {
        // Routes are behind the `auth` middleware. This used to auto-login a fallback admin
        // for anonymous visitors and create a "Demo Patient"; both were removed.
        $userPracticeId = auth()->user()->practice_id ?? \Filament\Facades\Filament::getTenant()?->id;

        if ($patient && $patient->exists) {
            abort_unless($patient->practice_id === $userPracticeId, 403, 'Unauthorized cross-tenant patient access.');

            return $patient;
        }

        // No patient in the URL: open the practice's first patient, if any.
        return Patient::where('practice_id', $userPracticeId)->firstOrFail();
    }

    /**
     * Display the 3D Odontogram test page for a patient.
     */
    public function showTest(?Patient $patient = null): Response
    {
        $patient = $this->authorizePatientAccess($patient);

        // Get or create the latest examination
        $examination = $patient->examinations()->latest('id')->first();
        if (!$examination) {
            $examination = $patient->examinations()->create([
                'doctor_id' => auth()->id(),
                'type' => 'initial',
            ]);
        }

        $examination->load('toothFindings.surfaceFindings');
        
        $teethRecords = [];
        foreach ($examination->toothFindings as $finding) {
            $surfaces = [];
            foreach ($finding->surfaceFindings as $surfaceFinding) {
                $surfaces[$surfaceFinding->surface] = $surfaceFinding->finding_type;
            }

            $teethRecords[$finding->tooth_number_fdi] = [
                'condition' => $finding->finding_type,
                'status' => $finding->status ?? 'existing',
                'clinical_details' => $finding->clinical_details,
                'notes' => $finding->notes,
                'surfaces' => (object)$surfaces,
            ];
        }

        // Merge with patient->teeth to capture any patient-level charted findings
        $patient->load('teeth');
        foreach ($patient->teeth as $pt) {
            if (!isset($teethRecords[$pt->tooth_number])) {
                $teethRecords[$pt->tooth_number] = [
                    'condition' => $pt->condition,
                    'status' => $pt->status ?? 'existing',
                    'clinical_details' => $pt->clinical_details,
                    'notes' => $pt->notes,
                    'surfaces' => (object)($pt->surfaces ?? []),
                ];
            } else {
                $teethRecords[$pt->tooth_number]['status'] = $pt->status ?? $teethRecords[$pt->tooth_number]['status'] ?? 'existing';
                $teethRecords[$pt->tooth_number]['clinical_details'] = $pt->clinical_details ?? $teethRecords[$pt->tooth_number]['clinical_details'] ?? null;
            }
        }

        // Preload clinical procedures across treatment plans
        $patient->load([
            'treatmentPlans.phases.procedures.procedureCode',
            'treatmentPlans.phases.procedures.doctor',
        ]);
        $proceduresByTooth = [];
        foreach ($patient->treatmentPlans as $plan) {
            foreach ($plan->phases as $phase) {
                foreach ($phase->procedures as $procedure) {
                    if ($procedure->tooth_number_fdi) {
                        $proceduresByTooth[(string)$procedure->tooth_number_fdi][] = $procedure;
                    }
                }
            }
        }

        $procedureCategories = \App\Models\ProcedureCategory::with(['procedureCodes' => fn($q) => $q->orderBy('code')])->get();

        return Inertia::render('Dental/Test3DOdontogram', [
            'patient' => $patient,
            'examination' => $examination,
            'initialRecords' => $teethRecords,
            'initialProcedures' => $proceduresByTooth,
            'procedureCategories' => $procedureCategories,
            'initialViewMode' => 'clean',
        ]);
    }

    /**
     * Display the Fullscreen Detailed Odontogram.
     */
    public function showDetails(?Patient $patient = null): \Symfony\Component\HttpFoundation\Response
    {
        $patient = $this->authorizePatientAccess($patient);

        // Retired: the standalone details page is replaced by the Chart workspace in Filament.
        $url = \App\Filament\Resources\PatientResource::getUrl('chart', ['record' => $patient], panel: 'admin', tenant: $patient->practice);
        if ($tooth = request('tooth')) {
            $url .= '?tooth='.urlencode((string) $tooth);
        }

        return redirect()->to($url);
    }

    /**
     * Get key-value JSON dictionary of tooth conditions for a patient.
     */
    public function getTeeth(Patient $patient)
    {
        $patient = $this->authorizePatientAccess($patient);

        $teethRecords = $patient->teeth()
            ->get(['tooth_number', 'condition', 'notes', 'surfaces'])
            ->keyBy('tooth_number')
            ->map(function ($tooth) {
                return [
                    'condition' => $tooth->condition,
                    'notes' => $tooth->notes,
                    'surfaces' => $tooth->surfaces,
                ];
            })
            ->toArray();

        return response()->json($teethRecords);
    }

    /**
     * Update or create a tooth condition for a patient.
     */
    public function updateTooth(Request $request, Patient $patient)
    {
        $patient = $this->authorizePatientAccess($patient);

        $validated = $request->validate([
            'tooth_number' => 'required|string',
            'condition' => 'required|string|in:healthy,active_caries,composite_filled,crown,root_canal,missing,implant,custom',
            'notes' => 'nullable|string',
            'surfaces' => 'nullable|array',
            'status' => 'nullable|string|in:existing,needs_treatment,watch',
            'clinical_details' => 'nullable|array',
        ]);

        $status = $validated['status'] ?? 'existing';
        $clinicalDetails = $validated['clinical_details'] ?? null;

        $tooth = $patient->teeth()->updateOrCreate(
            ['tooth_number' => $validated['tooth_number']],
            [
                'condition' => $validated['condition'],
                'status' => $status,
                'clinical_details' => $clinicalDetails,
                'notes' => $validated['notes'] ?? null,
                'surfaces' => $validated['surfaces'] ?? null,
            ]
        );

        $latestExam = $patient->examinations()->latest('id')->first();
        if (!$latestExam) {
            $latestExam = $patient->examinations()->create([
                'doctor_id' => auth()->id() ?? 1,
                'type' => 'periodic',
                'examined_at' => now(),
            ]);
        } elseif (!$latestExam->examined_at) {
            $latestExam->update(['examined_at' => now()]);
        }

        $toothFinding = $latestExam->toothFindings()->updateOrCreate(
            ['tooth_number_fdi' => $validated['tooth_number']],
            [
                'finding_type' => $validated['condition'],
                'status' => $status,
                'clinical_details' => $clinicalDetails,
                'notes' => $validated['notes'] ?? null,
            ]
        );
        if (isset($validated['surfaces']) && is_array($validated['surfaces'])) {
            foreach ($validated['surfaces'] as $surface => $cond) {
                $toothFinding->surfaceFindings()->updateOrCreate(
                    ['surface' => $surface],
                    ['finding_type' => $cond]
                );
            }
        }

        $patient->touch();

        \Illuminate\Support\Facades\Cache::forget("p360_raw_{$patient->id}");
        \Illuminate\Support\Facades\Cache::forget("p360_teeth_{$patient->id}");
        \Illuminate\Support\Facades\Cache::forget("p360_roadmap_{$patient->id}");
        \Illuminate\Support\Facades\Cache::forget("p360_plans_{$patient->id}");

        return response()->json($tooth);
    }
}
