<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Patient;
use App\Models\TreatmentPlan;
use App\Models\TreatmentPhase;
use App\Models\TreatmentProcedure;
use App\Models\ProcedureCode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TreatmentPlanController extends Controller
{
    protected function authorizePatient(Patient $patient): void
    {
        abort_unless(auth()->check() && $patient->practice_id === auth()->user()->practice_id, 403, 'Unauthorized cross-tenant patient access.');
    }

    protected function authorizePlan(TreatmentPlan $plan): void
    {
        abort_unless(auth()->check() && $plan->patient?->practice_id === auth()->user()->practice_id, 403, 'Unauthorized cross-tenant treatment plan access.');
    }

    /**
     * Get all treatment plans for a patient
     */
    public function index(Patient $patient)
    {
        $this->authorizePatient($patient);
        $plans = $patient->treatmentPlans()
            ->with(['phases.procedures.procedureCode'])
            ->latest()
            ->get();
            
        return response()->json($plans);
    }

    /**
     * Create a new treatment plan for a patient
     */
    public function store(Patient $patient, Request $request)
    {
        $this->authorizePatient($patient);
        $validated = $request->validate([
            'title' => 'required|string|max:255',
        ]);

        $plan = null;
        DB::transaction(function () use ($patient, $validated, &$plan) {
            $plan = $patient->treatmentPlans()->create([
                'title' => $validated['title'],
                'status' => 'draft',
                'doctor_id' => auth()->id() ?? 1, // Fallback if no auth
            ]);

            $plan->phases()->create([
                'sequence' => 1,
                'name' => 'Phase 1: General Treatment',
            ]);
        });

        return response()->json($plan->load(['phases.procedures.procedureCode']));
    }

    /**
     * Get the active (draft/in_progress) treatment plan for a patient, or null
     */
    public function getActivePlan(Patient $patient)
    {
        $this->authorizePatient($patient);
        // Try to find an existing active plan
        $plan = $patient->treatmentPlans()
            ->whereIn('status', ['draft', 'in_progress'])
            ->with(['phases.procedures.procedureCode'])
            ->latest()
            ->first();

        // Viewing the chart must not create records: return null when there is no active plan.
        // The frontend creates a plan only when the doctor adds the first procedure.
        return response()->json($plan);
    }

    /**
     * Add a procedure to the treatment plan
     */
    public function addProcedure(TreatmentPlan $plan, Request $request)
    {
        $this->authorizePlan($plan);
        $validated = $request->validate([
            'tooth_number_fdi' => ['required', 'integer', function ($attr, $value, $fail) {
                if (! \App\Services\Dental\OdontogramRules::isValidFdi($value)) {
                    $fail('Invalid FDI tooth number.');
                }
            }],
            'surface' => 'nullable|string',
            'procedure_code' => 'required|string', // We expect the string code, e.g. D2140
            'anesthetic_agent' => 'nullable|string',
            'anesthetic_dosage' => 'nullable|string',
            'anesthetic_technique' => 'nullable|string',
            'materials_used_json' => 'nullable|array',
            'tools_used_json' => 'nullable|array',
        ]);

        // Find the procedure code in the database
        abort_if(in_array($plan->status, ['completed', 'cancelled', 'rejected'], true), 422, 'This treatment plan is closed.');

        // Unknown codes used to be created on the fly with a 0 fee; reject them instead.
        $code = ProcedureCode::where('code', $validated['procedure_code'])->first();
        abort_unless($code, 422, 'Unknown procedure code: '.$validated['procedure_code']);

        // Get the first phase, or create one if none exists (fallback)
        $phase = $plan->phases()->first();
        if (!$phase) {
            $phase = $plan->phases()->create(['sequence' => 1, 'name' => 'Phase 1']);
        }

        // Add the procedure to the phase with clinical details
        $procedure = $phase->procedures()->create([
            'tooth_number_fdi' => $validated['tooth_number_fdi'],
            'surface' => $validated['surface'] ?? 'WHOLE',
            'procedure_code_id' => $code->id,
            'fee' => $code->standard_fee,
            'net_amount' => $code->standard_fee,
            'status' => 'planned',
            'doctor_id' => auth()->id(),
            'anesthetic_agent' => $validated['anesthetic_agent'] ?? null,
            'anesthetic_dosage' => $validated['anesthetic_dosage'] ?? null,
            'anesthetic_technique' => $validated['anesthetic_technique'] ?? null,
            'materials_used_json' => $validated['materials_used_json'] ?? null,
            'tools_used_json' => $validated['tools_used_json'] ?? null,
        ]);

        $plan->recalculateTotals();

        return response()->json([
            'message' => 'Procedure added',
            'procedure' => $procedure->load('procedureCode'),
            'plan' => $plan->fresh(['phases.procedures.procedureCode']),
        ]);
    }
}
