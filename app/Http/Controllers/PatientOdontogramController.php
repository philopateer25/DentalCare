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
        $userPracticeId = auth()->user()?->practice_id ?? \Filament\Facades\Filament::getTenant()?->id;

        if ($patient && $patient->exists) {
            if ($userPracticeId && $patient->practice_id !== $userPracticeId) {
                abort(403, 'Unauthorized cross-tenant patient access.');
            }
            return $patient;
        }

        if ($userPracticeId) {
            $patient = Patient::where('practice_id', $userPracticeId)->first();
        }

        if (!$patient) {
            $patient = Patient::firstOrCreate(
                ['practice_id' => $userPracticeId],
                [
                    'file_number' => 'DEMO-001',
                    'first_name' => 'Demo',
                    'last_name' => 'Patient',
                    'gender' => 'male',
                    'phone' => '0000000000',
                    'status' => 'active',
                ]
            );
        }

        return $patient;
    }

    /**
     * Display the 3D Odontogram test page for a patient.
     */
    public function showTest(?Patient $patient = null): Response
    {
        $patient = $this->authorizePatientAccess($patient);

        // Get or create the latest examination
        $examination = $patient->examinations()->latest('examined_at')->first();
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
                'notes' => $finding->notes,
                'surfaces' => (object)$surfaces,
            ];
        }

        return Inertia::render('Dental/Test3DOdontogram', [
            'patient' => $patient,
            'examination' => $examination,
            'initialRecords' => $teethRecords,
            'initialViewMode' => 'clean',
        ]);
    }

    /**
     * Display the Fullscreen Detailed Odontogram.
     */
    public function showDetails(?Patient $patient = null): Response
    {
        $patient = $this->authorizePatientAccess($patient);

        // Get or create the latest examination
        $examination = $patient->examinations()->latest('examined_at')->first();
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
                'notes' => $finding->notes,
                'surfaces' => (object)$surfaces,
            ];
        }

        return Inertia::render('Dental/Test3DOdontogram', [
            'patient' => $patient,
            'examination' => $examination,
            'initialRecords' => $teethRecords,
            'initialViewMode' => 'detailed',
        ]);
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
        ]);

        $tooth = $patient->teeth()->updateOrCreate(
            ['tooth_number' => $validated['tooth_number']],
            [
                'condition' => $validated['condition'],
                'notes' => $validated['notes'] ?? null,
                'surfaces' => $validated['surfaces'] ?? null,
            ]
        );

        return response()->json($tooth);
    }
}
