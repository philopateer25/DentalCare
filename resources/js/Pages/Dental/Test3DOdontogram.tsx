import React, { useState } from 'react';
import axios from 'axios';
import Dental3DViewer, { ArchMode, CameraPreset } from '../../Components/Dental3D/Dental3DViewer';
import { SingleToothViewer } from '../../Components/Dental3D/SingleToothViewer';
import VoiceNoteModal from '../../Components/Dental3D/VoiceNoteModal';
import ChartOverlay from '../../Components/Dental3D/ChartOverlay';
import { Mic, Eye, EyeOff, Crosshair, Camera } from 'lucide-react';
import {
  CONDITION_COLORS,
  CONDITION_LABELS,
  PatientInfo,
  ToothRecord,
  SurfaceCondition,
  ToothSurfaces,
  ToothCondition,
} from '../../Components/Dental3D/types';
import { ToothSurfaceKey } from '../../Components/Dental3D/dentitionManifest';

interface Props {
  patient: PatientInfo;
  examination: { id: number; type: string; examined_at: string; notes: string | null };
  initialRecords?: Record<string, ToothRecord>;
  initialProcedures?: Record<string, any[]>;
  procedureCategories?: any[];
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

import {
  DIAGNOSIS_OPTIONS,
  SEVERITY_OPTIONS,
  URGENCY_OPTIONS,
  MOBILITY_OPTIONS,
  PLAN_TEMPLATES,
  CARIES_DEPTH_OPTIONS,
  CARIES_CLASS_OPTIONS,
  CROWN_MATERIAL_OPTIONS,
  CROWN_MARGIN_OPTIONS,
  PULP_DIAGNOSIS_OPTIONS,
  PERIAPICAL_DIAGNOSIS_OPTIONS,
  CANALS_COUNT_OPTIONS,
  IMPLANT_BRAND_OPTIONS,
  IMPLANT_DIAMETER_OPTIONS,
  IMPLANT_LENGTH_OPTIONS,
  EXTRACTION_REASON_OPTIONS,
  PROCEDURE_CATEGORIES,
  ANESTHESIA_OPTIONS,
  DOSAGE_OPTIONS,
  TECHNIQUE_OPTIONS,
  MATERIAL_OPTIONS,
} from '../../Components/Dental3D/clinicalOptions';
export {
  DIAGNOSIS_OPTIONS,
  SEVERITY_OPTIONS,
  URGENCY_OPTIONS,
  MOBILITY_OPTIONS,
  PLAN_TEMPLATES,
  CARIES_DEPTH_OPTIONS,
  CARIES_CLASS_OPTIONS,
  CROWN_MATERIAL_OPTIONS,
  CROWN_MARGIN_OPTIONS,
  PULP_DIAGNOSIS_OPTIONS,
  PERIAPICAL_DIAGNOSIS_OPTIONS,
  CANALS_COUNT_OPTIONS,
  IMPLANT_BRAND_OPTIONS,
  IMPLANT_DIAMETER_OPTIONS,
  IMPLANT_LENGTH_OPTIONS,
  EXTRACTION_REASON_OPTIONS,
  PROCEDURE_CATEGORIES,
  ANESTHESIA_OPTIONS,
  DOSAGE_OPTIONS,
  TECHNIQUE_OPTIONS,
  MATERIAL_OPTIONS,
};

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
  procedureCategories = [],
  initialViewMode = 'detailed',
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
  // The exam currently being charted: the one the server opened, or one created in this session.
  // Other exams in the timeline are shown read-only.
  const [currentExamId, setCurrentExamId] = useState<number>(initialExam?.id);
  const [examinations, setExaminations] = useState<any[]>([initialExam]);

  const [mode, setMode] = useState<'findings' | 'treatment' | 'pricing'>('findings');
  const [procedureList, setProcedureList] = useState<any[]>([]);
  const [pricingSearch, setPricingSearch] = useState('');
  const [pricingCategory, setPricingCategory] = useState<string>('all');
  const [editingPriceId, setEditingPriceId] = useState<number | null>(null);
  const [editingFee, setEditingFee] = useState<string>('');
  const [editingDuration, setEditingDuration] = useState<string>('');
  const [priceUpdateSuccess, setPriceUpdateSuccess] = useState<number | null>(null);

  const [activePlan, setActivePlan] = useState<any>(null);
  const [allPlans, setAllPlans] = useState<any[]>([]);
  const [plannedProcedures, setPlannedProcedures] = useState<Record<string, any[]>>(initialProcedures);
  const [isCreatingPlan, setIsCreatingPlan] = useState(false);
  // Suggested treatment returned by the server when a tooth is marked "needs treatment".
  // Nothing is added to a plan until the doctor confirms it.
  const [suggestion, setSuggestion] = useState<{ tooth: string; code: string; title: string; fee: number; reason: string } | null>(null);
  const [planError, setPlanError] = useState<string | null>(null);
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
    // Fetch procedure catalog
    axios.get('/api/procedure-codes').then(res => {
      setProcedureList(res.data);
    }).catch(err => console.error('Failed to load procedure codes', err));

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
        // No plan yet: nothing is created on view. A plan is created when the doctor
        // adds the first procedure (see ensurePlan).
        setAllPlans([]);
        setActivePlan(null);
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

  /** Returns the active plan, creating a draft plan only when the doctor adds the first procedure. */
  const ensurePlan = async (): Promise<any> => {
    if (activePlan) return activePlan;
    const res = await axios.post(`/api/patients/${patient.id}/treatment-plans`, { title: 'Comprehensive Treatment Plan' });
    setAllPlans((prev) => [res.data, ...prev]);
    setActivePlan(res.data);
    return res.data;
  };

  const handleTreatmentSelect = async (procedureCode: string, details?: {
    anesthetic_agent?: string;
    anesthetic_dosage?: string;
    anesthetic_technique?: string;
    materials_used_json?: string[];
  }, toothOverride?: string) => {
    const toothNum = toothOverride || selectedTooth;
    if (!toothNum) return;
    setSavingStatus('saving');
    setPlanError(null);
    try {
      const plan = await ensurePlan();
      const res = await axios.post(`/api/treatment-plans/${plan.id}/procedures`, {
        tooth_number_fdi: toothNum,
        procedure_code: procedureCode,
        anesthetic_agent: details?.anesthetic_agent,
        anesthetic_dosage: details?.anesthetic_dosage,
        anesthetic_technique: details?.anesthetic_technique,
        materials_used_json: details?.materials_used_json,
      });
      // Update local state
      setPlannedProcedures(prev => ({
        ...prev,
        [toothNum]: [...(prev[toothNum] || []), res.data.procedure]
      }));
      if (res.data.plan) setActivePlan(res.data.plan);
      if (suggestion?.tooth === toothNum && suggestion.code === procedureCode) setSuggestion(null);
      setSavingStatus('saved');
      notifyParentWindow(toothNum);
      setTimeout(() => setSavingStatus('idle'), 2000);
    } catch (err: any) {
      console.error('Failed to add treatment', err);
      setPlanError(err?.response?.data?.message || 'Could not add the procedure.');
      setSavingStatus('error');
    }
  };

  const handleTreatmentComplete = async (procedureId: number, toothNumber: string) => {
    setSavingStatus('saving');
    try {
      // In a real app, you'd want a dedicated endpoint like PUT /api/procedures/{id}/complete
      // For now, we simulate an API call or assume the backend accepts a PATCH.
      // But since we don't have that endpoint in this demo, let's create a quick API endpoint in web.php.
      const res = await axios.patch(`/api/procedures/${procedureId}/complete`);
      // Reflect the resulting condition (decided server-side by CDT code) and clear "needs treatment".
      setTeethRecords(prev => ({
        ...prev,
        [toothNumber]: {
          ...(prev[toothNumber] || {}),
          condition: res.data?.condition || prev[toothNumber]?.condition || 'healthy',
          status: 'existing',
        } as ToothRecord,
      }));
      
      // Update local state
      setPlannedProcedures(prev => {
        const procs = prev[toothNumber] || [];
        return {
          ...prev,
          [toothNumber]: procs.map(p => p.id === procedureId ? { ...p, status: 'completed' } : p)
        };
      });
      axios.get(`/api/patients/${patient.id}/treatment-plans`).then((plansRes) => {
        setAllPlans(plansRes.data);
        const current = plansRes.data.find((pl: any) => pl.id === activePlan?.id) || plansRes.data[0] || null;
        setActivePlan(current);
      }).catch(() => {});
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
      setCurrentExamId(res.data.id);
      // The new exam starts from the previous chart (carried forward server-side).
      const chart = await axios.get(`/api/examinations/${res.data.id}/odontogram`);
      setTeethRecords(chart.data && !Array.isArray(chart.data) ? chart.data : {});
    } catch (err) {
      console.error('Failed to create examination', err);
    }
  };

  const currentRecord = selectedTooth ? teethRecords[selectedTooth] : undefined;
  const currentCondition: ToothCondition = currentRecord?.condition || 'healthy';
  const currentStatus: 'existing' | 'needs_treatment' | 'watch' = (currentRecord?.status as any) || 'existing';
  const currentClinicalDetails: Record<string, any> = currentRecord?.clinical_details || {};
  const currentNote = currentRecord?.notes || '';
  const currentSurfaces = currentRecord?.surfaces || {};
  const currentMedicalDetails = parseMedicalDetails(currentNote);

  const saveToothFinding = async (
    toothNum: string,
    condition: ToothCondition,
    status: 'existing' | 'needs_treatment' | 'watch',
    clinicalDetails: Record<string, any>,
    surfaces: ToothSurfaces,
    notes: string
  ) => {
    const previousState = { ...teethRecords };
    const updatedRecord: ToothRecord = {
      ...currentRecord,
      condition,
      status,
      clinical_details: clinicalDetails,
      surfaces,
      notes,
    };

    setTeethRecords((prev) => ({
      ...prev,
      [toothNum]: updatedRecord,
    }));

    setSavingStatus('saving');

    try {
      const res = await axios.post(`/api/examinations/${activeExam.id}/findings`, {
        tooth_number: toothNum,
        condition,
        status,
        clinical_details: clinicalDetails,
        surfaces,
        notes,
      });
      const alreadyPlanned = (plannedProcedures[toothNum] || []).some(
        (p: any) => p.status !== 'completed' && p.procedure_code?.code === res.data?.suggestion?.code
      );
      setSuggestion(res.data?.suggestion && !alreadyPlanned ? { tooth: toothNum, ...res.data.suggestion } : null);
      setSavingStatus('saved');
      notifyParentWindow(toothNum, condition);
      setTimeout(() => setSavingStatus('idle'), 2000);
    } catch (err) {
      console.error('Failed to update tooth finding:', err);
      setTeethRecords(previousState);
      setSavingStatus('error');
    }
  };

  const handleStatusChange = (status: 'existing' | 'needs_treatment' | 'watch') => {
    if (!selectedTooth) return;
    saveToothFinding(selectedTooth, currentCondition, status, currentClinicalDetails, currentSurfaces, currentNote);
  };

  const handleConditionSelect = (condition: ToothCondition) => {
    if (!selectedTooth) return;
    saveToothFinding(selectedTooth, condition, currentStatus, currentClinicalDetails, currentSurfaces, currentNote);
  };

  const handleClinicalDetailFieldChange = (key: string, value: string) => {
    if (!selectedTooth) return;
    const updated = { ...currentClinicalDetails, [key]: value };
    saveToothFinding(selectedTooth, currentCondition, currentStatus, updated, currentSurfaces, currentNote);
  };

  const handleMedicalDetailChange = async (field: keyof MedicalDetails, value: string) => {
    if (!selectedTooth) return;
    const updatedDetails = { ...currentMedicalDetails, [field]: value };
    const newNote = formatMedicalDetails(updatedDetails);
    saveToothFinding(selectedTooth, currentCondition, currentStatus, currentClinicalDetails, currentSurfaces, newNote);
  };

  const handleNoteSave = async (newNote: string) => {
    if (!selectedTooth) return;
    saveToothFinding(selectedTooth, currentCondition, currentStatus, currentClinicalDetails, currentSurfaces, newNote);
  };

  const handleSurfaceSelect = async (surface: keyof ToothSurfaces, condition: SurfaceCondition) => {
    if (!selectedTooth) return;
    const currentSurfaceCondition = currentSurfaces[surface];
    const newCondition = currentSurfaceCondition === condition ? 'healthy' : condition;
    const newSurfaces = { ...currentSurfaces, [surface]: newCondition };
    saveToothFinding(selectedTooth, currentCondition, currentStatus, currentClinicalDetails, newSurfaces, currentNote);
  };

  const handleStartEditPrice = (proc: any) => {
    setEditingPriceId(proc.id);
    setEditingFee(String(proc.standard_fee));
    setEditingDuration(String(proc.estimated_duration_minutes || ''));
  };

  const handleSavePrice = async (procId: number) => {
    const feeNum = parseFloat(editingFee);
    if (isNaN(feeNum) || feeNum < 0) return;
    try {
      await axios.patch(`/api/procedure-codes/${procId}`, {
        standard_fee: feeNum,
        estimated_duration_minutes: editingDuration ? parseInt(editingDuration) : undefined,
      });
      setProcedureList((prev) =>
        prev.map((p) =>
          p.id === procId
            ? {
                ...p,
                standard_fee: feeNum,
                estimated_duration_minutes: editingDuration ? parseInt(editingDuration) : p.estimated_duration_minutes,
              }
            : p
        )
      );
      setEditingPriceId(null);
      setPriceUpdateSuccess(procId);
      setTimeout(() => setPriceUpdateSuccess(null), 2500);
    } catch (err) {
      console.error('Failed to update fee', err);
    }
  };

  const filteredProcedures = procedureList.filter((p) => {
    const matchCat = pricingCategory === 'all' || p.category?.name === pricingCategory;
    const q = pricingSearch.toLowerCase().trim();
    const matchQ =
      !q ||
      p.code.toLowerCase().includes(q) ||
      p.title.toLowerCase().includes(q) ||
      (p.category?.name && p.category.name.toLowerCase().includes(q));
    return matchCat && matchQ;
  });

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
        
        {/* Phase 1 + 2 overlay: summary/legend, tooth quick actions, exam timeline, plan panel. */}
        <ChartOverlay
          patientId={patient.id}
          teethRecords={teethRecords}
          plannedProcedures={plannedProcedures}
          selectedTooth={selectedTooth}
          procedureList={procedureList}
          activePlan={activePlan}
          examinations={examinations}
          activeExam={activeExam}
          currentExamId={currentExamId}
          suggestion={suggestion}
          planError={planError}
          savingStatus={savingStatus}
          onSetCondition={handleConditionSelect}
          onSetStatus={handleStatusChange}
          onAddProcedure={(code, tooth) => handleTreatmentSelect(code, undefined, tooth)}
          onComplete={handleTreatmentComplete}
          onVoiceNote={(tooth, proc) => handleOpenVoiceNote(tooth, proc)}
          onExamChange={handleExamChange}
          onDismissSuggestion={() => setSuggestion(null)}
        />

        {/* Hovering Action Button (hidden when embedded in the Filament tab, which shows its own button) */}
        <div className={`absolute bottom-6 right-6 z-10 ${new URLSearchParams(window.location.search).get('embed') === '1' ? 'hidden' : ''}`}>
          <a 
            href={`/patients/${patient.id}/odontogram-details`}
            target="_blank"
            rel="noopener noreferrer"
            className="flex items-center gap-2 px-5 py-3 bg-slate-900/90 hover:bg-slate-800 text-white font-bold rounded-xl shadow-2xl border border-slate-700 backdrop-blur-md transition-all hover:scale-105 active:scale-95"
          >
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className="text-emerald-400"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
            Open Clinical Details
          </a>
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

  // The detailed editing screen that used to live here was retired: it is replaced by the
  // Chart workspace (Filament: Patients -> Chart). This page now only serves the clean 3D
  // view used by the 360 history widget's single-tooth viewer and its full-screen link.
  return null;
};

export default Test3DOdontogram;
