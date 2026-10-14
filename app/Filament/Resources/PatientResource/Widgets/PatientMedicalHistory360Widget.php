<?php

namespace App\Filament\Resources\PatientResource\Widgets;

use App\Models\Appointment;
use App\Models\ExternalTreatment;
use App\Models\Patient;
use App\Models\PatientTooth;
use App\Models\Prescription;
use App\Models\ToothFinding;
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

    // Filter State
    public string $historyFilter = 'all'; // 'all', 'internal', 'external' (source)
    public string $typeFilter = 'all'; // 'all', 'procedure', 'appointment', 'condition', 'external', 'prescription', 'voice_note'
    public string $toothFilter = 'all'; // 'all', 'general', or specific tooth number (e.g. '11', '16', '48')
    public string $doctorFilter = 'all'; // 'all' or specific doctor name
    public string $searchQuery = ''; // instant text search
    public string $sortOrder = 'desc'; // 'desc', 'asc'

    // Timeline Expand/Collapse State
    public bool $isTimelineCollapsed = false; // whether the unified logs card is collapsed
    public bool $isTimelineMaximized = false; // whether the unified logs card is expanded full width
    public bool $expandAllEntries = false; // whether all timeline entries are expanded inline
    public array $expandedItemKeys = []; // individual expanded items

    // Interactive Modal States
    public ?string $activeModal = null; // 'external_add', 'plan', 'procedure', 'prescription', 'external', 'visit', 'tooth_3d'
    public array $modalData = [];
    public ?string $selectedToothFor3D = null;

    // Quick-Add External Treatment Form State
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

    public function toggleTimelineCollapse(): void
    {
        $this->isTimelineCollapsed = !$this->isTimelineCollapsed;
    }

    public function toggleTimelineMaximized(): void
    {
        $this->isTimelineMaximized = !$this->isTimelineMaximized;
    }

    public function toggleExpandAllEntries(): void
    {
        $this->expandAllEntries = !$this->expandAllEntries;
        if (!$this->expandAllEntries) {
            $this->expandedItemKeys = [];
        }
    }

    public function toggleItemExpanded(string $itemKey): void
    {
        if (in_array($itemKey, $this->expandedItemKeys, true)) {
            $this->expandedItemKeys = array_values(array_diff($this->expandedItemKeys, [$itemKey]));
        } else {
            $this->expandedItemKeys[] = $itemKey;
        }
    }

    public function setHistoryFilter(string $filter): void
    {
        $this->historyFilter = in_array($filter, ['all', 'internal', 'external']) ? $filter : 'all';
    }

    public function setTypeFilter(string $type): void
    {
        $this->typeFilter = $type;
    }

    public function setToothFilter(string $tooth): void
    {
        $this->toothFilter = $tooth;
    }

    public function setDoctorFilter(string $doctor): void
    {
        $this->doctorFilter = $doctor;
    }

    public function setSortOrder(string $order): void
    {
        $this->sortOrder = in_array($order, ['asc', 'desc'], true) ? $order : 'desc';
    }

    public function resetTimelineFilters(): void
    {
        $this->historyFilter = 'all';
        $this->typeFilter = 'all';
        $this->toothFilter = 'all';
        $this->doctorFilter = 'all';
        $this->searchQuery = '';
        $this->sortOrder = 'desc';
    }

    public function updateToothCondition(string $toothNumber, string $condition, ?string $notes = null): void
    {
        $user = auth()->user();
        if ($user && $user->practice_id && $this->record?->practice_id !== $user->practice_id) {
            abort(403, 'Unauthorized cross-tenant patient access.');
        }

        $validConditions = ['healthy', 'crown', 'implant', 'root_canal', 'active_caries', 'composite_filled', 'missing', 'custom'];
        if (!in_array($condition, $validConditions, true)) {
            return;
        }

        $patientId = $this->record?->id;
        if (!$patientId) return;

        // 1. Update patient_teeth table
        PatientTooth::updateOrCreate(
            ['patient_id' => $patientId, 'tooth_number' => (string) $toothNumber],
            [
                'condition' => $condition,
                'notes' => $notes ?: ($condition === 'crown' ? 'Crown charted on 360 profile' : null),
            ]
        );

        // 2. Update or create ToothFinding on latest examination
        $exam = $this->record->examinations()->latest('examined_at')->first();
        if (!$exam) {
            $exam = $this->record->examinations()->create([
                'doctor_id' => auth()->id() ?? 1,
                'type' => 'periodic',
                'examined_at' => now(),
            ]);
        } elseif (!$exam->examined_at) {
            $exam->update(['examined_at' => now()]);
        }

        $exam->toothFindings()->updateOrCreate(
            ['tooth_number_fdi' => (string) $toothNumber],
            [
                'finding_type' => $condition,
                'notes' => $notes ?: ($condition === 'crown' ? 'Crown documented' : null),
            ]
        );

        $this->record?->touch();

        if ($this->activeModal === 'tooth_3d' && $this->selectedToothFor3D === $toothNumber) {
            $this->openTooth3DModal($toothNumber);
        }

        $labels = [
            'crown' => 'Crown (Prosthesis)',
            'implant' => 'Dental Implant',
            'root_canal' => 'Root Canal Treated',
            'active_caries' => 'Active Caries',
            'composite_filled' => 'Composite Filling',
            'missing' => 'Missing Tooth',
            'healthy' => 'Healthy / Sound',
            'custom' => 'Custom Finding',
        ];

        Notification::make()
            ->title("Tooth #{$toothNumber} Updated")
            ->body("Condition set to: " . ($labels[$condition] ?? ucfirst($condition)))
            ->success()
            ->send();

        $this->dispatch('refreshPatient360');
    }

    public function closeModal(): void
    {
        $this->activeModal = null;
        $this->modalData = [];
        $this->selectedToothFor3D = null;
    }

    public function openAddExternalModal(): void
    {
        $this->resetAddExternalForm();
        $this->activeModal = 'external_add';
    }

    public function closeAddExternalModal(): void
    {
        $this->closeModal();
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

        $ext = ExternalTreatment::create([
            'patient_id' => $this->record->id,
            'procedure_title' => $this->extProcedureTitle,
            'tooth_number_fdi' => !empty($this->extToothNumber) ? (int) $this->extToothNumber : null,
            'performed_year' => $this->extPerformedYear,
            'clinic_or_doctor_name' => $this->extClinicOrDoctor,
            'notes' => $this->extNotes,
            'created_by' => $user->id,
        ]);

        if (!empty($this->extToothNumber)) {
            $tTitle = strtolower($this->extProcedureTitle);
            $cond = null;
            if (str_contains($tTitle, 'crown') || str_contains($tTitle, 'cap') || str_contains($tTitle, 'bridge')) $cond = 'crown';
            elseif (str_contains($tTitle, 'implant')) $cond = 'implant';
            elseif (str_contains($tTitle, 'root canal') || str_contains($tTitle, 'rct') || str_contains($tTitle, 'endo')) $cond = 'root_canal';
            elseif (str_contains($tTitle, 'fill') || str_contains($tTitle, 'composite')) $cond = 'composite_filled';
            elseif (str_contains($tTitle, 'missing') || str_contains($tTitle, 'extract')) $cond = 'missing';

            if ($cond) {
                PatientTooth::updateOrCreate(
                    ['patient_id' => $this->record->id, 'tooth_number' => (string) $this->extToothNumber],
                    ['condition' => $cond, 'notes' => "Prior treatment: {$this->extProcedureTitle}"]
                );
            }
        }

        $this->record?->touch();

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

        if ($this->activeModal === 'external' && ($this->modalData['id'] ?? null) === $id) {
            $this->closeModal();
        }

        Notification::make()
            ->title('External treatment removed')
            ->warning()
            ->send();

        $this->dispatch('refreshPatient360');
    }

    /**
     * Open Treatment Plan Detail Popup
     */
    public function openTreatmentPlanModal(int $planId): void
    {
        $plan = TreatmentPlan::with(['phases.procedures.procedureCode', 'doctor:id,name'])
            ->where('patient_id', $this->record?->id)
            ->find($planId);

        if (!$plan) return;

        $phasesData = [];
        foreach ($plan->phases as $phase) {
            $procedures = [];
            foreach ($phase->procedures as $proc) {
                $procedures[] = [
                    'id' => $proc->id,
                    'tooth_number_fdi' => $proc->tooth_number_fdi,
                    'title' => $proc->procedureCode?->title ?: 'Clinical Item',
                    'code' => $proc->procedureCode?->code,
                    'status' => $proc->status,
                    'cost' => $proc->cost,
                    'anesthetic' => $proc->anesthetic_agent ? "{$proc->anesthetic_agent} ({$proc->anesthetic_dosage})" : null,
                    'materials' => is_array($proc->materials_used_json) ? implode(', ', $proc->materials_used_json) : $proc->materials_used_json,
                    'notes' => $proc->chairside_notes ?: $proc->notes,
                ];
            }
            $phasesData[] = [
                'name' => $phase->phase_name ?: "Phase " . ($phase->phase_number ?? 1),
                'status' => $phase->status,
                'procedures' => $procedures,
            ];
        }

        $this->modalData = [
            'id' => $plan->id,
            'title' => $plan->title,
            'status' => $plan->status,
            'doctor' => $plan->doctor?->name ?: 'Doctor',
            'created_at' => $plan->created_at->format('M d, Y'),
            'total_cost' => $plan->total_cost,
            'phases' => $phasesData,
        ];
        $this->activeModal = 'plan';
    }

    /**
     * Open Procedure Detail Popup
     */
    public function openProcedureModal(int $procedureId): void
    {
        $proc = TreatmentProcedure::with(['procedureCode', 'doctor:id,name', 'phase.plan'])
            ->find($procedureId);

        if (!$proc || $proc->phase?->plan?->patient_id !== $this->record?->id) return;

        $this->modalData = [
            'id' => $proc->id,
            'tooth_number_fdi' => $proc->tooth_number_fdi,
            'title' => $proc->procedureCode?->title ?: 'Clinical Treatment',
            'code' => $proc->procedureCode?->code,
            'category' => $proc->procedureCode?->category,
            'plan_title' => $proc->phase?->plan?->title,
            'doctor' => $proc->doctor?->name ?: 'Treating Doctor',
            'status' => $proc->status,
            'cost' => $proc->cost,
            'completed_at' => $proc->completed_at ? Carbon::parse($proc->completed_at)->format('l, M d, Y - h:i A') : ($proc->updated_at ? $proc->updated_at->format('M d, Y') : null),
            'anesthetic_agent' => $proc->anesthetic_agent,
            'anesthetic_dosage' => $proc->anesthetic_dosage,
            'anesthetic_technique' => $proc->anesthetic_technique,
            'materials_used' => is_array($proc->materials_used_json) ? implode(', ', $proc->materials_used_json) : $proc->materials_used_json,
            'tools_used' => is_array($proc->tools_used_json) ? implode(', ', $proc->tools_used_json) : $proc->tools_used_json,
            'hemostasis' => $proc->hemostasis_achieved ? 'Hemostasis Achieved' : null,
            'notes' => $proc->chairside_notes ?: $proc->notes,
        ];
        $this->activeModal = 'procedure';
    }

    /**
     * Open Prescription Detail Popup
     */
    public function openPrescriptionModal(int $prescriptionId): void
    {
        $rx = Prescription::with('doctor:id,name')
            ->where('patient_id', $this->record?->id)
            ->find($prescriptionId);

        if (!$rx) return;

        $date = $rx->date_prescribed ?: $rx->created_at;
        $this->modalData = [
            'id' => $rx->id,
            'doctor' => $rx->doctor?->name ?: 'Doctor',
            'issue_date' => $date ? Carbon::parse($date)->format('M d, Y') : 'Active',
            'status' => ucfirst($rx->status ?: 'Active'),
            'medication_name' => $rx->medication_name,
            'dosage' => $rx->dosage,
            'frequency' => $rx->frequency,
            'duration' => $rx->duration,
            'instructions' => $rx->instructions,
            'medications' => [
                [
                    'name' => $rx->medication_name,
                    'dosage' => $rx->dosage,
                    'frequency' => $rx->frequency,
                    'duration' => $rx->duration,
                ]
            ],
            'notes' => $rx->instructions,
        ];
        $this->activeModal = 'prescription';
    }

    /**
     * Open External Treatment Record Popup
     */
    public function openExternalModal(int $externalId): void
    {
        $ext = ExternalTreatment::with('creator:id,name')
            ->where('patient_id', $this->record?->id)
            ->find($externalId);

        if (!$ext) return;

        $this->modalData = [
            'id' => $ext->id,
            'procedure_title' => $ext->procedure_title,
            'tooth_number_fdi' => $ext->tooth_number_fdi,
            'performed_year' => $ext->performed_year,
            'clinic_or_doctor_name' => $ext->clinic_or_doctor_name,
            'notes' => $ext->notes,
            'created_by' => $ext->creator?->name ?: 'Staff',
            'created_at' => $ext->created_at->format('M d, Y'),
        ];
        $this->activeModal = 'external';
    }

    /**
     * Open Clinic Visit Popup
     */
    public function openVisitModal(int $appointmentId): void
    {
        $apt = Appointment::with(['doctor:id,name', 'operatory:id,name', 'branch:id,name'])
            ->where('patient_id', $this->record?->id)
            ->find($appointmentId);

        if (!$apt) return;

        $time = $apt->start_time ?: $apt->created_at;
        $this->modalData = [
            'id' => $apt->id,
            'starts_at' => $time ? Carbon::parse($time)->format('l, M d, Y - h:i A') : 'Scheduled',
            'type' => ucwords(str_replace('_', ' ', $apt->type ?: 'Consultation')),
            'status' => ucwords(str_replace('_', ' ', $apt->status ?: 'Booked')),
            'doctor' => $apt->doctor?->name ?: 'Doctor',
            'operatory' => $apt->operatory?->name,
            'branch' => $apt->branch?->name,
            'procedure_name' => $apt->procedure_name,
            'tooth_number' => $apt->tooth_number,
            'chief_complaint' => $apt->chief_complaint,
            'notes' => $apt->notes,
        ];
        $this->activeModal = 'visit';
    }

    /**
     * Open 3D Tooth Focus Modal
     */
    public function openTooth3DModal(string $toothNumber): void
    {
        $this->selectedToothFor3D = $toothNumber;
        
        $patientId = $this->record?->id;
        
        $insideProcs = TreatmentProcedure::with(['procedureCode', 'doctor:id,name'])
            ->whereHas('phase.plan', fn ($q) => $q->where('patient_id', $patientId))
            ->where('tooth_number_fdi', (int) $toothNumber)
            ->latest()
            ->get();

        $outsideTreatments = ExternalTreatment::where('patient_id', $patientId)
            ->where('tooth_number_fdi', (int) $toothNumber)
            ->latest()
            ->get();

        // 3D Model conditions & findings for this tooth (fresh queries)
        $patientTooth = PatientTooth::where('patient_id', $patientId)
            ->where('tooth_number', (string) $toothNumber)
            ->first();

        $toothFinding = ToothFinding::with('surfaceFindings')
            ->whereHas('examination', fn ($q) => $q->where('patient_id', $patientId))
            ->where('tooth_number_fdi', (string) $toothNumber)
            ->latest('created_at')
            ->first();

        // Detect condition from procedures or external treatments if not explicitly set
        $conditionRaw = 'healthy';
        if ($patientTooth && $patientTooth->condition && $patientTooth->condition !== 'healthy') {
            $conditionRaw = $patientTooth->condition;
        } elseif ($toothFinding && $toothFinding->finding_type && $toothFinding->finding_type !== 'healthy') {
            $conditionRaw = $toothFinding->finding_type;
        } else {
            // Check inside clinic procedures on this tooth
            foreach ($insideProcs as $proc) {
                $pTitle = strtolower($proc->procedureCode?->title ?: '');
                $pCode = strtoupper($proc->procedureCode?->code ?: '');
                if (str_contains($pTitle, 'crown') || str_contains($pCode, 'D2740') || str_contains($pTitle, 'cap') || str_contains($pTitle, 'bridge')) {
                    $conditionRaw = 'crown'; break;
                } elseif (str_contains($pTitle, 'implant') || str_contains($pCode, 'D6010')) {
                    $conditionRaw = 'implant'; break;
                } elseif (str_contains($pTitle, 'root canal') || str_contains($pTitle, 'endo') || str_contains($pCode, 'D3310')) {
                    $conditionRaw = 'root_canal'; break;
                } elseif (str_contains($pTitle, 'composite') || str_contains($pTitle, 'fill') || str_contains($pCode, 'D2330')) {
                    $conditionRaw = 'composite_filled'; break;
                } elseif (str_contains($pTitle, 'extract') || str_contains($pCode, 'D7140')) {
                    $conditionRaw = 'missing'; break;
                }
            }

            if ($conditionRaw === 'healthy') {
                foreach ($outsideTreatments as $ext) {
                    $eTitle = strtolower($ext->procedure_title ?: '');
                    if (str_contains($eTitle, 'crown') || str_contains($eTitle, 'cap') || str_contains($eTitle, 'bridge')) {
                        $conditionRaw = 'crown'; break;
                    } elseif (str_contains($eTitle, 'implant')) {
                        $conditionRaw = 'implant'; break;
                    } elseif (str_contains($eTitle, 'root canal') || str_contains($eTitle, 'rct') || str_contains($eTitle, 'endo')) {
                        $conditionRaw = 'root_canal'; break;
                    } elseif (str_contains($eTitle, 'fill') || str_contains($eTitle, 'composite')) {
                        $conditionRaw = 'composite_filled'; break;
                    } elseif (str_contains($eTitle, 'missing') || str_contains($eTitle, 'extract')) {
                        $conditionRaw = 'missing'; break;
                    }
                }
            }
        }

        $conditionLabels = [
            'missing' => 'Missing Tooth',
            'crown' => 'Crown (Prosthesis)',
            'implant' => 'Dental Implant',
            'root_canal' => 'Root Canal Treated',
            'active_caries' => 'Active Caries / Decay',
            'composite_filled' => 'Composite Restoration',
            'restoration' => 'Restoration',
            'healthy' => 'Healthy / Intact',
            'custom' => 'Custom Finding',
        ];
        $conditionLabel = $conditionLabels[$conditionRaw] ?? ucwords(str_replace('_', ' ', $conditionRaw));

        $surfaces = [];
        if ($toothFinding && $toothFinding->surfaceFindings->count() > 0) {
            $surfaces = $toothFinding->surfaceFindings->map(fn($sf) => "{$sf->surface}: {$sf->finding_type}")->toArray();
        } elseif ($patientTooth && is_array($patientTooth->surfaces)) {
            $surfaces = array_keys(array_filter($patientTooth->surfaces, fn($v) => $v && $v !== 'healthy'));
        }

        $notes = $toothFinding?->notes ?: ($patientTooth?->notes ?: null);

        $voiceNotes = VoiceNote::where('patient_id', $patientId)
            ->where('tooth_number_fdi', (int) $toothNumber)
            ->latest()
            ->get();

        $this->modalData = [
            'tooth' => $toothNumber,
            'current_condition' => $conditionLabel,
            'condition_raw' => $conditionRaw,
            'surfaces' => $surfaces,
            'tooth_notes' => $notes,
            'inside_procedures' => $insideProcs,
            'outside_treatments' => $outsideTreatments,
            'voice_notes' => $voiceNotes,
        ];

        $this->activeModal = 'tooth_3d';
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
     * Get Tooth-by-Tooth History Map (Links all findings, conditions, procedures & external treatments to tooth numbers).
     */
    public function getToothHistoryMap(): array
    {
        $patientId = $this->record?->id;
        $teeth = [];

        $conditionLabels = [
            'missing' => ['label' => 'Missing Tooth', 'color' => 'slate'],
            'crown' => ['label' => 'Crown', 'color' => 'purple'],
            'implant' => ['label' => 'Implant', 'color' => 'blue'],
            'root_canal' => ['label' => 'Root Canal (Endo)', 'color' => 'rose'],
            'active_caries' => ['label' => 'Active Caries', 'color' => 'amber'],
            'caries' => ['label' => 'Active Caries', 'color' => 'amber'],
            'composite_filled' => ['label' => 'Composite Filling', 'color' => 'emerald'],
            'restoration' => ['label' => 'Restoration', 'color' => 'emerald'],
            'healthy' => ['label' => 'Healthy / Sound', 'color' => 'gray'],
            'custom' => ['label' => 'Custom Finding', 'color' => 'indigo'],
        ];

        // 1. Patient Teeth Conditions (from patient_teeth table - fresh DB query)
        $patientTeeth = PatientTooth::where('patient_id', $patientId)->get();
        foreach ($patientTeeth as $pt) {
            $tooth = (string) $pt->tooth_number;
            if (!$tooth) continue;

            $condKey = strtolower(trim($pt->condition ?: ''));
            $condInfo = $conditionLabels[$condKey] ?? ['label' => ucwords(str_replace('_', ' ', $condKey)), 'color' => 'indigo'];

            $teeth[$tooth] = [
                'tooth' => $tooth,
                'condition' => $condInfo['label'],
                'condition_raw' => $condKey,
                'condition_color' => $condInfo['color'],
                'notes' => $pt->notes,
                'surfaces' => $pt->surfaces ?: [],
                'inside_count' => 0,
                'outside_count' => 0,
                'condition_count' => 0,
                'items' => [],
            ];

            if ($condKey && $condKey !== 'healthy') {
                $teeth[$tooth]['condition_count']++;
                $teeth[$tooth]['items'][] = [
                    'id' => $pt->id,
                    'source' => 'charting',
                    'type' => 'condition',
                    'title' => 'Documented Condition: ' . $condInfo['label'],
                    'status' => '3D Odontogram',
                    'doctor' => 'Clinical Charting',
                    'date' => $pt->updated_at ? Carbon::parse($pt->updated_at)->format('M Y') : null,
                    'notes' => $pt->notes,
                ];
            }
        }

        // 2. Dental Examination Findings (ToothFinding table - fresh DB query)
        $findings = ToothFinding::with(['examination.doctor:id,name', 'surfaceFindings'])
            ->whereHas('examination', fn ($q) => $q->where('patient_id', $patientId))
            ->latest('updated_at')
            ->get();

        foreach ($findings as $finding) {
            $tooth = (string) $finding->tooth_number_fdi;
            if (!$tooth) continue;

            $condKey = strtolower(trim($finding->finding_type ?: ''));
            $condInfo = $conditionLabels[$condKey] ?? ['label' => ucwords(str_replace('_', ' ', $condKey)), 'color' => 'indigo'];

            if (!isset($teeth[$tooth])) {
                $teeth[$tooth] = [
                    'tooth' => $tooth,
                    'condition' => $condInfo['label'],
                    'condition_raw' => $condKey,
                    'condition_color' => $condInfo['color'],
                    'notes' => $finding->notes,
                    'surfaces' => $finding->surfaceFindings->pluck('surface')->toArray(),
                    'inside_count' => 0,
                    'outside_count' => 0,
                    'condition_count' => 0,
                    'items' => [],
                ];
            } else {
                if (empty($teeth[$tooth]['condition']) || $teeth[$tooth]['condition_raw'] === 'healthy' || ($condKey && $condKey !== 'healthy')) {
                    $teeth[$tooth]['condition'] = $condInfo['label'];
                    $teeth[$tooth]['condition_raw'] = $condKey;
                    $teeth[$tooth]['condition_color'] = $condInfo['color'];
                }
                if ($finding->notes && empty($teeth[$tooth]['notes'])) {
                    $teeth[$tooth]['notes'] = $finding->notes;
                }
            }

            if ($condKey && $condKey !== 'healthy') {
                $teeth[$tooth]['condition_count']++;
                $examDoctor = $finding->examination?->doctor?->name ?: 'Examining Doctor';
                $examDate = $finding->examination?->examined_at ? Carbon::parse($finding->examination->examined_at)->format('M Y') : null;
                $surfaceNames = $finding->surfaceFindings->pluck('surface')->implode(', ');

                $teeth[$tooth]['items'][] = [
                    'id' => $finding->id,
                    'source' => 'charting',
                    'type' => 'condition',
                    'title' => 'Clinical Finding: ' . $condInfo['label'] . ($surfaceNames ? " ({$surfaceNames})" : ''),
                    'status' => '3D Exam',
                    'doctor' => $examDoctor,
                    'date' => $examDate,
                    'notes' => $finding->notes,
                ];
            }
        }

        // 3. Internal procedures with a tooth
        $procedures = TreatmentProcedure::with(['procedureCode', 'doctor:id,name', 'phase.plan'])
            ->whereHas('phase.plan', fn ($q) => $q->where('patient_id', $patientId))
            ->whereNotNull('tooth_number_fdi')
            ->get();

        foreach ($procedures as $proc) {
            $tooth = (string) $proc->tooth_number_fdi;
            $procTitle = $proc->procedureCode?->title ?: 'Clinical Treatment';
            $procCode = strtoupper($proc->procedureCode?->code ?: '');
            $titleLower = strtolower($procTitle);

            // Detect condition from procedure (crown, implant, root canal, etc.)
            $detectedCond = null;
            if (str_contains($titleLower, 'crown') || str_contains($procCode, 'D2740') || str_contains($titleLower, 'cap') || str_contains($titleLower, 'bridge')) {
                $detectedCond = 'crown';
            } elseif (str_contains($titleLower, 'implant') || str_contains($procCode, 'D6010')) {
                $detectedCond = 'implant';
            } elseif (str_contains($titleLower, 'root canal') || str_contains($titleLower, 'endo') || str_contains($procCode, 'D3310')) {
                $detectedCond = 'root_canal';
            } elseif (str_contains($titleLower, 'composite') || str_contains($titleLower, 'fill') || str_contains($procCode, 'D2330')) {
                $detectedCond = 'composite_filled';
            } elseif (str_contains($titleLower, 'extract') || str_contains($procCode, 'D7140')) {
                $detectedCond = 'missing';
            }

            $condInfo = $detectedCond ? ($conditionLabels[$detectedCond] ?? null) : null;

            if (!isset($teeth[$tooth])) {
                $teeth[$tooth] = [
                    'tooth' => $tooth,
                    'condition' => $condInfo['label'] ?? 'Treated Tooth',
                    'condition_raw' => $detectedCond ?? 'treated',
                    'condition_color' => $condInfo['color'] ?? 'emerald',
                    'notes' => null,
                    'surfaces' => [],
                    'inside_count' => 0,
                    'outside_count' => 0,
                    'condition_count' => 0,
                    'items' => [],
                ];
            } else {
                if ($detectedCond && ($teeth[$tooth]['condition_raw'] === 'healthy' || $teeth[$tooth]['condition_raw'] === 'treated')) {
                    $teeth[$tooth]['condition'] = $condInfo['label'];
                    $teeth[$tooth]['condition_raw'] = $detectedCond;
                    $teeth[$tooth]['condition_color'] = $condInfo['color'];
                }
            }
            $teeth[$tooth]['inside_count']++;
            $teeth[$tooth]['items'][] = [
                'id' => $proc->id,
                'source' => 'inside',
                'type' => 'procedure',
                'title' => $procTitle,
                'status' => ucfirst($proc->status ?: 'Completed'),
                'doctor' => $proc->doctor?->name ?: 'Clinic Doctor',
                'date' => $proc->completed_at ? Carbon::parse($proc->completed_at)->format('M Y') : ($proc->updated_at ? $proc->updated_at->format('M Y') : null),
            ];
        }

        // 4. External treatments with a tooth
        $external = ExternalTreatment::where('patient_id', $patientId)
            ->whereNotNull('tooth_number_fdi')
            ->get();

        foreach ($external as $ext) {
            $tooth = (string) $ext->tooth_number_fdi;
            $titleLower = strtolower($ext->procedure_title);

            $detectedCond = null;
            if (str_contains($titleLower, 'crown') || str_contains($titleLower, 'cap') || str_contains($titleLower, 'bridge')) $detectedCond = 'crown';
            elseif (str_contains($titleLower, 'implant')) $detectedCond = 'implant';
            elseif (str_contains($titleLower, 'root canal') || str_contains($titleLower, 'rct') || str_contains($titleLower, 'endo')) $detectedCond = 'root_canal';
            elseif (str_contains($titleLower, 'fill') || str_contains($titleLower, 'composite')) $detectedCond = 'composite_filled';
            elseif (str_contains($titleLower, 'missing') || str_contains($titleLower, 'extract')) $detectedCond = 'missing';

            $condInfo = $detectedCond ? ($conditionLabels[$detectedCond] ?? null) : null;

            if (!isset($teeth[$tooth])) {
                $teeth[$tooth] = [
                    'tooth' => $tooth,
                    'condition' => $condInfo['label'] ?? 'Prior Work',
                    'condition_raw' => $detectedCond ?? 'prior',
                    'condition_color' => $condInfo['color'] ?? 'indigo',
                    'notes' => null,
                    'surfaces' => [],
                    'inside_count' => 0,
                    'outside_count' => 0,
                    'condition_count' => 0,
                    'items' => [],
                ];
            } else {
                if ($detectedCond && ($teeth[$tooth]['condition_raw'] === 'healthy' || $teeth[$tooth]['condition_raw'] === 'prior')) {
                    $teeth[$tooth]['condition'] = $condInfo['label'];
                    $teeth[$tooth]['condition_raw'] = $detectedCond;
                    $teeth[$tooth]['condition_color'] = $condInfo['color'];
                }
            }
            $teeth[$tooth]['outside_count']++;
            $teeth[$tooth]['items'][] = [
                'id' => $ext->id,
                'source' => 'outside',
                'type' => 'external',
                'title' => $ext->procedure_title,
                'status' => 'External / Prior',
                'doctor' => $ext->clinic_or_doctor_name ?: 'Outside Clinic',
                'date' => $ext->performed_year,
            ];
        }

        // Sort by tooth number numerically
        ksort($teeth, SORT_NUMERIC);

        return $teeth;
    }

    /**
     * Get External Treatments.
     */
    public function getExternalTreatments(): \Illuminate\Database\Eloquent\Collection
    {
        return ExternalTreatment::with('creator:id,name')
            ->where('patient_id', $this->record?->id)
            ->latest()
            ->get();
    }

    /**
     * Get Raw (Unfiltered) Timeline Items with unique keys and normalized data.
     */
    public function getRawTimelineItems(): array
    {
        $patientId = $this->record?->id;
        if (!$patientId) return [];

        $timeline = [];

        $conditionLabels = [
            'missing' => 'Missing Tooth Charted',
            'crown' => 'Crown Documented',
            'implant' => 'Implant Documented',
            'root_canal' => 'Root Canal Treated',
            'active_caries' => 'Active Caries Charted',
            'composite_filled' => 'Composite Filling Documented',
            'restoration' => 'Restoration Documented',
            'custom' => 'Custom Finding Charted',
        ];

        // 1. Inside Clinic Procedures & Surgical Operations
        $procedures = TreatmentProcedure::with(['procedureCode', 'doctor:id,name', 'phase.plan'])
            ->whereHas('phase.plan', fn ($q) => $q->where('patient_id', $patientId))
            ->get();

        foreach ($procedures as $proc) {
            $date = $proc->completed_at ?: ($proc->updated_at ?: $proc->created_at);
            $toothPrefix = $proc->tooth_number_fdi ? "Tooth #{$proc->tooth_number_fdi}: " : '';
            $procTitle = $proc->procedureCode?->title ?: 'Clinical Treatment';

            $timeline[] = [
                'key' => "proc-{$proc->id}",
                'id' => $proc->id,
                'source' => 'inside',
                'type' => 'procedure',
                'tooth_number_fdi' => $proc->tooth_number_fdi,
                'date' => $date ? Carbon::parse($date) : now(),
                'title' => $toothPrefix . $procTitle,
                'doctor' => $proc->doctor?->name ?: 'Treating Doctor',
                'status' => ucfirst($proc->status ?: 'Completed'),
                'details' => [
                    'anesthetic' => $proc->anesthetic_agent ? "{$proc->anesthetic_agent} ({$proc->anesthetic_dosage}) - {$proc->anesthetic_technique}" : null,
                    'materials' => $proc->materials_used_json,
                    'tools' => $proc->tools_used_json,
                    'hemostasis' => $proc->hemostasis_achieved ? 'Hemostasis Achieved' : null,
                    'notes' => $proc->chairside_notes ?: $proc->notes,
                ],
            ];
        }

        // 2. Outside Clinic Treatments
        $externalTreatments = ExternalTreatment::with('creator:id,name')
            ->where('patient_id', $patientId)
            ->latest()
            ->get();

        foreach ($externalTreatments as $ext) {
            $year = is_numeric($ext->performed_year) ? (int) $ext->performed_year : null;
            $date = $year ? Carbon::createFromDate($year, 6, 1) : $ext->created_at;

            $timeline[] = [
                'key' => "ext-{$ext->id}",
                'id' => $ext->id,
                'source' => 'outside',
                'type' => 'external',
                'tooth_number_fdi' => $ext->tooth_number_fdi,
                'date' => $date,
                'display_date' => $ext->performed_year ?: $ext->created_at->format('M Y'),
                'title' => ($ext->tooth_number_fdi ? "Tooth #{$ext->tooth_number_fdi}: " : '') . $ext->procedure_title,
                'doctor' => $ext->clinic_or_doctor_name ?: 'Outside Clinic',
                'status' => 'Outside Treatment',
                'details' => [
                    'notes' => $ext->notes,
                    'logged_by' => $ext->creator?->name ?: 'Staff',
                ],
            ];
        }

        // 3. Prescriptions
        $prescriptions = Prescription::with('doctor:id,name')
            ->where('patient_id', $patientId)
            ->latest('created_at')
            ->get();

        foreach ($prescriptions as $rx) {
            $date = $rx->date_prescribed ?: $rx->created_at;
            $timeline[] = [
                'key' => "rx-{$rx->id}",
                'id' => $rx->id,
                'source' => 'inside',
                'type' => 'prescription',
                'tooth_number_fdi' => null,
                'date' => Carbon::parse($date),
                'title' => 'Prescription: ' . $rx->medication_name . ($rx->dosage ? " ({$rx->dosage})" : ''),
                'doctor' => $rx->doctor?->name ?: 'Prescribing Doctor',
                'status' => ucfirst($rx->status ?: 'Active'),
                'details' => [
                    'medication_name' => $rx->medication_name,
                    'dosage' => $rx->dosage,
                    'frequency' => $rx->frequency,
                    'duration' => $rx->duration,
                    'instructions' => $rx->instructions,
                    'notes' => $rx->instructions,
                ],
            ];
        }

        // 4. Appointments / Clinic Visits
        $appointments = Appointment::with(['doctor:id,name', 'operatory:id,name'])
            ->where('patient_id', $patientId)
            ->latest('start_time')
            ->take(30)
            ->get();

        foreach ($appointments as $apt) {
            $visitTitle = 'Clinic Visit: ' . ucwords(str_replace('_', ' ', $apt->type ?: 'Consultation'));
            if ($apt->procedure_name) {
                $visitTitle .= " ({$apt->procedure_name})";
            }

            $timeline[] = [
                'key' => "apt-{$apt->id}",
                'id' => $apt->id,
                'source' => 'inside',
                'type' => 'appointment',
                'tooth_number_fdi' => $apt->tooth_number,
                'date' => Carbon::parse($apt->start_time ?: $apt->created_at),
                'title' => $visitTitle,
                'doctor' => $apt->doctor?->name ?: 'Doctor',
                'status' => ucwords(str_replace('_', ' ', $apt->status ?: 'Booked')),
                'details' => [
                    'operatory' => $apt->operatory?->name,
                    'chief_complaint' => $apt->chief_complaint,
                    'notes' => $apt->notes,
                    'procedure_name' => $apt->procedure_name,
                ],
            ];
        }

        // 5. 3D Model Tooth Findings & Anatomical Conditions Charted
        $findings = ToothFinding::with(['examination.doctor:id,name', 'surfaceFindings'])
            ->whereHas('examination', fn ($q) => $q->where('patient_id', $patientId))
            ->latest('updated_at')
            ->get();

        foreach ($findings as $finding) {
            $cond = strtolower(trim($finding->finding_type ?: ''));
            if (!$cond || $cond === 'healthy') continue;

            $condLabel = $conditionLabels[$cond] ?? ucwords(str_replace('_', ' ', $cond));
            $surfaces = $finding->surfaceFindings->pluck('surface')->implode(', ');
            $date = $finding->examination?->examined_at ?: ($finding->updated_at ?: $finding->created_at);

            $timeline[] = [
                'key' => "finding-{$finding->id}",
                'id' => $finding->id,
                'source' => 'inside',
                'type' => 'condition',
                'tooth_number_fdi' => $finding->tooth_number_fdi,
                'date' => Carbon::parse($date),
                'title' => "Tooth #{$finding->tooth_number_fdi}: {$condLabel}" . ($surfaces ? " ({$surfaces})" : ''),
                'doctor' => $finding->examination?->doctor?->name ?: '3D Odontogram',
                'status' => '3D Model Finding',
                'details' => [
                    'condition' => $condLabel,
                    'surfaces' => $surfaces,
                    'notes' => $finding->notes,
                ],
            ];
        }

        // Include any patient_teeth conditions (direct fresh DB query)
        $patientTeeth = PatientTooth::where('patient_id', $patientId)->get();
        $chartedTeethConditions = [];
        foreach ($findings as $f) {
            $chartedTeethConditions[] = ((string)$f->tooth_number_fdi) . ':' . strtolower(trim($f->finding_type ?: ''));
        }

        foreach ($patientTeeth as $pt) {
            $cond = strtolower(trim($pt->condition ?: ''));
            if (!$cond || $cond === 'healthy') continue;

            // Only add if not identical condition already added from tooth_findings
            $keyCheck = ((string)$pt->tooth_number) . ':' . $cond;
            if (in_array($keyCheck, $chartedTeethConditions, true)) continue;

            $condLabel = $conditionLabels[$cond] ?? ucwords(str_replace('_', ' ', $cond));
            $timeline[] = [
                'key' => "pt-{$pt->id}",
                'id' => $pt->id,
                'source' => 'inside',
                'type' => 'condition',
                'tooth_number_fdi' => $pt->tooth_number,
                'date' => Carbon::parse($pt->updated_at ?: $pt->created_at),
                'title' => "Tooth #{$pt->tooth_number}: {$condLabel}",
                'doctor' => '3D Odontogram',
                'status' => 'Documented Condition',
                'details' => [
                    'condition' => $condLabel,
                    'notes' => $pt->notes,
                ],
            ];
        }

        // 6. Voice Notes Sent via WhatsApp
        $voiceNotes = VoiceNote::with('user:id,name')
            ->where('patient_id', $patientId)
            ->latest()
            ->get();

        foreach ($voiceNotes as $vn) {
            $timeline[] = [
                'key' => "vn-{$vn->id}",
                'id' => $vn->id,
                'source' => 'inside',
                'type' => 'voice_note',
                'tooth_number_fdi' => $vn->tooth_number_fdi,
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

        return $timeline;
    }

    /**
     * Get statistics count for each timeline type for the UI filter badges.
     */
    public function getTimelineFilterCounts(): array
    {
        $raw = $this->getRawTimelineItems();
        return [
            'all' => count($raw),
            'procedure' => count(array_filter($raw, fn($i) => ($i['type'] ?? '') === 'procedure')),
            'appointment' => count(array_filter($raw, fn($i) => ($i['type'] ?? '') === 'appointment')),
            'condition' => count(array_filter($raw, fn($i) => ($i['type'] ?? '') === 'condition')),
            'external' => count(array_filter($raw, fn($i) => ($i['type'] ?? '') === 'external')),
            'prescription' => count(array_filter($raw, fn($i) => ($i['type'] ?? '') === 'prescription')),
            'voice_note' => count(array_filter($raw, fn($i) => ($i['type'] ?? '') === 'voice_note')),
        ];
    }

    /**
     * Get unique teeth numbers present in the timeline for filter dropdown.
     */
    public function getAvailableTimelineTeeth(): array
    {
        $raw = $this->getRawTimelineItems();
        $teeth = [];
        foreach ($raw as $item) {
            if (!empty($item['tooth_number_fdi'])) {
                $teeth[] = (string) $item['tooth_number_fdi'];
            }
        }
        $teeth = array_unique($teeth);
        sort($teeth, SORT_NUMERIC);
        return $teeth;
    }

    /**
     * Get unique doctor names present in the timeline for filter dropdown.
     */
    public function getAvailableTimelineDoctors(): array
    {
        $raw = $this->getRawTimelineItems();
        $doctors = [];
        foreach ($raw as $item) {
            $doc = trim($item['doctor'] ?? '');
            if ($doc && !in_array($doc, ['Doctor', 'Staff', '3D Odontogram', 'Clinical Charting'])) {
                $doctors[] = $doc;
            }
        }
        $doctors = array_unique($doctors);
        sort($doctors);
        return $doctors;
    }

    /**
     * Get Unified Journey Timeline with all active filters and sorting applied.
     */
    public function getClinicJourneyTimeline(): array
    {
        $timeline = $this->getRawTimelineItems();

        // 1. Filter by source (all, internal, external)
        if ($this->historyFilter === 'internal') {
            $timeline = array_filter($timeline, fn ($item) => ($item['source'] ?? '') === 'inside');
        } elseif ($this->historyFilter === 'external') {
            $timeline = array_filter($timeline, fn ($item) => ($item['source'] ?? '') === 'outside');
        }

        // 2. Filter by type (all, procedure, appointment, condition, external, prescription, voice_note)
        if ($this->typeFilter !== 'all') {
            $timeline = array_filter($timeline, fn ($item) => ($item['type'] ?? '') === $this->typeFilter);
        }

        // 3. Filter by tooth (all, general, specific tooth #)
        if ($this->toothFilter !== 'all') {
            if ($this->toothFilter === 'general') {
                $timeline = array_filter($timeline, fn ($item) => empty($item['tooth_number_fdi']));
            } else {
                $timeline = array_filter($timeline, fn ($item) => (string)($item['tooth_number_fdi'] ?? '') === (string)$this->toothFilter);
            }
        }

        // 4. Filter by doctor
        if ($this->doctorFilter !== 'all') {
            $timeline = array_filter($timeline, fn ($item) => ($item['doctor'] ?? '') === $this->doctorFilter);
        }

        // 5. Instant Search Query
        if (!empty(trim($this->searchQuery))) {
            $q = mb_strtolower(trim($this->searchQuery));
            $timeline = array_filter($timeline, function ($item) use ($q) {
                $searchable = ($item['title'] ?? '') . ' ' .
                              ($item['doctor'] ?? '') . ' ' .
                              ($item['status'] ?? '') . ' ' .
                              ($item['tooth_number_fdi'] ? "#{$item['tooth_number_fdi']} Tooth" : '') . ' ' .
                              json_encode($item['details'] ?? []);
                return str_contains(mb_strtolower($searchable), $q);
            });
        }

        // 6. Sort timeline by date
        if ($this->sortOrder === 'asc') {
            usort($timeline, fn ($a, $b) => $a['date'] <=> $b['date']);
        } else {
            usort($timeline, fn ($a, $b) => $b['date'] <=> $a['date']);
        }

        return array_values($timeline);
    }

    /**
     * Get All Treatment Plans (Active, Completed, Proposed).
     */
    public function getAllTreatmentPlans(): \Illuminate\Database\Eloquent\Collection
    {
        return TreatmentPlan::with(['phases.procedures.procedureCode', 'doctor:id,name'])
            ->where('patient_id', $this->record?->id)
            ->latest()
            ->get();
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
            ->where('start_time', '>=', now())
            ->whereIn('status', ['booked', 'scheduled', 'confirmed'])
            ->orderBy('start_time', 'asc')
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
