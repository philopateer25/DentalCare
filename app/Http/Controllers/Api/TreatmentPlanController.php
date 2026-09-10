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
        if (auth()->check() && auth()->user()->practice_id) {
            abort_unless($patient->practice_id === auth()->user()->practice_id, 403, 'Unauthorized cross-tenant patient access.');
        }
    }

    protected function authorizePlan(TreatmentPlan $plan): void
    {
        if (auth()->check() && auth()->user()->practice_id) {
            abort_unless($plan->patient?->practice_id === auth()->user()->practice_id, 403, 'Unauthorized cross-tenant treatment plan access.');
        }
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
     * Get or create the active (draft/in_progress) treatment plan for a patient
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

        // If none exists, create a default draft plan with one phase
        if (!$plan) {
            DB::transaction(function () use ($patient, &$plan) {
                $plan = $patient->treatmentPlans()->create([
                    'title' => 'Comprehensive Treatment Plan',
                    'status' => 'draft',
                ]);

                $plan->phases()->create([
                    'sequence' => 1,
                    'name' => 'Phase 1: General Treatment',
                ]);
            });

            // Reload with relations
            $plan->load(['phases.procedures.procedureCode']);
        }

        return response()->json($plan);
    }

    /**
     * Add a procedure to the treatment plan
     */
    public function addProcedure(TreatmentPlan $plan, Request $request)
    {
        $this->authorizePlan($plan);
        $validated = $request->validate([
            'tooth_number_fdi' => 'required|integer',
            'surface' => 'nullable|string',
            'procedure_code' => 'required|string', // We expect the string code, e.g. D2140
        ]);

        // Find the procedure code in the database
        $code = ProcedureCode::where('code', $validated['procedure_code'])->first();
        if (!$code) {
            // For now, if the code doesn't exist, we'll quickly stub one. 
            // In a real app, you'd seed these.
            $code = ProcedureCode::firstOrCreate(
                ['code' => $validated['procedure_code']],
                [
                    'title' => 'Custom Procedure ' . $validated['procedure_code'],
                    'standard_fee' => 0.00,
                    'category_id' => 1, // Assuming category 1 exists
                ]
            );
        }

        // Get the first phase, or create one if none exists (fallback)
        $phase = $plan->phases()->first();
        if (!$phase) {
            $phase = $plan->phases()->create(['sequence' => 1, 'name' => 'Phase 1']);
        }

        // Add the procedure to the phase
        $procedure = $phase->procedures()->create([
            'tooth_number_fdi' => $validated['tooth_number_fdi'],
            'surface' => $validated['surface'] ?? 'WHOLE',
            'procedure_code_id' => $code->id,
            'fee' => $code->standard_fee,
            'net_amount' => $code->standard_fee,
            'status' => 'planned',
        ]);

        // Recalculate plan totals
        $total = $plan->phases->flatMap->procedures->sum('fee');
        $plan->update([
            'total_amount' => $total,
            'net_amount' => $total,
        ]);

        return response()->json([
            'message' => 'Procedure added',
            'procedure' => $procedure->load('procedureCode'),
            'plan' => $plan->fresh(['phases.procedures.procedureCode']),
        ]);
    }
}
