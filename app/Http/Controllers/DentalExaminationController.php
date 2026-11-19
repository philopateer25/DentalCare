<?php

namespace App\Http\Controllers;

use App\Models\DentalExamination;
use App\Models\Patient;
use App\Models\ToothFinding;
use App\Services\Dental\OdontogramRules;
use Illuminate\Http\Request;

class DentalExaminationController extends Controller
{
    protected function authorizePatient(Patient $patient): void
    {
        abort_unless(auth()->check() && $patient->practice_id === auth()->user()->practice_id, 403, 'Unauthorized cross-tenant patient access.');
    }

    protected function authorizeExamination(DentalExamination $examination): void
    {
        abort_unless(auth()->check() && $examination->patient?->practice_id === auth()->user()->practice_id, 403, 'Unauthorized cross-tenant examination access.');
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

        $previous = $patient->examinations()->with('toothFindings.surfaceFindings')
            ->latest('id')->first();

        $examination = $patient->examinations()->create([
            'doctor_id' => auth()->id(),
            'type' => $validated['type'],
            'notes' => $validated['notes'] ?? null,
        ]);

        // A new exam starts from the current mouth (missing teeth, crowns, fillings carry
        // forward) rather than an empty chart; the doctor then updates what changed.
        if ($previous) {
            foreach ($previous->toothFindings as $finding) {
                $copy = $examination->toothFindings()->create([
                    'tooth_number_fdi' => $finding->tooth_number_fdi,
                    'finding_type' => $finding->finding_type,
                    'status' => $finding->status,
                    'clinical_details' => $finding->clinical_details,
                    'notes' => $finding->notes,
                ]);
                foreach ($finding->surfaceFindings as $surface) {
                    $copy->surfaceFindings()->create([
                        'surface' => $surface->surface,
                        'finding_type' => $surface->finding_type,
                    ]);
                }
            }
        }

        return response()->json($examination, 201);
    }

    /**
     * Get the specific clinical state for that exam, formatted for the 3D Viewer.
     */
    public function show(DentalExamination $examination)
    {
        $this->authorizeExamination($examination);

        // The Chart workspace asks for the normalised shape (structured diagnosis).
        if (request()->boolean('normalised')) {
            // The newest exam also shows teeth charted only at patient level (older data),
            // matching what the workspace shows on first load.
            $patient = $examination->patient;
            $isCurrent = $patient && $patient->examinations()->max('id') === $examination->id;

            return response()->json((object) \App\Http\Controllers\Api\ChartWorkspaceController::records($examination, $isCurrent ? $patient : null));
        }

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
                'status' => $finding->status ?? 'existing',
                'clinical_details' => $finding->clinical_details,
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
            'tooth_number' => ['required', 'string', function ($attr, $value, $fail) {
                if (! OdontogramRules::isValidFdi($value)) {
                    $fail('Invalid FDI tooth number.');
                }
            }],
            'condition' => 'required|string|in:'.implode(',', OdontogramRules::CONDITIONS), // mapping to finding_type
            'notes' => 'nullable|string',
            'surfaces' => 'nullable|array',
            'status' => 'nullable|string|in:existing,needs_treatment,watch',
            'clinical_details' => 'nullable|array',
        ]);

        $status = $validated['status'] ?? 'existing';
        $clinicalDetails = $validated['clinical_details'] ?? null;

        // Update or create the main tooth finding
        $toothFinding = $examination->toothFindings()->updateOrCreate(
            ['tooth_number_fdi' => $validated['tooth_number']],
            [
                'finding_type' => $validated['condition'],
                'status' => $status,
                'clinical_details' => $clinicalDetails,
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

        if (!$examination->examined_at) {
            $examination->update(['examined_at' => now()]);
        }

        // Dual-sync to patient_teeth so both models and 360 profile are updated
        if ($examination->patient) {
            $patient = $examination->patient;
            $patient->teeth()->updateOrCreate(
                ['tooth_number' => (string) $validated['tooth_number']],
                [
                    'condition' => $validated['condition'],
                    'status' => $status,
                    'clinical_details' => $clinicalDetails,
                    'notes' => $validated['notes'] ?? null,
                    'surfaces' => $validated['surfaces'] ?? null,
                ]
            );
            $patient->touch();

            // Charting never creates treatment by itself (it used to auto-create a plan and
            // procedure here). A "needs treatment" finding returns a suggestion instead; the
            // doctor adds it to a plan explicitly. See App\Services\Dental\OdontogramRules.

            \Illuminate\Support\Facades\Cache::forget("p360_raw_{$patient->id}");
            \Illuminate\Support\Facades\Cache::forget("p360_teeth_{$patient->id}");
            \Illuminate\Support\Facades\Cache::forget("p360_roadmap_{$patient->id}");
            \Illuminate\Support\Facades\Cache::forget("p360_plans_{$patient->id}");
        }

        return response()->json(array_merge($toothFinding->load('surfaceFindings')->toArray(), [
            'suggestion' => OdontogramRules::suggestionFor($validated['condition'], $status, $validated['tooth_number']),
        ]));
    }
}
