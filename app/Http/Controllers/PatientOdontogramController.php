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
    public function showTest(?Patient $patient = null): Response
    {
        if (!$patient || !$patient->exists) {
            $patient = Patient::first();
            if (!$patient) {
                $patient = Patient::create([
                    'file_number' => 'DEMO-001',
                    'full_name' => 'Demo Patient',
                    'first_name' => 'Demo',
                    'last_name' => 'Patient',
                    'gender' => 'male',
                    'phone' => '0000000000',
                    'status' => 'active',
                ]);
            }
        }

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
        if (!$patient || !$patient->exists) {
            $patient = Patient::first();
        }

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
