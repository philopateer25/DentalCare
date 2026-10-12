<?php

namespace App\Filament\Resources\PatientResource\Widgets;

use App\Models\Appointment;
use App\Models\ExternalTreatment;
use App\Models\Patient;
use App\Models\Prescription;
use App\Models\TreatmentPlan;
use App\Models\TreatmentProcedure;
use App\Models\VoiceNote;
use App\Services\ClinicalPresets;
use Filament\Notifications\Notification;
use Filament\Widgets\Widget;
use Illuminate\Support\Carbon;

class PatientMedicalHistory360Widget extends Widget
{
    public ?Patient $record = null;

    protected static string $view = 'filament.resources.patient-resource.widgets.patient-medical-history360-widget';

    protected int | string | array $columnSpan = 'full';

    // Quick-Add Modal State
    public bool $showAddExternalModal = false;
    public string $extProcedureTitle = '';
    public ?string $extToothNumber = null;
    public string $extPerformedYear = '';
    public string $extClinicOrDoctor = '';
    public string $extNotes = '';

    protected $listeners = ['refreshPatient360' => '$refresh'];

    public function mount(?Patient $record = null): void
    {
        if ($record) {
            $this->record = $record;
        }
    }

    public function openAddExternalModal(): void
    {
        $this->resetAddExternalForm();
        $this->showAddExternalModal = true;
    }

    public function closeAddExternalModal(): void
    {
        $this->showAddExternalModal = false;
        $this->resetAddExternalForm();
    }

    public function resetAddExternalForm(): void
    {
        $this->extProcedureTitle = '';
        $this->extToothNumber = null;
        $this->extPerformedYear = date('Y');
        $this->extClinicOrDoctor = '';
        $this->extNotes = '';
    }

    public function saveExternalTreatment(): void
    {
        $user = auth()->user();
        if (!$user) {
            return;
        }

        // Validate tenant isolation
        if ($user->practice_id && $this->record?->practice_id !== $user->practice_id) {
            abort(403, 'Unauthorized cross-tenant patient access.');
        }

        $this->validate([
            'extProcedureTitle' => 'required|string|max:255',
            'extToothNumber' => 'nullable|integer|min:11|max:85',
            'extPerformedYear' => 'nullable|string|max:50',
            'extClinicOrDoctor' => 'nullable|string|max:255',
            'extNotes' => 'nullable|string|max:2000',
        ]);

        ExternalTreatment::create([
            'patient_id' => $this->record->id,
            'procedure_title' => $this->extProcedureTitle,
            'tooth_number_fdi' => !empty($this->extToothNumber) ? (int) $this->extToothNumber : null,
            'performed_year' => $this->extPerformedYear,
            'clinic_or_doctor_name' => $this->extClinicOrDoctor,
            'notes' => $this->extNotes,
            'created_by' => $user->id,
        ]);

        Notification::make()
            ->title('External treatment logged')
            ->success()
            ->send();

        $this->closeAddExternalModal();
        $this->dispatch('refreshPatient360');
    }

    public function deleteExternalTreatment(int $id): void
    {
        $user = auth()->user();
        $treatment = ExternalTreatment::find($id);

        if (!$treatment || $treatment->patient_id !== $this->record?->id) {
            return;
        }

        if ($user->practice_id && $treatment->patient?->practice_id !== $user->practice_id) {
            abort(403, 'Unauthorized cross-tenant patient access.');
        }

        $treatment->delete();

        Notification::make()
            ->title('External treatment removed')
            ->warning()
            ->send();

        $this->dispatch('refreshPatient360');
    }

    /**
     * Compute categorized medical risks with nuanced positive, negative, and unassessed statuses.
     */
    public function getMedicalRiskAssessment(): array
    {
        $med = $this->record?->medicalHistory;
        $risks = [];

        // 1. DIABETES
        if (!$med || $med->diabetic_status === null) {
            $risks['diabetes'] = [
                'level' => 'unknown',
                'title' => 'Diabetes Mellitus',
                'badge' => 'Unassessed / Missing Data',
                'description' => 'Diabetic status not yet recorded. Confirm blood glucose/HbA1c prior to invasive surgical procedures.',
                'color' => 'amber',
            ];
        } elseif ($med->diabetic_status) {
            $type = $med->diabetes_type ? ucwords(str_replace('_', ' ', $med->diabetes_type)) : 'Type Unspecified';
            $hba1c = $med->hba1c_level ? "HbA1c: {$med->hba1c_level}" : 'HbA1c Not Tested';
            $sugar = $med->blood_sugar_reading ? "FBS/RBS: {$med->blood_sugar_reading} mg/dL" : '';
            $hypo = $med->hypoglycemia_history ? '⚠️ History of Hypoglycemic Shock' : '';

            $details = array_filter([$type, $hba1c, $sugar, $hypo]);

            $risks['diabetes'] = [
                'level' => 'danger',
                'title' => 'Diabetes Mellitus (Active Risk)',
                'badge' => 'High Risk · ' . ($med->hba1c_level ?: 'Active'),
                'description' => implode(' · ', $details),
                'color' => 'rose',
            ];
        } else {
            $risks['diabetes'] = [
                'level' => 'safe',
                'title' => 'Diabetes Mellitus',
                'badge' => 'Documented Negative',
                'description' => 'Patient has confirmed no history of diabetes or blood sugar abnormalities.',
                'color' => 'emerald',
            ];
        }

        // 2. BLEEDING & ANTICOAGULANTS
        if (!$med || $med->bleeding_disorder === null) {
            $risks['bleeding'] = [
                'level' => 'unknown',
                'title' => 'Bleeding / Blood Thinners',
                'badge' => 'Unassessed / Missing Data',
                'description' => 'Coagulation status unassessed. Verify anticoagulant medications and INR before extractions.',
                'color' => 'amber',
            ];
        } elseif ($med->bleeding_disorder) {
            $drug = $med->anticoagulant_type ? strtoupper($med->anticoagulant_type) : 'Active Anticoagulant Therapy';
            $inr = $med->inr_level ? "INR Level: {$med->inr_level}" : 'INR Unspecified';

            $risks['bleeding'] = [
                'level' => 'danger',
                'title' => 'Bleeding / Anticoagulants (High Risk)',
                'badge' => "Active Risk · {$drug}",
                'description' => "{$drug} therapy active. {$inr}. Hemostasis precautions and local measures required.",
                'color' => 'rose',
            ];
        } else {
            $risks['bleeding'] = [
                'level' => 'safe',
                'title' => 'Bleeding Disorders',
                'badge' => 'Documented Negative',
                'description' => 'No known bleeding disorders, coagulopathies, or current anticoagulant usage.',
                'color' => 'emerald',
            ];
        }

        // 3. BISPHOSPHONATES / MRONJ BONE RISK
        if (!$med || $med->bisphosphonate_therapy === null) {
            $risks['bisphosphonates'] = [
                'level' => 'unknown',
                'title' => 'Bisphosphonate Therapy (MRONJ)',
                'badge' => 'Unassessed / Missing Data',
                'description' => 'Bone antiresorptive therapy history not recorded. Screen before extractions or implant placement.',
                'color' => 'amber',
            ];
        } elseif ($med->bisphosphonate_therapy) {
            $details = $med->bisphosphonate_details ?: 'Antiresorptive Bone Therapy Active';
            $risks['bisphosphonates'] = [
                'level' => 'danger',
                'title' => 'MRONJ High Risk Alert',
                'badge' => 'CRITICAL · Bisphosphonates Active',
                'description' => "High risk of Medication-Related Osteonecrosis of the Jaw. Details: {$details}. Strictly avoid elective bone trauma.",
                'color' => 'rose',
            ];
        } else {
            $risks['bisphosphonates'] = [
                'level' => 'safe',
                'title' => 'Bisphosphonates (MRONJ)',
                'badge' => 'Documented Cleared',
                'description' => 'No history of oral or IV bisphosphonate or antiresorptive medication use.',
                'color' => 'slate',
            ];
        }

        // 4. ALLERGIES (Penicillin, Latex, Local Anesthetic)
        $allergiesPositive = [];
        $allergiesUnknown = [];
        $allergiesNegative = [];

        if (!$med || $med->penicillin_allergy === null) {
            $allergiesUnknown[] = 'Penicillin';
        } elseif ($med->penicillin_allergy) {
            $allergiesPositive[] = 'Penicillin / Amoxicillin';
        } else {
            $allergiesNegative[] = 'Penicillin Cleared';
        }

        if (!$med || $med->latex_allergy === null) {
            $allergiesUnknown[] = 'Latex';
        } elseif ($med->latex_allergy) {
            $allergiesPositive[] = 'Latex (Use non-latex gloves & dam)';
        } else {
            $allergiesNegative[] = 'Latex Cleared';
        }

        if (!$med || $med->local_anesthetic_allergy === null) {
            $allergiesUnknown[] = 'Local Anesthetic';
        } elseif ($med->local_anesthetic_allergy) {
            $allergiesPositive[] = 'Local Anesthetics (Allergic Shock Risk)';
        } else {
            $allergiesNegative[] = 'Local Anesthetics Cleared';
        }

        if (!empty($allergiesPositive)) {
            $risks['allergies'] = [
                'level' => 'danger',
                'title' => 'Allergies: Documented Positive',
                'badge' => 'ALLERGY ALERT',
                'description' => 'Confirmed Allergies: ' . implode(', ', $allergiesPositive),
                'color' => 'rose',
            ];
        } elseif (!empty($allergiesUnknown)) {
            $risks['allergies'] = [
                'level' => 'unknown',
                'title' => 'Allergies: Partial Data',
                'badge' => 'Unassessed: ' . implode(', ', $allergiesUnknown),
                'description' => 'Confirm allergy status for ' . implode(', ', $allergiesUnknown) . ' prior to administration.',
                'color' => 'amber',
            ];
        } else {
            $risks['allergies'] = [
                'level' => 'safe',
                'title' => 'Allergies Cleared',
                'badge' => 'No Known Allergies',
                'description' => 'Patient has confirmed no known allergies to Penicillin, Latex, or Local Anesthetics.',
                'color' => 'emerald',
            ];
        }

        // 5. CARDIAC & ANTIBIOTIC PROPHYLAXIS
        if ($med && $med->requires_antibiotic_prophylaxis) {
            $conditions = is_array($med->cardiac_conditions_json) 
                ? implode(', ', array_map('ucwords', $med->cardiac_conditions_json)) 
                : 'High-risk cardiac condition';

            $risks['cardiac'] = [
                'level' => 'danger',
                'title' => 'Infective Endocarditis Risk',
                'badge' => 'ANTIBIOTIC PROPHYLAXIS REQUIRED',
                'description' => "Administer prophylactic antibiotics (e.g., 2g Amoxicillin or 600mg Clindamycin) 30–60 min prior to bacteremic procedures. Indication: {$conditions}.",
                'color' => 'rose',
            ];
        } elseif ($med && !empty($med->cardiac_conditions_json)) {
            $conditions = is_array($med->cardiac_conditions_json) 
                ? implode(', ', array_map('ucwords', $med->cardiac_conditions_json)) 
                : 'Cardiac history';

            $risks['cardiac'] = [
                'level' => 'warning',
                'title' => 'Cardiovascular History',
                'badge' => 'Cardiac Caution',
                'description' => "Conditions on record: {$conditions}. Monitor vitals and limit epinephrine as indicated.",
                'color' => 'amber',
            ];
        }

        return $risks;
    }

    /**
     * Get Column 1 External Treatments.
     */
    public function getExternalTreatments(): \Illuminate\Database\Eloquent\Collection
    {
        return ExternalTreatment::with('creator:id,name')
            ->where('patient_id', $this->record?->id)
            ->latest()
            ->get();
    }

    /**
     * Get Column 2 Unified Clinic Journey Timeline.
     */
    public function getClinicJourneyTimeline(): array
    {
        $patientId = $this->record?->id;
        $timeline = [];

        // 1. Completed Procedures
        $procedures = TreatmentProcedure::with(['procedureCode', 'doctor:id,name', 'phase.plan'])
            ->whereHas('phase.plan', fn ($q) => $q->where('patient_id', $patientId))
            ->where(function ($q) {
                $q->where('status', 'completed')
                  ->orWhereNotNull('completed_at');
            })
            ->latest('completed_at')
            ->get();

        foreach ($procedures as $proc) {
            $date = $proc->completed_at ?: $proc->updated_at;
            $timeline[] = [
                'type' => 'procedure',
                'date' => $date ? Carbon::parse($date) : now(),
                'title' => ($proc->tooth_number_fdi ? "Tooth #{$proc->tooth_number_fdi}: " : '') . ($proc->procedureCode?->title ?: 'Clinical Treatment'),
                'doctor' => $proc->doctor?->name ?: 'Treating Doctor',
                'status' => 'Completed',
                'details' => [
                    'anesthetic' => $proc->anesthetic_agent ? "{$proc->anesthetic_agent} ({$proc->anesthetic_dosage}) - {$proc->anesthetic_technique}" : null,
                    'materials' => $proc->materials_used_json,
                    'tools' => $proc->tools_used_json,
                    'hemostasis' => $proc->hemostasis_achieved ? 'Hemostasis Achieved' : null,
                    'notes' => $proc->chairside_notes ?: $proc->notes,
                ],
            ];
        }

        // 2. Prescriptions
        $prescriptions = Prescription::with('doctor:id,name')
            ->where('patient_id', $patientId)
            ->latest('issue_date')
            ->get();

        foreach ($prescriptions as $rx) {
            $timeline[] = [
                'type' => 'prescription',
                'date' => Carbon::parse($rx->issue_date ?: $rx->created_at),
                'title' => 'Prescription Issued',
                'doctor' => $rx->doctor?->name ?: 'Prescribing Doctor',
                'status' => $rx->status ?: 'Active',
                'details' => [
                    'medications' => $rx->medications,
                    'instructions' => $rx->instructions,
                    'notes' => $rx->notes,
                ],
            ];
        }

        // 3. Appointments / Consultations
        $appointments = Appointment::with(['doctor:id,name', 'operatory:id,name'])
            ->where('patient_id', $patientId)
            ->where('starts_at', '<', now())
            ->latest('starts_at')
            ->take(10)
            ->get();

        foreach ($appointments as $apt) {
            $timeline[] = [
                'type' => 'appointment',
                'date' => Carbon::parse($apt->starts_at),
                'title' => 'Clinic Visit: ' . ucwords($apt->type ?: 'Consultation'),
                'doctor' => $apt->doctor?->name ?: 'Doctor',
                'status' => ucwords($apt->status),
                'details' => [
                    'operatory' => $apt->operatory?->name,
                    'chief_complaint' => $apt->chief_complaint,
                    'notes' => $apt->notes,
                ],
            ];
        }

        // 4. Voice Notes Sent
        $voiceNotes = VoiceNote::with('user:id,name')
            ->where('patient_id', $patientId)
            ->latest()
            ->get();

        foreach ($voiceNotes as $vn) {
            $timeline[] = [
                'type' => 'voice_note',
                'date' => Carbon::parse($vn->created_at),
                'title' => '🎙️ Post-Op Voice Note (' . ($vn->tooth_number_fdi ? "Tooth #{$vn->tooth_number_fdi}" : 'General') . ')',
                'doctor' => $vn->user?->name ?: 'Doctor',
                'status' => 'WhatsApp Link Prepared',
                'details' => [
                    'title' => $vn->title,
                    'share_url' => url("/voice-notes/stream/{$vn->share_token}"),
                    'expires_at' => $vn->token_expires_at?->format('M d, Y'),
                    'duration' => $vn->duration_seconds ? "{$vn->duration_seconds}s" : null,
                ],
            ];
        }

        // Sort timeline by date descending
        usort($timeline, fn ($a, $b) => $b['date'] <=> $a['date']);

        return $timeline;
    }

    /**
     * Get Column 3 Future Roadmap (Active Treatment Plan Phases + Next Appointment).
     */
    public function getFutureRoadmap(): array
    {
        $patientId = $this->record?->id;

        // Next upcoming appointment
        $nextAppointment = Appointment::with(['doctor:id,name', 'operatory:id,name'])
            ->where('patient_id', $patientId)
            ->where('starts_at', '>=', now())
            ->whereIn('status', ['scheduled', 'confirmed'])
            ->orderBy('starts_at', 'asc')
            ->first();

        // Active treatment plan phases and pending procedures
        $activePlan = TreatmentPlan::with(['phases.procedures.procedureCode', 'doctor:id,name'])
            ->where('patient_id', $patientId)
            ->whereIn('status', ['active', 'in_progress', 'proposed'])
            ->latest()
            ->first();

        return [
            'next_appointment' => $nextAppointment,
            'active_plan' => $activePlan,
        ];
    }
}
