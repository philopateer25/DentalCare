<?php

namespace App\Http\Controllers\Api;

use App\Filament\Support\PatientSnapshot;
use App\Http\Controllers\Controller;
use App\Models\DentalExamination;
use App\Models\Patient;
use App\Models\ProcedureCode;
use App\Services\Dental\OdontogramRules;

/**
 * Data for the Chart workspace (Filament: Patients → Chart). One request returns everything
 * the workspace needs; writes still go through the existing finding / plan / procedure APIs.
 */
class ChartWorkspaceController extends Controller
{
    public function show(Patient $patient)
    {
        abort_unless($patient->practice_id === auth()->user()->practice_id, 403, 'Unauthorized cross-tenant patient access.');

        $patient->load('medicalHistory');

        $examinations = $patient->examinations()->orderByDesc('id')->get(['id', 'type', 'examined_at', 'created_at', 'notes']);
        $current = $examinations->first();

        $plans = $patient->treatmentPlans()
            ->with(['phases' => fn ($q) => $q->orderBy('sequence'), 'phases.procedures.procedureCode'])
            ->latest()
            ->get();

        $proceduresByTooth = [];
        foreach ($plans as $plan) {
            foreach ($plan->phases as $phase) {
                foreach ($phase->procedures as $procedure) {
                    if ($procedure->tooth_number_fdi) {
                        $proceduresByTooth[(string) $procedure->tooth_number_fdi][] = $procedure;
                    }
                }
            }
        }

        $user = auth()->user();

        return response()->json([
            'patient' => [
                'id' => $patient->id,
                'name' => trim($patient->first_name.' '.$patient->last_name),
                'first_name' => $patient->first_name,
                'last_name' => $patient->last_name,
                'file_number' => $patient->file_number,
                'gender' => $patient->gender,
                'age' => $patient->dob?->age,
                'phone' => $patient->phone,
                'blood_type' => $patient->medicalHistory?->blood_type,
            ],
            'alerts' => PatientSnapshot::alerts($patient->medicalHistory),
            'examinations' => $examinations,
            'current_exam_id' => $current?->id,
            'records' => $current ? self::records($current, $patient) : self::patientLevelRecords($patient),
            'procedures_by_tooth' => (object) $proceduresByTooth,
            'plans' => $plans,
            'procedure_codes' => ProcedureCode::with('category:id,name')->orderBy('code')->get(['id', 'code', 'title', 'standard_fee', 'category_id']),
            'can_edit_pricing' => $user->hasAnyRole(['clinic_admin', 'super_admin', 'developer']),
        ]);
    }

    /** Chart for an examination, normalised (structured diagnosis, surfaces as an object). */
    public static function records(DentalExamination $examination, ?Patient $patient = null): array
    {
        $examination->loadMissing('toothFindings.surfaceFindings');
        $records = [];

        foreach ($examination->toothFindings as $finding) {
            $records[$finding->tooth_number_fdi] = self::record(
                $finding->finding_type,
                $finding->status,
                $finding->clinical_details,
                $finding->notes,
                $finding->surfaceFindings->pluck('finding_type', 'surface')->all(),
            );
        }

        // Teeth charted only at patient level (older data) still show on the current exam.
        if ($patient) {
            foreach ($patient->teeth()->get() as $pt) {
                $records[$pt->tooth_number] ??= self::record($pt->condition, $pt->status, $pt->clinical_details, $pt->notes, $pt->surfaces ?? []);
            }
        }

        return $records;
    }

    protected static function patientLevelRecords(Patient $patient): array
    {
        $records = [];
        foreach ($patient->teeth()->get() as $pt) {
            $records[$pt->tooth_number] = self::record($pt->condition, $pt->status, $pt->clinical_details, $pt->notes, $pt->surfaces ?? []);
        }

        return $records;
    }

    protected static function record(?string $condition, ?string $status, mixed $details, ?string $notes, array $surfaces): array
    {
        $normalised = OdontogramRules::normaliseRecord($notes, $details);

        return [
            'condition' => $condition ?: 'healthy',
            'status' => $status ?: 'existing',
            'clinical_details' => (object) $normalised['clinical_details'],
            'notes' => $normalised['notes'],
            'surfaces' => (object) $surfaces,
        ];
    }
}
