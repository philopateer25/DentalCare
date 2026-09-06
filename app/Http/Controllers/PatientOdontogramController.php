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

        return Inertia::render('Dental/Test3DOdontogram', [
            'patient' => $patient,
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

        return Inertia::render('Dental/Test3DOdontogram', [
            'patient' => $patient,
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
