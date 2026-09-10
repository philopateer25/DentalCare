<?php

namespace App\Http\Controllers;

use App\Models\DentalExamination;
use App\Models\Patient;
use App\Models\ToothFinding;
use Illuminate\Http\Request;

class DentalExaminationController extends Controller
{
    protected function authorizePatient(Patient $patient): void
    {
        if (auth()->check() && auth()->user()->practice_id) {
            abort_unless($patient->practice_id === auth()->user()->practice_id, 403, 'Unauthorized cross-tenant patient access.');
        }
    }

    protected function authorizeExamination(DentalExamination $examination): void
    {
        if (auth()->check() && auth()->user()->practice_id) {
            abort_unless($examination->patient?->practice_id === auth()->user()->practice_id, 403, 'Unauthorized cross-tenant examination access.');
        }
    }

    /**
     * Get all examinations for a patient.
     */
    public function index(Patient $patient)
    {
        $this->authorizePatient($patient);
        $examinations = $patient->examinations()->orderBy('examined_at', 'desc')->get();
        return response()->json($examinations);
    }

    /**
     * Start a new examination for a patient.
     */
    public function store(Request $request, Patient $patient)
    {
        $this->authorizePatient($patient);
        $validated = $request->validate([
            'type' => 'required|string|in:initial,periodic,emergency',
            'notes' => 'nullable|string',
        ]);

        $examination = $patient->examinations()->create([
            'doctor_id' => auth()->id(), // Allow null if not authenticated
            'type' => $validated['type'],
            'notes' => $validated['notes'] ?? null,
        ]);

        return response()->json($examination, 201);
    }

    /**
     * Get the specific clinical state for that exam, formatted for the 3D Viewer.
     */
    public function show(DentalExamination $examination)
    {
        $this->authorizeExamination($examination);
        // Load findings with their surface findings
        $examination->load('toothFindings.surfaceFindings');

        // Transform to the format expected by the frontend (dictionary keyed by tooth number)
        $teethRecords = [];

        foreach ($examination->toothFindings as $finding) {
            $surfaces = [];
            foreach ($finding->surfaceFindings as $surfaceFinding) {
                $surfaces[$surfaceFinding->surface] = $surfaceFinding->finding_type;
            }

            $teethRecords[$finding->tooth_number_fdi] = [
                'condition' => $finding->finding_type,
                'notes' => $finding->notes,
                'surfaces' => (object)$surfaces, // Force object for empty arrays in JSON
            ];
        }

        return response()->json($teethRecords);
    }

    /**
     * Record a tooth/surface finding (optimistic save).
     */
    public function updateFinding(Request $request, DentalExamination $examination)
    {
        $this->authorizeExamination($examination);
        $validated = $request->validate([
            'tooth_number' => 'required|string',
            'condition' => 'required|string', // mapping to finding_type
            'notes' => 'nullable|string',
            'surfaces' => 'nullable|array',
        ]);

        // Update or create the main tooth finding
        $toothFinding = $examination->toothFindings()->updateOrCreate(
            ['tooth_number_fdi' => $validated['tooth_number']],
            [
                'finding_type' => $validated['condition'],
                'notes' => $validated['notes'] ?? null,
            ]
        );

        // Update surface findings if provided
        if (isset($validated['surfaces']) && is_array($validated['surfaces'])) {
            foreach ($validated['surfaces'] as $surface => $condition) {
                $toothFinding->surfaceFindings()->updateOrCreate(
                    ['surface' => $surface],
                    ['finding_type' => $condition]
                );
            }
        }

        return response()->json($toothFinding->load('surfaceFindings'));
    }
}
