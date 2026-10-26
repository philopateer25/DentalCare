import React, { useState } from 'react';
import axios from 'axios';
import Dental3DViewer, { ArchMode, CameraPreset } from '../../Components/Dental3D/Dental3DViewer';
import { SingleToothViewer } from '../../Components/Dental3D/SingleToothViewer';
import VoiceNoteModal from '../../Components/Dental3D/VoiceNoteModal';
import { Mic, Eye, EyeOff, Crosshair, Camera } from 'lucide-react';
import {
  CONDITION_COLORS,
  CONDITION_LABELS,
  PatientInfo,
  ToothRecord,
  SurfaceCondition,
  ToothSurfaces,
} from '../../Components/Dental3D/types';
import { ToothSurfaceKey } from '../../Components/Dental3D/dentitionManifest';

interface Props {
  patient: PatientInfo;
  examination: { id: number; type: string; examined_at: string; notes: string | null };
  initialRecords?: Record<string, ToothRecord>;
  initialProcedures?: Record<string, any[]>;
  initialViewMode?: 'clean' | 'detailed';
}

export interface MedicalDetails {
  diagnosis: string;
  severity: string;
  urgency: string;
  mobility: string;
}

export const parseMedicalDetails = (rawNotes?: string): MedicalDetails => {
  const defaults: MedicalDetails = {
    diagnosis: '',
    severity: '',
    urgency: '',
    mobility: '',
  };
  if (!rawNotes) return defaults;

  try {
    const parsed = JSON.parse(rawNotes);
    if (typeof parsed === 'object' && parsed !== null) {
      return {
        diagnosis: parsed.diagnosis || '',
        severity: parsed.severity || '',
        urgency: parsed.urgency || '',
        mobility: parsed.mobility || '',
      };
    }
  } catch {}

  const dxMatch = rawNotes.match(/\[Dx:\s*([^\]]+)\]/i);
  const sevMatch = rawNotes.match(/\[Severity:\s*([^\]]+)\]/i);
  const urgMatch = rawNotes.match(/\[Urgency:\s*([^\]]+)\]/i);
  const mobMatch = rawNotes.match(/\[Mobility:\s*([^\]]+)\]/i);

  if (dxMatch || sevMatch || urgMatch || mobMatch) {
    return {
      diagnosis: dxMatch ? dxMatch[1].trim() : '',
      severity: sevMatch ? sevMatch[1].trim() : '',
      urgency: urgMatch ? urgMatch[1].trim() : '',
      mobility: mobMatch ? mobMatch[1].trim() : '',
    };
  }

  return { ...defaults, diagnosis: rawNotes.trim() };
};

export const formatMedicalDetails = (details: MedicalDetails): string => {
  const parts: string[] = [];
  if (details.diagnosis) parts.push(`[Dx: ${details.diagnosis}]`);
  if (details.severity) parts.push(`[Severity: ${details.severity}]`);
  if (details.urgency) parts.push(`[Urgency: ${details.urgency}]`);
  if (details.mobility) parts.push(`[Mobility: ${details.mobility}]`);
  return parts.join(' ');
};

export const DIAGNOSIS_OPTIONS = [
  { value: '', label: 'Select Clinical Diagnosis...' },
  { value: 'Reversible Pulpitis', label: 'Reversible Pulpitis (Sensitivity to Cold/Sweet)' },
  { value: 'Irreversible Pulpitis', label: 'Irreversible Pulpitis (Spontaneous Throbbing)' },
  { value: 'Pulpal Necrosis', label: 'Pulpal Necrosis (Non-Vital Pulp)' },
  { value: 'Acute Apical Abscess', label: 'Acute Apical Abscess (Localized Swelling)' },
  { value: 'Chronic Apical Periodontitis', label: 'Chronic Apical Periodontitis (Radiolucency)' },
  { value: 'Enamel Fracture', label: 'Enamel Fracture / Chipped Incisal Edge' },
  { value: 'Dentin Fracture', label: 'Uncomplicated Crown Fracture (Dentin)' },
  { value: 'Crown-Root Fracture', label: 'Complicated Crown-Root Fracture' },
  { value: 'Incipient Caries', label: 'Incipient Enamel Demineralization (White Spot)' },
  { value: 'Gingival Recession', label: 'Cervical Gingival Recession' },
  { value: 'Deep Periodontal Pocket', label: 'Periodontitis (Pocket > 5mm)' },
  { value: 'Food Impaction', label: 'Interproximal Open Contact / Food Impaction' },
  { value: 'Pathological Mobility', label: 'Tooth Mobility / Trauma From Occlusion' },
  { value: 'Severe Bruxism / Attrition', label: 'Severe Attrition / Bruxism Facet' },
  { value: 'Cervical Abfraction', label: 'Cervical Abfraction / Wedge Defect' },
  { value: 'Acid Erosion', label: 'Enamel Acid Erosion (GERD / Bulimia)' },
  { value: 'Partially Impacted', label: 'Partially Impacted / Pericoronitis' },
];

export const SEVERITY_OPTIONS = [
  { value: '', label: 'Select Severity / Depth...' },
  { value: 'Mild / Initial', label: 'Mild (Enamel / Reversible)' },
  { value: 'Moderate / Localized', label: 'Moderate (Dentin Involvement)' },
  { value: 'Severe / Deep', label: 'Severe (Near Pulp / Deep Pocket)' },
  { value: 'Critical / Acute', label: 'Critical (Pulp Exposure / Acute Abscess)' },
];

export const URGENCY_OPTIONS = [
  { value: '', label: 'Select Treatment Priority...' },
  { value: 'Routine', label: 'Routine (Elective / Next Recall)' },
  { value: 'Priority', label: 'Priority (Schedule Within 1-2 Weeks)' },
  { value: 'Urgent / Emergency', label: 'Urgent (Emergency Same-Day)' },
  { value: 'Observation', label: 'Observation (Monitor Next Visit)' },
];

export const MOBILITY_OPTIONS = [
  { value: '', label: 'Select Mobility Grade...' },
  { value: 'Grade 0 (Physiologic)', label: 'Grade 0: Physiologic Mobility (Normal)' },
  { value: 'Grade I (< 1mm)', label: 'Grade I: Slight Mobility (< 1mm Horizontal)' },
  { value: 'Grade II (> 1mm)', label: 'Grade II: Moderate Mobility (> 1mm Horizontal)' },
  { value: 'Grade III (Vertical)', label: 'Grade III: Severe Horizontal & Vertical Mobility' },
];

export const PLAN_TEMPLATES = [
  'Comprehensive Oral Rehabilitation',
  'Restorative & Esthetic Smile Design',
  'Endodontic Therapy & Core Buildup',
  'Periodontal Scaling & Maintenance',
  'Prosthodontic Crown & Bridge Plan',
  'Oral Surgery & Implant Protocol',
  'Orthodontic Alignment Plan',
  'Emergency Palliative Protocol',
];

export const PROCEDURE_CATEGORIES = [
  {
    category: 'Restorative',
    procedures: [
      { code: 'D2330', name: 'Composite - 1 Surface, Anterior ($120)' },
      { code: 'D2331', name: 'Composite - 2 Surfaces, Anterior ($160)' },
      { code: 'D2391', name: 'Composite - 1 Surface, Posterior ($140)' },
      { code: 'D2392', name: 'Composite - 2 Surfaces, Posterior ($190)' },
      { code: 'D2393', name: 'Composite - 3+ Surfaces, Posterior ($240)' },
      { code: 'D2950', name: 'Core Buildup, Including Any Pins ($180)' },
    ],
  },
  {
    category: 'Endodontics',
    procedures: [
      { code: 'D3310', name: 'Root Canal Therapy - Anterior ($450)' },
      { code: 'D3320', name: 'Root Canal Therapy - Premolar ($600)' },
      { code: 'D3330', name: 'Root Canal Therapy - Molar ($850)' },
      { code: 'D3410', name: 'Apicoectomy - Anterior ($400)' },
    ],
  },
  {
    category: 'Prosthodontics & Implants',
    procedures: [
      { code: 'D2740', name: 'Crown - Porcelain / Ceramic (Zirconia) ($750)' },
      { code: 'D2750', name: 'Crown - Porcelain Fused to High Noble ($650)' },
      { code: 'D6010', name: 'Surgical Placement of Implant Body ($1,200)' },
      { code: 'D6058', name: 'Abutment Supported Ceramic Crown ($850)' },
    ],
  },
  {
    category: 'Oral Surgery & Periodontics',
    procedures: [
      { code: 'D7140', name: 'Extraction, Erupted Tooth ($110)' },
      { code: 'D7210', name: 'Surgical Removal of Erupted Tooth ($220)' },
      { code: 'D4341', name: 'Periodontal Scaling & Root Planing ($150)' },
      { code: 'D1351', name: 'Pit & Fissure Sealant ($45)' },
    ],
  },
];

export const ANESTHESIA_OPTIONS = [
  { value: '', label: 'Select Anesthesia (Optional)...' },
  { value: 'Lidocaine 2% with 1:100,000 Epinephrine', label: 'Lidocaine 2% with 1:100k Epi (Standard)' },
  { value: 'Articaine 4% with 1:100,000 Epinephrine', label: 'Articaine 4% with 1:100k Epi (Septocaine)' },
  { value: 'Mepivacaine 3% Plain (No Epinephrine)', label: 'Mepivacaine 3% Plain (Cardiac Safe)' },
  { value: 'Bupivacaine 0.5% with 1:200,000 Epinephrine', label: 'Bupivacaine 0.5% with 1:200k Epi (Long Acting)' },
];

export const DOSAGE_OPTIONS = [
  { value: '', label: 'Select Dosage...' },
  { value: '0.5 carpule (0.9 mL)', label: '0.5 carpule (0.9 mL)' },
  { value: '1.0 carpule (1.8 mL)', label: '1.0 carpule (1.8 mL)' },
  { value: '1.5 carpules (2.7 mL)', label: '1.5 carpules (2.7 mL)' },
  { value: '2.0 carpules (3.6 mL)', label: '2.0 carpules (3.6 mL)' },
];

export const TECHNIQUE_OPTIONS = [
  { value: '', label: 'Select Injection Technique...' },
  { value: 'Local Infiltration', label: 'Local Infiltration (Buccal/Palatal)' },
  { value: 'Inferior Alveolar Nerve Block', label: 'Inferior Alveolar Nerve Block (IANB)' },
  { value: 'Mental / Incisive Nerve Block', label: 'Mental / Incisive Nerve Block' },
  { value: 'Greater Palatine Block', label: 'Greater Palatine Nerve Block' },
  { value: 'Intraligamentary (PDL)', label: 'Intraligamentary / PDL Injection' },
];

export const MATERIAL_OPTIONS = [
  { value: '', label: 'Select Dental Material (Optional)...' },
  { value: 'Composite Nano-Hybrid (VITA A2)', label: 'Composite Nano-Hybrid (VITA A2)' },
  { value: 'Glass Ionomer Cement (GIC)', label: 'Glass Ionomer Cement (GIC)' },
  { value: 'High-Translucency Zirconia', label: 'High-Translucency Zirconia' },
  { value: 'IPS E-Max Lithium Disilicate', label: 'IPS E-Max Lithium Disilicate' },
  { value: 'Gutta-Percha & Bio-Ceramic Sealer', label: 'Gutta-Percha & Bio-Ceramic Sealer' },
  { value: 'Mineral Trioxide Aggregate (MTA)', label: 'Mineral Trioxide Aggregate (MTA)' },
  { value: 'Dental Amalgam Alloy', label: 'Dental Amalgam Alloy' },
];

// FDI Teeth list in anatomical order
const FDI_TEETH = {
  upperRight: ['18', '17', '16', '15', '14', '13', '12', '11'],
  upperLeft: ['21', '22', '23', '24', '25', '26', '27', '28'],
  lowerRight: ['48', '47', '46', '45', '44', '43', '42', '41'],
  lowerLeft: ['31', '32', '33', '34', '35', '36', '37', '38'],
};

class TopErrorBoundary extends React.Component<
  { children: React.ReactNode },
  { hasError: boolean; error: Error | null }
> {
  constructor(props: { children: React.ReactNode }) {
    super(props);
    this.state = { hasError: false, error: null };
  }

  static getDerivedStateFromError(error: Error) {
    return { hasError: true, error };
  }

  render() {
    if (this.state.hasError) {
      return (
        <div style={{ padding: 20, color: 'red', backgroundColor: '#fff' }}>
          <h1>React Crashed!</h1>
          <pre>{this.state.error?.toString()}</pre>
          <pre>{this.state.error?.stack}</pre>
        </div>
      );
    }
    return this.props.children;
  }
}

export const Test3DOdontogram: React.FC<Props> = (props) => {
  return (
    <TopErrorBoundary>
      <Test3DOdontogramInner {...props} />
    </TopErrorBoundary>
  );
};

const Test3DOdontogramInner: React.FC<Props> = ({
  patient,
  examination: initialExam,
  initialRecords = {},
  initialProcedures = {},
  initialViewMode = 'clean',
}) => {
  const [teethRecords, setTeethRecords] = useState<Record<string, ToothRecord>>(initialRecords);
  const [selectedTooth, setSelectedTooth] = useState<string | null>(() => {
    if (typeof window !== 'undefined') {
      const p = new URLSearchParams(window.location.search).get('tooth');
      if (p) return p;
    }
    return '11';
  });
  const [savingStatus, setSavingStatus] = useState<'idle' | 'saving' | 'saved' | 'error'>('idle');
  const [viewMode, setViewMode] = useState<'clean' | 'detailed'>(initialViewMode);
  
  const [activeExam, setActiveExam] = useState(initialExam);
  const [examinations, setExaminations] = useState<any[]>([initialExam]);

  const [mode, setMode] = useState<'findings' | 'treatment'>('findings');
  const [activePlan, setActivePlan] = useState<any>(null);
  const [allPlans, setAllPlans] = useState<any[]>([]);
  const [plannedProcedures, setPlannedProcedures] = useState<Record<string, any[]>>(initialProcedures);
  const [isCreatingPlan, setIsCreatingPlan] = useState(false);
  const [newPlanTitle, setNewPlanTitle] = useState(PLAN_TEMPLATES[0]);
  const [selectedProcCode, setSelectedProcCode] = useState<string>('D2330');
  const [selectedAnesthesia, setSelectedAnesthesia] = useState<string>('');
  const [selectedDosage, setSelectedDosage] = useState<string>('');
  const [selectedTechnique, setSelectedTechnique] = useState<string>('');
  const [selectedMaterial, setSelectedMaterial] = useState<string>('');

  // Clinical Control States
  const [showGums, setShowGums] = useState(true);
  const [isolateTooth, setIsolateTooth] = useState<boolean>(() => {
    if (typeof window !== 'undefined') {
      return new URLSearchParams(window.location.search).get('isolate') === '1';
    }
    return false;
  });
  const [archMode, setArchMode] = useState<ArchMode>('both');
  const [cameraPreset, setCameraPreset] = useState<CameraPreset>('default');

  // Post-Op Voice Note Modal State
  const [voiceNoteModalOpen, setVoiceNoteModalOpen] = useState(false);
  const [voiceNoteTooth, setVoiceNoteTooth] = useState<string | null>(null);
  const [voiceNoteProcedure, setVoiceNoteProcedure] = useState<any | null>(null);

  const handleOpenVoiceNote = (toothNum: string, proc?: any) => {
    setVoiceNoteTooth(toothNum);
    setVoiceNoteProcedure(proc || (plannedProcedures[toothNum] ? plannedProcedures[toothNum][0] : null));
    setVoiceNoteModalOpen(true);
  };

  React.useEffect(() => {
    // Fetch historical examinations
    axios.get(`/api/patients/${patient.id}/examinations`).then(res => {
      setExaminations(res.data);
    });

    // Fetch all treatment plans
    axios.get(`/api/patients/${patient.id}/treatment-plans`).then(res => {
      setAllPlans(res.data);
      if (res.data.length > 0) {
        setActivePlan(res.data[0]);
        loadProceduresFromPlan(res.data[0]);
      } else {
        // Fallback to active if none found, which auto-creates one
        axios.get(`/api/patients/${patient.id}/treatment-plans/active`).then(activeRes => {
          setAllPlans([activeRes.data]);
          setActivePlan(activeRes.data);
          loadProceduresFromPlan(activeRes.data);
        });
      }
    });
  }, [patient.id]);

  const notifyParentWindow = (tooth?: string, cond?: string) => {
    if (typeof window !== 'undefined') {
      const msg = { type: 'DENTAL_ODONTOGRAM_UPDATED', tooth, condition: cond };
      try {
        window.parent?.postMessage(msg, '*');
      } catch (e) {}
      try {
        window.opener?.postMessage(msg, '*');
      } catch (e) {}
    }
  };

  const loadProceduresFromPlan = (plan: any) => {
    const procs: Record<string, any[]> = {};
    if (plan && plan.phases) {
        plan.phases.forEach((p: any) => {
            p.procedures?.forEach((proc: any) => {
                if (proc.tooth_number_fdi) {
                    if (!procs[proc.tooth_number_fdi]) procs[proc.tooth_number_fdi] = [];
                    procs[proc.tooth_number_fdi].push(proc);
                }
            });
        });
    }
    setPlannedProcedures(procs);
  };

  const handleCreatePlan = async () => {
    if (!newPlanTitle.trim()) return;
    setSavingStatus('saving');
    try {
      const res = await axios.post(`/api/patients/${patient.id}/treatment-plans`, {
        title: newPlanTitle,
      });
      setAllPlans([res.data, ...allPlans]);
      setActivePlan(res.data);
      loadProceduresFromPlan(res.data);
      setIsCreatingPlan(false);
      setNewPlanTitle(PLAN_TEMPLATES[0]);
      setSavingStatus('saved');
      setTimeout(() => setSavingStatus('idle'), 2000);
    } catch (err) {
      console.error('Failed to create plan', err);
      setSavingStatus('error');
    }
  };

  const handlePlanChange = (planId: number) => {
    const plan = allPlans.find(p => p.id === planId);
    if (plan) {
      setActivePlan(plan);
      loadProceduresFromPlan(plan);
    }
  };

  const handleTreatmentSelect = async (procedureCode: string, details?: {
    anesthetic_agent?: string;
    anesthetic_dosage?: string;
    anesthetic_technique?: string;
    materials_used_json?: string[];
  }) => {
    if (!selectedTooth || !activePlan) return;
    setSavingStatus('saving');
    try {
      const res = await axios.post(`/api/treatment-plans/${activePlan.id}/procedures`, {
        tooth_number_fdi: selectedTooth,
        procedure_code: procedureCode,
        anesthetic_agent: details?.anesthetic_agent,
        anesthetic_dosage: details?.anesthetic_dosage,
        anesthetic_technique: details?.anesthetic_technique,
        materials_used_json: details?.materials_used_json,
      });
      // Update local state
      setPlannedProcedures(prev => ({
        ...prev,
        [selectedTooth]: [...(prev[selectedTooth] || []), res.data.procedure]
      }));
      setSavingStatus('saved');
      notifyParentWindow(selectedTooth);
      setTimeout(() => setSavingStatus('idle'), 2000);
    } catch (err) {
      console.error('Failed to add treatment', err);
      setSavingStatus('error');
    }
  };

  const handleTreatmentComplete = async (procedureId: number, toothNumber: string) => {
    setSavingStatus('saving');
    try {
      // In a real app, you'd want a dedicated endpoint like PUT /api/procedures/{id}/complete
      // For now, we simulate an API call or assume the backend accepts a PATCH.
      // But since we don't have that endpoint in this demo, let's create a quick API endpoint in web.php.
      await axios.patch(`/api/procedures/${procedureId}/complete`);
      
      // Update local state
      setPlannedProcedures(prev => {
        const procs = prev[toothNumber] || [];
        return {
          ...prev,
          [toothNumber]: procs.map(p => p.id === procedureId ? { ...p, status: 'completed' } : p)
        };
      });
      setSavingStatus('saved');
      notifyParentWindow(toothNumber);
      setTimeout(() => setSavingStatus('idle'), 2000);
    } catch (err) {
      console.error('Failed to complete treatment', err);
      setSavingStatus('error');
    }
  };

  const handleExamChange = async (examId: number) => {
    const exam = examinations.find(e => e.id === examId);
    if (!exam) return;
    setActiveExam(exam);
    
    // Fetch findings for this exam
    try {
      const res = await axios.get(`/api/examinations/${examId}/odontogram`);
      setTeethRecords(res.data);
    } catch (err) {
      console.error('Failed to load examination details', err);
    }
  };

  const createNewExam = async () => {
    try {
      const res = await axios.post(`/api/patients/${patient.id}/examinations`, { type: 'periodic' });
      setExaminations([res.data, ...examinations]);
      setActiveExam(res.data);
      setTeethRecords({}); // empty slate for new exam
    } catch (err) {
      console.error('Failed to create examination', err);
    }
  };

  const currentRecord = selectedTooth ? teethRecords[selectedTooth] : undefined;
  const currentCondition: ToothCondition = currentRecord?.condition || 'healthy';
  const currentNote = currentRecord?.notes || '';
  const currentSurfaces = currentRecord?.surfaces || {};
  const currentMedicalDetails = parseMedicalDetails(currentNote);

  const handleMedicalDetailChange = async (field: keyof MedicalDetails, value: string) => {
    if (!selectedTooth) return;
    const previousState = { ...teethRecords };
    const updatedDetails = { ...currentMedicalDetails, [field]: value };
    const newNote = formatMedicalDetails(updatedDetails);
    const updatedRecord = { ...currentRecord, condition: currentCondition, notes: newNote };

    setTeethRecords((prev) => ({
      ...prev,
      [selectedTooth]: updatedRecord,
    }));

    setSavingStatus('saving');

    try {
      await axios.post(`/api/examinations/${activeExam.id}/findings`, {
        tooth_number: selectedTooth,
        condition: currentCondition,
        notes: newNote,
        surfaces: currentSurfaces,
      });
      setSavingStatus('saved');
      notifyParentWindow(selectedTooth, currentCondition);
      setTimeout(() => setSavingStatus('idle'), 2000);
    } catch (err) {
      console.error('Failed to update medical details:', err);
      setTeethRecords(previousState);
      setSavingStatus('error');
    }
  };

  const handleConditionSelect = async (condition: ToothCondition) => {
    if (!selectedTooth) return;

    // Optimistic UI Update
    const previousState = { ...teethRecords };
    const updatedRecord = {
      ...currentRecord,
      condition: condition,
    };
    
    setTeethRecords((prev) => ({
      ...prev,
      [selectedTooth]: updatedRecord,
    }));

    setSavingStatus('saving');

    try {
      await axios.post(`/api/examinations/${activeExam.id}/findings`, {
        tooth_number: selectedTooth,
        condition: condition,
        notes: currentNote,
        surfaces: currentSurfaces,
      });
      setSavingStatus('saved');
      notifyParentWindow(selectedTooth, condition);
      setTimeout(() => setSavingStatus('idle'), 2000);
    } catch (err) {
      console.error('Failed to update tooth condition:', err);
      setTeethRecords(previousState); // Rollback on error
      setSavingStatus('error');
    }
  };

  const handleNoteSave = async (newNote: string) => {
    if (!selectedTooth) return;

    const updatedRecord = { ...currentRecord, condition: currentCondition, notes: newNote };
    setTeethRecords((prev) => ({
      ...prev,
      [selectedTooth]: updatedRecord,
    }));
    
    setSavingStatus('saving');

    try {
      await axios.post(`/api/examinations/${activeExam.id}/findings`, {
        tooth_number: selectedTooth,
        condition: currentCondition,
        notes: newNote,
        surfaces: currentSurfaces,
      });
      setSavingStatus('saved');
      notifyParentWindow(selectedTooth, currentCondition);
      setTimeout(() => setSavingStatus('idle'), 2000);
    } catch (err) {
      console.error('Failed to update tooth note:', err);
      setSavingStatus('error');
    }
  };

  const handleSurfaceSelect = async (surface: keyof ToothSurfaces, condition: SurfaceCondition) => {
    if (!selectedTooth) return;

    // Toggle logic: if clicking the same condition, clear it (healthy)
    const currentSurfaceCondition = currentSurfaces[surface];
    const newCondition = currentSurfaceCondition === condition ? 'healthy' : condition;

    const newSurfaces = { ...currentSurfaces, [surface]: newCondition };
    const updatedRecord = { ...currentRecord, condition: currentCondition, surfaces: newSurfaces };

    setTeethRecords((prev) => ({
      ...prev,
      [selectedTooth]: updatedRecord,
    }));
    
    setSavingStatus('saving');

    try {
      await axios.post(`/api/examinations/${activeExam.id}/findings`, {
        tooth_number: selectedTooth,
        condition: currentCondition,
        notes: currentNote,
        surfaces: newSurfaces,
      });
      setSavingStatus('saved');
      notifyParentWindow(selectedTooth, currentCondition);
      setTimeout(() => setSavingStatus('idle'), 2000);
    } catch (err) {
      console.error('Failed to update tooth surface:', err);
      setSavingStatus('error');
    }
  };

  const handleSurfaceClick3D = (toothNumber: string, surfaceKey: ToothSurfaceKey) => {
    setSelectedTooth(toothNumber);
    if (surfaceKey === 'cervical') return;
    const surfKey = surfaceKey as keyof ToothSurfaces;
    const currentSurf = teethRecords[toothNumber]?.surfaces?.[surfKey] || 'healthy';
    let nextCond: SurfaceCondition = 'active_caries';
    if (currentSurf === 'active_caries') nextCond = 'composite_filled';
    else if (currentSurf === 'composite_filled') nextCond = 'healthy';
    handleSurfaceSelect(surfKey, nextCond);
  };

  // Double-Click Modal State
  const [modalTooth, setModalTooth] = useState<string | null>(null);

  // Quick statistics
  const conditionCounts = Object.values(teethRecords).reduce((acc, record) => {
    const cond = record.condition || 'healthy';
    acc[cond] = (acc[cond] || 0) + 1;
    return acc;
  }, {} as Record<ToothCondition, number>);

  if (viewMode === 'clean') {
    return (
      <div className="w-full h-screen bg-slate-950 relative flex items-center justify-center overflow-hidden">
        <Dental3DViewer
          teethRecords={teethRecords}
          plannedProcedures={plannedProcedures}
          selectedTooth={selectedTooth}
          onSelectTooth={(num) => setSelectedTooth(num)}
          onSelectSurface={handleSurfaceClick3D}
          onDoubleClickTooth={(num) => setModalTooth(num)}
          showGums={showGums}
          onShowGumsChange={setShowGums}
          isolateTooth={isolateTooth}
          onIsolateToothChange={setIsolateTooth}
          archMode={archMode}
          onArchModeChange={setArchMode}
          cameraPreset={cameraPreset}
          onCameraPresetChange={setCameraPreset}
          onOpenVoiceNote={(num, proc) => handleOpenVoiceNote(num, proc)}
          cleanMode={true}
        />
        
        {/* Hovering Action Button */}
        <div className="absolute bottom-6 right-6 z-10">
          <button 
            onClick={() => {
              window.top?.open(`/patients/${patient.id}/odontogram-details`, '_blank');
            }}
            className="flex items-center gap-2 px-5 py-3 bg-slate-900/90 hover:bg-slate-800 text-white font-bold rounded-xl shadow-2xl border border-slate-700 backdrop-blur-md transition-all hover:scale-105 active:scale-95"
          >
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className="text-emerald-400"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
            Open Clinical Details
          </button>
        </div>
        
        {/* Clean Mode Double Click Modal */}
        {modalTooth && (
          <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/80 backdrop-blur-sm transition-opacity">
            <div className="bg-slate-900 border border-slate-700 rounded-2xl shadow-2xl w-full max-w-4xl overflow-hidden">
              <div className="p-5 border-b border-slate-800 flex justify-between items-center bg-slate-950/50">
                <h2 className="text-lg font-bold text-white">Tooth #{modalTooth} Details</h2>
                <button 
                  onClick={() => setModalTooth(null)}
                  className="text-slate-400 hover:text-white transition-colors"
                >
                  ✕
                </button>
              </div>
              
              <div className="grid grid-cols-1 md:grid-cols-2 gap-0 border-b border-slate-800">
                <div className="bg-slate-950/40 p-4 border-b md:border-b-0 md:border-r border-slate-800">
                  <SingleToothViewer 
                    modelUrl="/models/Dentition_Permanent_Full_V2.1.glb" 
                    toothNumber={modalTooth} 
                    record={teethRecords[modalTooth]} 
                  />
                </div>
                <div className="p-6 flex flex-col gap-5 bg-slate-900/50">
                  <div>
                    <label className="text-xs font-semibold text-slate-400 uppercase tracking-wider block mb-2">Main Condition</label>
                    <div className="flex items-center gap-2">
                      <span
                        className="w-3 h-3 rounded-full"
                        style={{ backgroundColor: CONDITION_COLORS[teethRecords[modalTooth]?.condition || 'healthy'] }}
                      />
                      <span className="font-medium text-slate-200">
                        {CONDITION_LABELS[teethRecords[modalTooth]?.condition || 'healthy'].label}
                      </span>
                    </div>
                  </div>
                  <div className="bg-slate-950/50 p-4 rounded-xl border border-slate-800/80">
                    <label className="text-xs font-semibold text-slate-400 uppercase tracking-wider block mb-3">Surface Breakdown</label>
                    <div className="grid grid-cols-2 gap-y-3 gap-x-6">
                      {(['mesial', 'occlusal', 'distal', 'buccal', 'lingual'] as const).map((surface) => {
                        const sCond = teethRecords[modalTooth]?.surfaces?.[surface] || 'healthy';
                        const cLabel = sCond === 'healthy' ? 'Healthy' : sCond === 'active_caries' ? 'Active Caries' : 'Composite Filled';
                        return (
                          <div key={surface} className="flex justify-between items-center text-sm">
                            <span className="text-slate-400 capitalize">{surface}:</span>
                            <div className="flex items-center gap-1.5">
                              <span className="w-2 h-2 rounded-full" style={{ backgroundColor: CONDITION_COLORS[sCond] }} />
                              <span className="text-slate-200">{cLabel}</span>
                            </div>
                          </div>
                        );
                      })}
                    </div>
                  </div>

                  {/* Procedures and Anesthesia Details */}
                  {plannedProcedures[modalTooth] && plannedProcedures[modalTooth].length > 0 && (
                    <div className="bg-slate-950/60 p-3.5 rounded-xl border border-slate-800 flex flex-col gap-2">
                      <label className="text-xs font-semibold text-slate-400 uppercase tracking-wider">
                        Procedures ({plannedProcedures[modalTooth].length})
                      </label>
                      {plannedProcedures[modalTooth].map((proc: any, idx: number) => (
                        <div key={idx} className="p-2.5 rounded-lg bg-slate-900 border border-slate-800/80 flex flex-col gap-1 text-xs">
                          <div className="flex items-center justify-between">
                            <span className="font-semibold text-slate-100">{proc.procedure_code?.title || 'Procedure'}</span>
                            <span className={`text-[10px] px-2 py-0.5 rounded-full font-bold uppercase ${proc.status === 'completed' ? 'bg-emerald-500/20 text-emerald-400' : 'bg-purple-500/20 text-purple-400'}`}>
                              {proc.status}
                            </span>
                          </div>
                          {proc.anesthetic_agent && (
                            <div className="text-emerald-400 font-medium text-[11px]">
                              💉 Anesthesia: {proc.anesthetic_agent} ({proc.anesthetic_dosage || '1 carpule'}) - {proc.anesthetic_technique || 'Infiltration'}
                            </div>
                          )}
                          {proc.materials_used_json && (
                            <div className="text-slate-300 text-[11px]">
                              <span className="text-slate-400">Materials: </span>
                              {Array.isArray(proc.materials_used_json) ? proc.materials_used_json.join(', ') : proc.materials_used_json}
                            </div>
                          )}
                          {proc.tools_used_json && (
                            <div className="text-slate-300 text-[11px]">
                              <span className="text-slate-400">Tools: </span>
                              {Array.isArray(proc.tools_used_json) ? proc.tools_used_json.join(', ') : proc.tools_used_json}
                            </div>
                          )}
                        </div>
                      ))}
                    </div>
                  )}

                  {teethRecords[modalTooth]?.notes && (
                    <div>
                      <label className="text-xs font-semibold text-slate-400 uppercase tracking-wider block mb-2">Clinical Notes</label>
                      <div className="bg-slate-800/50 border border-slate-700/50 rounded-lg p-3 text-sm text-slate-300 italic whitespace-pre-wrap">
                        "{teethRecords[modalTooth]?.notes}"
                      </div>
                    </div>
                  )}
                </div>
              </div>
              <div className="p-4 border-t border-slate-800 bg-slate-950/50 flex justify-between items-center gap-3">
                <button
                  type="button"
                  onClick={() => {
                    const proc = plannedProcedures[modalTooth] ? plannedProcedures[modalTooth][0] : null;
                    handleOpenVoiceNote(modalTooth, proc);
                  }}
                  className="px-4 py-2 bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-bold rounded-xl transition-all shadow-lg flex items-center gap-1.5 active:scale-95"
                >
                  <Mic className="w-3.5 h-3.5" />
                  Send WhatsApp Voice Note
                </button>

                <div className="flex items-center gap-2">
                  <button 
                    onClick={() => {
                      setModalTooth(null);
                      window.top?.open(`/patients/${patient.id}/odontogram-details`, '_blank');
                    }}
                    className="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-white text-xs font-semibold rounded-xl border border-slate-700 transition-colors"
                  >
                    Edit Tooth
                  </button>
                  <button 
                    onClick={() => setModalTooth(null)}
                    className="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-semibold rounded-xl transition-colors"
                  >
                    Close
                  </button>
                </div>
              </div>
            </div>
          </div>
        )}

        {/* Global Post-Op Voice Note Modal */}
        <VoiceNoteModal
          isOpen={voiceNoteModalOpen}
          onClose={() => setVoiceNoteModalOpen(false)}
          patient={patient}
          toothNumber={voiceNoteTooth}
          procedure={voiceNoteProcedure}
        />
      </div>
    );
  }

  return (
    <div className="min-h-screen bg-slate-950 text-slate-100 font-sans p-4 md:p-8">
      {/* Top Header Bar */}
      <div className="max-w-7xl mx-auto mb-6 bg-slate-900/90 border border-slate-800 rounded-2xl p-5 shadow-xl flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
          <div className="flex flex-wrap items-center gap-3">
            <span className="px-3 py-1 bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 text-xs font-semibold rounded-full uppercase tracking-wider">
              3D Interactive Odontogram
            </span>
            <span className="text-xs text-slate-400">
              Patient File: <strong className="text-slate-200">{patient.file_number}</strong>
            </span>
            <div className="ml-4 flex items-center bg-slate-950 p-1 rounded-lg border border-slate-800">
              <button
                onClick={() => setMode('findings')}
                className={`px-4 py-1.5 rounded-md text-xs font-bold transition-all ${mode === 'findings' ? 'bg-slate-800 text-white shadow' : 'text-slate-400 hover:text-slate-200'}`}
              >
                Chart Findings
              </button>
              <button
                onClick={() => setMode('treatment')}
                className={`px-4 py-1.5 rounded-md text-xs font-bold transition-all ${mode === 'treatment' ? 'bg-emerald-600 text-white shadow' : 'text-slate-400 hover:text-slate-200'}`}
              >
                Plan Treatment
              </button>
            </div>
          </div>
          <h1 className="text-2xl md:text-3xl font-bold mt-1 text-white tracking-tight flex items-center gap-4">
            {patient.full_name}
            
            <div className="flex items-center gap-2 ml-4">
              <select
                value={activeExam.id}
                onChange={(e) => handleExamChange(Number(e.target.value))}
                className="bg-slate-950 border border-slate-700 text-sm text-slate-200 rounded-lg px-3 py-1.5 focus:outline-none focus:border-emerald-500"
              >
                {examinations.map(exam => (
                  <option key={exam.id} value={exam.id}>
                    {new Date(exam.examined_at || exam.created_at).toLocaleDateString(undefined, { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' })} - {exam.type.charAt(0).toUpperCase() + exam.type.slice(1)} Exam
                  </option>
                ))}
              </select>
              <button
                onClick={createNewExam}
                className="bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-semibold px-3 py-1.5 rounded-lg transition-colors"
              >
                + New Exam
              </button>
            </div>

            <button 
              onClick={() => window.close()}
              className="text-xs px-3 py-1.5 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-lg transition-colors font-medium border border-slate-700 flex items-center gap-1.5 ml-auto"
            >
              <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="m15 18-6-6 6-6"/></svg>
              Close Fullscreen
            </button>
          </h1>
        </div>

        {/* Sync Status Badge */}
        <div className="flex items-center gap-3 bg-slate-950/60 px-4 py-2 rounded-xl border border-slate-800 text-sm">
          {savingStatus === 'saving' && (
            <div className="flex items-center gap-2 text-amber-400">
              <span className="w-2 h-2 rounded-full bg-amber-400 animate-ping" />
              <span>Saving changes...</span>
            </div>
          )}
          {savingStatus === 'saved' && (
            <div className="flex items-center gap-2 text-emerald-400">
              <span className="w-2 h-2 rounded-full bg-emerald-400" />
              <span>Saved to database</span>
            </div>
          )}
          {savingStatus === 'error' && (
            <div className="flex items-center gap-2 text-red-400">
              <span className="w-2 h-2 rounded-full bg-red-400" />
              <span>Save failed. Reverted.</span>
            </div>
          )}
          {savingStatus === 'idle' && (
            <div className="flex items-center gap-2 text-slate-400">
              <span className="w-2 h-2 rounded-full bg-slate-500" />
              <span>Live Synced</span>
            </div>
          )}
        </div>
      </div>

      {/* Main Layout Grid */}
      <div className="max-w-7xl mx-auto grid grid-cols-1 lg:grid-cols-12 gap-6">
        {/* Left Side: 3D Viewport & Tooth Quick Selector (7 Cols) */}
        <div className="lg:col-span-7 flex flex-col gap-6">
          <div className="relative h-[500px] w-full">
            <Dental3DViewer
              teethRecords={teethRecords}
              plannedProcedures={plannedProcedures}
              selectedTooth={selectedTooth}
              onSelectTooth={(num) => setSelectedTooth(num)}
              onSelectSurface={handleSurfaceClick3D}
              onDoubleClickTooth={(num) => setModalTooth(num)}
              showGums={showGums}
              onShowGumsChange={setShowGums}
              isolateTooth={isolateTooth}
              onIsolateToothChange={setIsolateTooth}
              archMode={archMode}
              onArchModeChange={setArchMode}
              cameraPreset={cameraPreset}
              onCameraPresetChange={setCameraPreset}
              onOpenVoiceNote={(num, proc) => handleOpenVoiceNote(num, proc)}
            />
          </div>

          {/* Quick FDI Teeth Selector Grid */}
          <div className="bg-slate-900/90 border border-slate-800 rounded-2xl p-5 shadow-xl">
            <h3 className="text-sm font-semibold text-slate-300 uppercase tracking-wider mb-3">
              FDI Dental Arch Quick Selector
            </h3>

            {/* Upper Quadrants */}
            <div className="mb-4">
              <div className="text-[11px] font-medium text-slate-400 mb-1.5 flex justify-between">
                <span>Upper Right (Q1)</span>
                <span>Upper Left (Q2)</span>
              </div>
              <div className="grid grid-cols-16 gap-1 sm:gap-1.5 bg-slate-950/80 p-2 rounded-xl border border-slate-800/80">
                {[...FDI_TEETH.upperRight, ...FDI_TEETH.upperLeft].map((tNum) => {
                  const cond = teethRecords[tNum]?.condition || 'healthy';
                  const isSel = selectedTooth === tNum;
                  return (
                    <button
                      key={tNum}
                      onClick={() => setSelectedTooth(tNum)}
                      style={{
                        borderColor: isSel ? '#10B981' : CONDITION_COLORS[cond],
                      }}
                      className={`h-9 flex flex-col items-center justify-center rounded-lg text-xs font-semibold transition-all border ${
                        isSel
                          ? 'bg-emerald-500/20 text-emerald-300 shadow-md ring-2 ring-emerald-500/50'
                          : 'bg-slate-900 text-slate-300 hover:bg-slate-800'
                      }`}
                    >
                      <span>{tNum}</span>
                      <span
                        className="w-1.5 h-1.5 rounded-full mt-0.5"
                        style={{ backgroundColor: CONDITION_COLORS[cond] }}
                      />
                    </button>
                  );
                })}
              </div>
            </div>

            {/* Lower Quadrants */}
            <div>
              <div className="text-[11px] font-medium text-slate-400 mb-1.5 flex justify-between">
                <span>Lower Right (Q4)</span>
                <span>Lower Left (Q3)</span>
              </div>
              <div className="grid grid-cols-16 gap-1 sm:gap-1.5 bg-slate-950/80 p-2 rounded-xl border border-slate-800/80">
                {[...FDI_TEETH.lowerRight, ...FDI_TEETH.lowerLeft].map((tNum) => {
                  const cond = teethRecords[tNum]?.condition || 'healthy';
                  const isSel = selectedTooth === tNum;
                  return (
                    <button
                      key={tNum}
                      onClick={() => setSelectedTooth(tNum)}
                      style={{
                        borderColor: isSel ? '#10B981' : CONDITION_COLORS[cond],
                      }}
                      className={`h-9 flex flex-col items-center justify-center rounded-lg text-xs font-semibold transition-all border ${
                        isSel
                          ? 'bg-emerald-500/20 text-emerald-300 shadow-md ring-2 ring-emerald-500/50'
                          : 'bg-slate-900 text-slate-300 hover:bg-slate-800'
                      }`}
                    >
                      <span>{tNum}</span>
                      <span
                        className="w-1.5 h-1.5 rounded-full mt-0.5"
                        style={{ backgroundColor: CONDITION_COLORS[cond] }}
                      />
                    </button>
                  );
                })}
              </div>
            </div>
          </div>
        </div>

        {/* Right Side: Clinical Condition Selector Panel (5 Cols) */}
        <div className="lg:col-span-5 flex flex-col gap-6">
          {/* Selected Tooth Info Card */}
          <div className="bg-slate-900/90 border border-slate-800 rounded-2xl p-6 shadow-xl">
            <div className="flex items-center justify-between pb-4 border-b border-slate-800">
              <div>
                <div className="text-xs font-semibold uppercase text-slate-400">
                  Selected Tooth
                </div>
                <div className="text-3xl font-extrabold text-white mt-0.5">
                  {selectedTooth ? `Tooth #${selectedTooth}` : 'None Selected'}
                </div>
              </div>
              {selectedTooth && (
                <div
                  className="px-3.5 py-1.5 rounded-xl border text-xs font-bold flex items-center gap-2"
                  style={{
                    backgroundColor: `${CONDITION_COLORS[currentCondition]}20`,
                    borderColor: CONDITION_COLORS[currentCondition],
                    color: CONDITION_COLORS[currentCondition],
                  }}
                >
                  <span
                    className="w-2.5 h-2.5 rounded-full"
                    style={{ backgroundColor: CONDITION_COLORS[currentCondition] }}
                  />
                  {CONDITION_LABELS[currentCondition].label}
                </div>
              )}
            </div>

            {/* Clinical Dropdown Command Center */}
            <div className="mt-5">
              <label className="block text-xs font-semibold uppercase text-slate-400 mb-3">
                {mode === 'findings' ? 'Clinical Findings & Medical Details' : 'Propose Treatment Procedure'}
              </label>

              {mode === 'findings' ? (
                <div className="flex flex-col gap-3.5">
                  {/* 1. Primary Condition Dropdown */}
                  <div>
                    <label className="block text-xs font-semibold uppercase text-slate-400 mb-1.5">
                      Primary Condition
                    </label>
                    <select
                      value={currentCondition}
                      onChange={(e) => handleConditionSelect(e.target.value as ToothCondition)}
                      disabled={!selectedTooth}
                      className="w-full bg-slate-950/80 border border-slate-700 rounded-xl px-3.5 py-2.5 text-sm font-semibold text-slate-100 focus:outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 transition-all cursor-pointer disabled:opacity-50"
                    >
                      {(Object.keys(CONDITION_LABELS) as ToothCondition[]).map((condKey) => (
                        <option key={condKey} value={condKey} className="bg-slate-900 text-slate-100 py-1">
                          {CONDITION_LABELS[condKey].label} - {CONDITION_LABELS[condKey].description}
                        </option>
                      ))}
                    </select>
                  </div>

                  {/* 2. Medical Diagnosis Dropdown */}
                  <div>
                    <label className="block text-xs font-semibold uppercase text-slate-400 mb-1.5">
                      Pathological Diagnosis
                    </label>
                    <select
                      value={currentMedicalDetails.diagnosis}
                      onChange={(e) => handleMedicalDetailChange('diagnosis', e.target.value)}
                      disabled={!selectedTooth}
                      className="w-full bg-slate-950/80 border border-slate-700 rounded-xl px-3.5 py-2.5 text-xs font-medium text-slate-100 focus:outline-none focus:border-emerald-500 transition-all cursor-pointer disabled:opacity-50"
                    >
                      {DIAGNOSIS_OPTIONS.map((opt) => (
                        <option key={opt.value} value={opt.value} className="bg-slate-900 text-slate-100">
                          {opt.label}
                        </option>
                      ))}
                    </select>
                  </div>

                  {/* 3. Severity & Treatment Urgency (2 Columns) */}
                  <div className="grid grid-cols-2 gap-3">
                    <div>
                      <label className="block text-xs font-semibold uppercase text-slate-400 mb-1.5">
                        Severity / Stage
                      </label>
                      <select
                        value={currentMedicalDetails.severity}
                        onChange={(e) => handleMedicalDetailChange('severity', e.target.value)}
                        disabled={!selectedTooth}
                        className="w-full bg-slate-950/80 border border-slate-700 rounded-xl px-2.5 py-2 text-xs font-medium text-slate-100 focus:outline-none focus:border-emerald-500 transition-all cursor-pointer disabled:opacity-50"
                      >
                        {SEVERITY_OPTIONS.map((opt) => (
                          <option key={opt.value} value={opt.value} className="bg-slate-900 text-slate-100">
                            {opt.label}
                          </option>
                        ))}
                      </select>
                    </div>
                    <div>
                      <label className="block text-xs font-semibold uppercase text-slate-400 mb-1.5">
                        Urgency Priority
                      </label>
                      <select
                        value={currentMedicalDetails.urgency}
                        onChange={(e) => handleMedicalDetailChange('urgency', e.target.value)}
                        disabled={!selectedTooth}
                        className="w-full bg-slate-950/80 border border-slate-700 rounded-xl px-2.5 py-2 text-xs font-medium text-slate-100 focus:outline-none focus:border-emerald-500 transition-all cursor-pointer disabled:opacity-50"
                      >
                        {URGENCY_OPTIONS.map((opt) => (
                          <option key={opt.value} value={opt.value} className="bg-slate-900 text-slate-100">
                            {opt.label}
                          </option>
                        ))}
                      </select>
                    </div>
                  </div>

                  {/* 4. Mobility & Perio Grade */}
                  <div>
                    <label className="block text-xs font-semibold uppercase text-slate-400 mb-1.5">
                      Mobility & Perio Grade
                    </label>
                    <select
                      value={currentMedicalDetails.mobility}
                      onChange={(e) => handleMedicalDetailChange('mobility', e.target.value)}
                      disabled={!selectedTooth}
                      className="w-full bg-slate-950/80 border border-slate-700 rounded-xl px-3 py-2 text-xs font-medium text-slate-100 focus:outline-none focus:border-emerald-500 transition-all cursor-pointer disabled:opacity-50"
                    >
                      {MOBILITY_OPTIONS.map((opt) => (
                        <option key={opt.value} value={opt.value} className="bg-slate-900 text-slate-100">
                          {opt.label}
                        </option>
                      ))}
                    </select>
                  </div>

                  {/* 5. Surface Details (M-O-D-B-L) Dropdowns */}
                  {selectedTooth && ['healthy', 'active_caries', 'composite_filled', 'custom'].includes(currentCondition) && (
                    <div className="mt-2 bg-slate-950/60 border border-slate-800/80 rounded-xl p-3.5">
                      <label className="block text-xs font-semibold uppercase text-slate-400 mb-2.5">
                        Surface-Level Findings (M-O-D-B-L)
                      </label>
                      <div className="flex flex-col gap-2">
                        {(['mesial', 'occlusal', 'distal', 'buccal', 'lingual'] as const).map((surface) => {
                          const surfCond = currentSurfaces[surface] || 'healthy';
                          return (
                            <div key={surface} className="flex items-center justify-between gap-3 bg-slate-900/60 p-1.5 px-2.5 rounded-lg border border-slate-800/80">
                              <span className="text-xs font-semibold text-slate-300 capitalize w-20">
                                {surface}:
                              </span>
                              <select
                                value={surfCond}
                                onChange={(e) => handleSurfaceSelect(surface, e.target.value as SurfaceCondition)}
                                className="flex-1 bg-slate-950 border border-slate-700 rounded-md px-2 py-1 text-xs text-slate-200 focus:outline-none focus:border-emerald-500 cursor-pointer"
                              >
                                <option value="healthy">Healthy Enamel</option>
                                <option value="active_caries">Caries (Decay)</option>
                                <option value="composite_filled">Composite Resin</option>
                                <option value="amalgam">Amalgam Filling</option>
                                <option value="sealant">Pit & Fissure Sealant</option>
                              </select>
                            </div>
                          );
                        })}
                      </div>
                    </div>
                  )}
                </div>
              ) : (
                <div className="flex-1 overflow-y-auto scrollbar-thin scrollbar-thumb-slate-700 flex flex-col gap-4">
                  {/* Target Treatment Plan Selector */}
                  <div className="flex flex-col gap-2">
                    <label className="text-xs font-bold text-slate-400 uppercase tracking-wider">
                      Target Treatment Plan
                    </label>
                    {isCreatingPlan ? (
                      <div className="flex gap-2">
                        <select
                          value={newPlanTitle}
                          onChange={(e) => setNewPlanTitle(e.target.value)}
                          className="flex-1 bg-slate-900 border border-slate-700 rounded-lg text-xs px-3 py-2 text-white focus:outline-none focus:border-purple-500 cursor-pointer"
                        >
                          {PLAN_TEMPLATES.map((tmpl) => (
                            <option key={tmpl} value={tmpl}>{tmpl}</option>
                          ))}
                        </select>
                        <button
                          type="button"
                          onClick={handleCreatePlan}
                          className="px-3 py-2 bg-purple-600 hover:bg-purple-700 text-white text-xs rounded-lg transition-colors font-medium"
                        >
                          Save
                        </button>
                        <button
                          type="button"
                          onClick={() => setIsCreatingPlan(false)}
                          className="px-3 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs rounded-lg transition-colors"
                        >
                          Cancel
                        </button>
                      </div>
                    ) : (
                      <div className="flex gap-2">
                        <select
                          className="flex-1 bg-slate-900 border border-slate-700 rounded-lg text-xs px-3 py-2.5 text-white focus:outline-none focus:border-purple-500 appearance-none font-medium cursor-pointer"
                          value={activePlan?.id || ''}
                          onChange={(e) => handlePlanChange(Number(e.target.value))}
                        >
                          {allPlans.map(plan => (
                            <option key={plan.id} value={plan.id}>{plan.title} ({plan.status})</option>
                          ))}
                        </select>
                        <button
                          type="button"
                          onClick={() => setIsCreatingPlan(true)}
                          className="px-3 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs rounded-lg transition-colors flex items-center justify-center whitespace-nowrap font-medium"
                        >
                          + New Plan
                        </button>
                      </div>
                    )}
                  </div>
                  
                  {/* Treatment Procedure Dropdowns Builder */}
                  <div className="flex flex-col gap-3 p-4 bg-slate-950/70 border border-slate-800 rounded-xl">
                    <span className="text-xs font-bold text-purple-300 uppercase tracking-wider">
                      Add Planned Procedure to #{selectedTooth || 'None'}
                    </span>

                    {/* Procedure Code Dropdown */}
                    <div>
                      <label className="block text-[11px] font-semibold uppercase text-slate-400 mb-1">
                        Procedure (CDT Code)
                      </label>
                      <select
                        value={selectedProcCode}
                        onChange={(e) => setSelectedProcCode(e.target.value)}
                        disabled={!selectedTooth}
                        className="w-full bg-slate-900 border border-slate-700 rounded-xl px-3 py-2 text-xs font-medium text-slate-100 focus:outline-none focus:border-purple-500 cursor-pointer disabled:opacity-50"
                      >
                        {PROCEDURE_CATEGORIES.map((cat) => (
                          <optgroup key={cat.category} label={cat.category} className="bg-slate-950 font-bold text-slate-300">
                            {cat.procedures.map((p) => (
                              <option key={p.code} value={p.code} className="bg-slate-900 text-slate-100 font-normal">
                                [{p.code}] {p.name}
                              </option>
                            ))}
                          </optgroup>
                        ))}
                      </select>
                    </div>

                    {/* Anesthetic Agent Dropdown */}
                    <div>
                      <label className="block text-[11px] font-semibold uppercase text-slate-400 mb-1">
                        Anesthetic Agent
                      </label>
                      <select
                        value={selectedAnesthesia}
                        onChange={(e) => setSelectedAnesthesia(e.target.value)}
                        disabled={!selectedTooth}
                        className="w-full bg-slate-900 border border-slate-700 rounded-lg px-2.5 py-1.5 text-xs text-slate-100 focus:outline-none focus:border-purple-500 cursor-pointer disabled:opacity-50"
                      >
                        {ANESTHESIA_OPTIONS.map((opt) => (
                          <option key={opt.value} value={opt.value} className="bg-slate-900 text-slate-100">
                            {opt.label}
                          </option>
                        ))}
                      </select>
                    </div>

                    {/* Dosage & Technique Dropdowns */}
                    <div className="grid grid-cols-2 gap-2">
                      <div>
                        <label className="block text-[11px] font-semibold uppercase text-slate-400 mb-1">
                          Dosage
                        </label>
                        <select
                          value={selectedDosage}
                          onChange={(e) => setSelectedDosage(e.target.value)}
                          disabled={!selectedTooth}
                          className="w-full bg-slate-900 border border-slate-700 rounded-lg px-2 py-1.5 text-xs text-slate-100 focus:outline-none focus:border-purple-500 cursor-pointer disabled:opacity-50"
                        >
                          {DOSAGE_OPTIONS.map((opt) => (
                            <option key={opt.value} value={opt.value} className="bg-slate-900 text-slate-100">
                              {opt.label}
                            </option>
                          ))}
                        </select>
                      </div>
                      <div>
                        <label className="block text-[11px] font-semibold uppercase text-slate-400 mb-1">
                          Technique
                        </label>
                        <select
                          value={selectedTechnique}
                          onChange={(e) => setSelectedTechnique(e.target.value)}
                          disabled={!selectedTooth}
                          className="w-full bg-slate-900 border border-slate-700 rounded-lg px-2 py-1.5 text-xs text-slate-100 focus:outline-none focus:border-purple-500 cursor-pointer disabled:opacity-50"
                        >
                          {TECHNIQUE_OPTIONS.map((opt) => (
                            <option key={opt.value} value={opt.value} className="bg-slate-900 text-slate-100">
                              {opt.label}
                            </option>
                          ))}
                        </select>
                      </div>
                    </div>

                    {/* Primary Material Dropdown */}
                    <div>
                      <label className="block text-[11px] font-semibold uppercase text-slate-400 mb-1">
                        Primary Dental Material
                      </label>
                      <select
                        value={selectedMaterial}
                        onChange={(e) => setSelectedMaterial(e.target.value)}
                        disabled={!selectedTooth}
                        className="w-full bg-slate-900 border border-slate-700 rounded-lg px-2.5 py-1.5 text-xs text-slate-100 focus:outline-none focus:border-purple-500 cursor-pointer disabled:opacity-50"
                      >
                        {MATERIAL_OPTIONS.map((opt) => (
                          <option key={opt.value} value={opt.value} className="bg-slate-900 text-slate-100">
                            {opt.label}
                          </option>
                        ))}
                      </select>
                    </div>

                    <button
                      type="button"
                      onClick={() => {
                        handleTreatmentSelect(selectedProcCode, {
                          anesthetic_agent: selectedAnesthesia || undefined,
                          anesthetic_dosage: selectedDosage || undefined,
                          anesthetic_technique: selectedTechnique || undefined,
                          materials_used_json: selectedMaterial ? [selectedMaterial] : undefined,
                        });
                      }}
                      disabled={!selectedTooth}
                      className="mt-1 w-full py-2 px-3 bg-purple-600 hover:bg-purple-500 disabled:opacity-50 disabled:cursor-not-allowed text-white text-xs font-bold rounded-xl shadow-lg transition-all flex items-center justify-center gap-2"
                    >
                      <span>+ Add Planned Procedure</span>
                    </button>
                  </div>
                  
                  {/* List planned treatments for selected tooth */}
                  {selectedTooth && plannedProcedures[selectedTooth] && plannedProcedures[selectedTooth].length > 0 && (
                    <div className="mt-4 pt-4 border-t border-slate-800">
                      <label className="block text-xs font-semibold uppercase text-slate-400 mb-2">
                        Planned for #{selectedTooth}
                      </label>
                      <ul className="space-y-2">
                        {plannedProcedures[selectedTooth].map((proc: any, i: number) => (
                          <li key={i} className="text-sm bg-slate-900 border border-slate-700 rounded-lg p-2.5 flex justify-between items-center text-emerald-100">
                            <div>
                              <span>{proc.procedure_code?.title || 'Unknown Procedure'}</span>
                              <div className="text-[10px] text-slate-400 mt-0.5">Status: <span className={proc.status === 'completed' ? 'text-emerald-400' : 'text-amber-400'}>{proc.status}</span></div>
                            </div>
                            {proc.status !== 'completed' && (
                              <button 
                                onClick={() => handleTreatmentComplete(proc.id, selectedTooth)}
                                className="px-2 py-1 bg-emerald-600/20 hover:bg-emerald-600/40 text-emerald-400 text-xs rounded border border-emerald-500/30 transition-colors"
                              >
                                Complete ✓
                              </button>
                            )}
                          </li>
                        ))}
                      </ul>
                    </div>
                  )}
                </div>
              )}
            </div>
          </div>

          {/* Condition Overview Summary Card */}
          <div className="bg-slate-900/90 border border-slate-800 rounded-2xl p-6 shadow-xl">
            <h3 className="text-xs font-semibold text-slate-400 uppercase tracking-wider mb-4">
              Odontogram Summary Statistics
            </h3>
            <div className="grid grid-cols-2 gap-3">
              {(Object.keys(CONDITION_LABELS) as ToothCondition[]).map((condKey) => {
                const count = conditionCounts[condKey] || 0;
                const meta = CONDITION_LABELS[condKey];
                const hexColor = CONDITION_COLORS[condKey];
                return (
                  <div
                    key={condKey}
                    className="flex items-center justify-between p-2.5 bg-slate-950/70 border border-slate-800/80 rounded-xl"
                  >
                    <div className="flex items-center gap-2">
                      <span
                        className="w-2.5 h-2.5 rounded-full"
                        style={{ backgroundColor: hexColor }}
                      />
                      <span className="text-xs text-slate-300 font-medium">
                        {meta.label}
                      </span>
                    </div>
                    <span className="text-xs font-bold text-white px-2 py-0.5 bg-slate-800 rounded-md">
                      {count}
                    </span>
                  </div>
                );
              })}
            </div>
          </div>
        </div>
      </div>

      {/* Double-Click Details Modal */}
      {modalTooth && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/80 backdrop-blur-sm transition-opacity">
          <div className="bg-slate-900 border border-slate-700 rounded-2xl shadow-2xl w-full max-w-4xl overflow-hidden animate-in fade-in zoom-in-95 duration-200">
            <div className="p-5 border-b border-slate-800 flex justify-between items-center bg-slate-950/50">
              <h2 className="text-lg font-bold text-white">Tooth #{modalTooth} Details</h2>
              <button 
                onClick={() => setModalTooth(null)}
                className="text-slate-400 hover:text-white transition-colors"
              >
                ✕
              </button>
            </div>
            
            <div className="grid grid-cols-1 md:grid-cols-2 gap-0 border-b border-slate-800">
              {/* Left Column: Isolated 3D View */}
              <div className="bg-slate-950/40 p-4 border-b md:border-b-0 md:border-r border-slate-800">
                <SingleToothViewer 
                  modelUrl="/models/Dentition_Permanent_Full_V2.1.glb" 
                  toothNumber={modalTooth} 
                  record={teethRecords[modalTooth]} 
                />
              </div>

              {/* Right Column: Diagnostic Info */}
              <div className="p-6 flex flex-col gap-5 bg-slate-900/50">
                {/* Main Condition */}
                <div>
                  <label className="text-xs font-semibold text-slate-400 uppercase tracking-wider block mb-2">Main Condition</label>
                  <div className="flex items-center gap-2">
                    <span
                      className="w-3 h-3 rounded-full"
                      style={{ backgroundColor: CONDITION_COLORS[teethRecords[modalTooth]?.condition || 'healthy'] }}
                    />
                    <span className="font-medium text-slate-200">
                      {CONDITION_LABELS[teethRecords[modalTooth]?.condition || 'healthy'].label}
                    </span>
                  </div>
                </div>

              {/* Surface Breakdown */}
              <div className="bg-slate-950/50 p-4 rounded-xl border border-slate-800/80">
                <label className="text-xs font-semibold text-slate-400 uppercase tracking-wider block mb-3">Surface Breakdown</label>
                <div className="grid grid-cols-2 gap-y-3 gap-x-6">
                  {(['mesial', 'occlusal', 'distal', 'buccal', 'lingual'] as const).map((surface) => {
                    const sCond = teethRecords[modalTooth]?.surfaces?.[surface] || 'healthy';
                    const cLabel = sCond === 'healthy' ? 'Healthy' : sCond === 'active_caries' ? 'Active Caries' : 'Composite Filled';
                    return (
                      <div key={surface} className="flex justify-between items-center text-sm">
                        <span className="text-slate-400 capitalize">{surface}:</span>
                        <div className="flex items-center gap-1.5">
                          <span
                            className="w-2 h-2 rounded-full"
                            style={{ backgroundColor: CONDITION_COLORS[sCond] }}
                          />
                          <span className="text-slate-200">{cLabel}</span>
                        </div>
                      </div>
                    );
                  })}
                </div>
              </div>

              {/* Procedures & Clinical Execution Breakdown */}
              {plannedProcedures[modalTooth] && plannedProcedures[modalTooth].length > 0 && (
                <div className="bg-slate-950/60 p-4 rounded-xl border border-slate-800 flex flex-col gap-2">
                  <label className="text-xs font-semibold text-slate-400 uppercase tracking-wider">
                    Clinical Procedures ({plannedProcedures[modalTooth].length})
                  </label>
                  {plannedProcedures[modalTooth].map((proc: any, idx: number) => (
                    <div key={idx} className="p-3 rounded-lg bg-slate-900 border border-slate-800/80 flex flex-col gap-1.5 text-xs">
                      <div className="flex items-center justify-between">
                        <span className="font-semibold text-slate-100">{proc.procedure_code?.title || 'Procedure'}</span>
                        <span className={`text-[10px] px-2 py-0.5 rounded-full font-bold uppercase ${proc.status === 'completed' ? 'bg-emerald-500/20 text-emerald-400' : 'bg-purple-500/20 text-purple-400'}`}>
                          {proc.status}
                        </span>
                      </div>
                      {proc.anesthetic_agent && (
                        <div className="text-emerald-400 font-medium text-[11px]">
                          💉 Anesthesia: {proc.anesthetic_agent} ({proc.anesthetic_dosage || '1 carpule'}) - {proc.anesthetic_technique || 'Infiltration'}
                        </div>
                      )}
                      {proc.materials_used_json && (
                        <div className="text-slate-300 text-[11px]">
                          <span className="text-slate-400">Materials: </span>
                          {Array.isArray(proc.materials_used_json) ? proc.materials_used_json.join(', ') : proc.materials_used_json}
                        </div>
                      )}
                      {proc.tools_used_json && (
                        <div className="text-slate-300 text-[11px]">
                          <span className="text-slate-400">Tools: </span>
                          {Array.isArray(proc.tools_used_json) ? proc.tools_used_json.join(', ') : proc.tools_used_json}
                        </div>
                      )}
                    </div>
                  ))}
                </div>
              )}

              {/* Clinical Notes */}
              {teethRecords[modalTooth]?.notes && (
                <div>
                  <label className="text-xs font-semibold text-slate-400 uppercase tracking-wider block mb-2">Clinical Notes</label>
                  <div className="bg-slate-800/50 border border-slate-700/50 rounded-lg p-3 text-sm text-slate-300 italic whitespace-pre-wrap">
                    "{teethRecords[modalTooth]?.notes}"
                  </div>
                </div>
              )}
            </div>
            </div>

            <div className="p-4 border-t border-slate-800 bg-slate-950/50 flex justify-between items-center gap-3">
              <button
                type="button"
                onClick={() => {
                  const proc = plannedProcedures[modalTooth] ? plannedProcedures[modalTooth][0] : null;
                  handleOpenVoiceNote(modalTooth, proc);
                }}
                className="px-4 py-2 bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-bold rounded-xl transition-all shadow-lg flex items-center gap-1.5 active:scale-95"
              >
                <Mic className="w-3.5 h-3.5" />
                Send WhatsApp Voice Note
              </button>

              <button 
                onClick={() => setModalTooth(null)}
                className="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-white text-xs font-semibold rounded-xl border border-slate-700 transition-colors"
              >
                Close
              </button>
            </div>
          </div>
        </div>
      )}

      {/* Global Post-Op Voice Note Modal */}
      <VoiceNoteModal
        isOpen={voiceNoteModalOpen}
        onClose={() => setVoiceNoteModalOpen(false)}
        patient={patient}
        toothNumber={voiceNoteTooth}
        procedure={voiceNoteProcedure}
      />
    </div>
  );
};

export default Test3DOdontogram;
