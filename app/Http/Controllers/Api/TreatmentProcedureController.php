<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PatientTooth;
use App\Models\TreatmentProcedure;
use App\Services\Dental\OdontogramRules;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class TreatmentProcedureController extends Controller
{
    /**
     * Mark a planned procedure as completed and sync the resulting tooth condition to the chart.
     * (Moved from a route closure; the condition is now decided by CDT code range.)
     */
    public function complete(TreatmentProcedure $procedure, Request $request)
    {
        $patient = $procedure->phase?->plan?->patient;
        abort_unless($patient && auth()->user()->practice_id === $patient->practice_id, 403, 'Unauthorized cross-tenant access.');

        $updateData = ['status' => 'completed', 'completed_at' => now()];
        foreach (['anesthetic_agent', 'anesthetic_dosage', 'anesthetic_technique', 'chairside_notes'] as $field) {
            if ($request->filled($field)) {
                $updateData[$field] = $request->input($field);
            }
        }
        foreach (['tools_used_json', 'materials_used_json'] as $field) {
            if ($request->has($field)) {
                $updateData[$field] = $request->input($field);
            }
        }
        if ($request->has('hemostasis_achieved')) {
            $updateData['hemostasis_achieved'] = $request->boolean('hemostasis_achieved');
        }

        DB::transaction(function () use ($procedure, $updateData, $patient) {
            $procedure->update($updateData);
            $procedure->phase?->plan?->recalculateTotals();

            $tooth = (string) $procedure->tooth_number_fdi;
            if (! $procedure->tooth_number_fdi) {
                return;
            }

            $title = $procedure->procedureCode?->title ?: 'Clinical treatment';
            $condition = OdontogramRules::conditionAfterProcedure($procedure->procedureCode?->code);
            $line = 'Completed '.now()->format('Y-m-d').': '.$title;

            // Patient-level chart: update condition when the procedure changes it, clear "needs treatment",
            // and append to notes instead of overwriting them.
            $pt = PatientTooth::firstOrNew(['patient_id' => $patient->id, 'tooth_number' => $tooth]);
            if ($condition) {
                $pt->condition = $condition;
            }
            $pt->condition ??= 'healthy';
            $pt->status = 'existing';
            $pt->notes = trim(($pt->notes ? $pt->notes."\n" : '').$line);
            $pt->save();

            $exam = $patient->examinations()->latest('examined_at')->latest('id')->first()
                ?? $patient->examinations()->create([
                    'doctor_id' => auth()->id(),
                    'type' => 'treatment',
                    'examined_at' => now(),
                ]);
            if (! $exam->examined_at) {
                $exam->update(['examined_at' => now()]);
            }

            $finding = $exam->toothFindings()->firstOrNew(['tooth_number_fdi' => $tooth]);
            if ($condition) {
                $finding->finding_type = $condition;
            }
            $finding->finding_type ??= 'healthy';
            $finding->status = 'existing';
            $finding->notes = trim(($finding->notes ? $finding->notes."\n" : '').$line);
            $finding->save();

            $patient->touch();
            foreach (['raw', 'teeth', 'roadmap', 'plans'] as $key) {
                Cache::forget("p360_{$key}_{$patient->id}");
            }
        });

        return response()->json([
            'message' => 'Completed',
            'procedure' => $procedure->fresh(),
            'condition' => OdontogramRules::conditionAfterProcedure($procedure->procedureCode?->code),
        ]);
    }
}
