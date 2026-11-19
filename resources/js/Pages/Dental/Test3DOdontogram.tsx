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

export const CARIES_DEPTH_OPTIONS = [
  { value: '', label: 'Select Depth...' },
  { value: 'D1 - Incipient Enamel', label: 'D1: Incipient Enamel (Non-Cavitated)' },
  { value: 'D2 - Shallow Dentin', label: 'D2: Shallow Dentin (Outer 1/3)' },
  { value: 'D3 - Deep Dentin', label: 'D3: Deep Dentin (Inner 1/3)' },
  { value: 'D4 - Pulp-Approaching', label: 'D4: Pulp-Approaching (<0.5mm RDT)' },
];

export const CARIES_CLASS_OPTIONS = [
  { value: '', label: 'Select G.V. Black Class...' },
  { value: 'Class I', label: 'Class I: Pits & Fissures (Occlusal/B/L)' },
  { value: 'Class II', label: 'Class II: Proximal Posterior (MO/DO/MOD)' },
  { value: 'Class III', label: 'Class III: Proximal Anterior (No Incisal Angle)' },
  { value: 'Class IV', label: 'Class IV: Proximal Anterior with Incisal Angle' },
  { value: 'Class V', label: 'Class V: Gingival / Cervical Third' },
  { value: 'Class VI', label: 'Class VI: Cusp Tip / Incisal Edge' },
];

export const CROWN_MATERIAL_OPTIONS = [
  { value: '', label: 'Select Crown Material...' },
  { value: 'High-Translucency Zirconia', label: 'High-Translucency Zirconia (Monolithic)' },
  { value: 'IPS E-Max Lithium Disilicate', label: 'IPS E-Max Lithium Disilicate' },
  { value: 'Porcelain Fused to High Noble (PFM)', label: 'Porcelain Fused to Metal (PFM)' },
  { value: 'Full Cast Gold Alloy', label: 'Full Cast Gold (Type III)' },
  { value: 'PMMA Long-Term Provisional', label: 'PMMA Long-Term Provisional' },
];

export const CROWN_MARGIN_OPTIONS = [
  { value: '', label: 'Select Margin Fit...' },
  { value: 'Supragingival Chamfer', label: 'Supragingival Chamfer (0.5mm Coronal)' },
  { value: 'Equigingival Shoulder', label: 'Equigingival Shoulder' },
  { value: 'Subgingival Chamfer', label: 'Subgingival Chamfer (0.5mm Sulcus)' },
  { value: 'Open Margin / Microleakage', label: 'Open Margin / Microleakage Present' },
  { value: 'Overcontoured Shelf', label: 'Overcontoured Margin Shelf' },
];

export const PULP_DIAGNOSIS_OPTIONS = [
  { value: '', label: 'Select Pulpal Diagnosis...' },
  { value: 'Normal Vital Pulp', label: 'Normal Vital Pulp' },
  { value: 'Reversible Pulpitis', label: 'Reversible Pulpitis (Sensitivity to Cold)' },
  { value: 'Symptomatic Irreversible Pulpitis', label: 'Symptomatic Irreversible Pulpitis' },
  { value: 'Asymptomatic Irreversible Pulpitis', label: 'Asymptomatic Irreversible Pulpitis' },
  { value: 'Pulpal Necrosis', label: 'Pulpal Necrosis (Non-Vital)' },
  { value: 'Previously Treated', label: 'Previously Treated Canal' },
];

export const PERIAPICAL_DIAGNOSIS_OPTIONS = [
  { value: '', label: 'Select Periapical Diagnosis...' },
  { value: 'Normal Apical Tissues', label: 'Normal Apical Tissues' },
  { value: 'Symptomatic Apical Periodontitis', label: 'Symptomatic Apical Periodontitis' },
  { value: 'Asymptomatic Apical Periodontitis', label: 'Asymptomatic Apical Periodontitis' },
  { value: 'Acute Apical Abscess', label: 'Acute Apical Abscess (Swelling)' },
  { value: 'Chronic Apical Abscess', label: 'Chronic Apical Abscess (Sinus Tract)' },
];

export const CANALS_COUNT_OPTIONS = [
  { value: '', label: 'Select Canal Count...' },
  { value: '1 Canal', label: '1 Canal (Single Straight)' },
  { value: '2 Canals', label: '2 Canals' },
  { value: '3 Canals', label: '3 Canals (MB, DB, Palatal)' },
  { value: '4 Canals', label: '4 Canals (MB1, MB2 Located)' },
];

export const IMPLANT_BRAND_OPTIONS = [
  { value: '', label: 'Select Implant System...' },
  { value: 'Straumann BLX / Roxolid', label: 'Straumann (BLX / Roxolid SLActive)' },
  { value: 'Nobel Biocare Active', label: 'Nobel Biocare (NobelActive Conical)' },
  { value: 'Osstem TSIII SA', label: 'Osstem (TSIII SA / Hiossen ETIII)' },
  { value: 'Zimmer Tapered Screw-Vent', label: 'Zimmer Biomet (TSV Internal Hex)' },
  { value: 'BioHorizons Laser-Lok', label: 'BioHorizons (Tapered Internal Laser-Lok)' },
  { value: 'Megagen AnyRidge', label: 'Megagen (AnyRidge / BlueDiamond)' },
];

export const IMPLANT_DIAMETER_OPTIONS = [
  { value: '', label: 'Select Diameter...' },
  { value: '3.0 mm', label: '3.0 mm (Narrow Platform)' },
  { value: '3.5 mm', label: '3.5 mm (Standard Anterior)' },
  { value: '4.1 mm', label: '4.1 mm (Standard Posterior)' },
  { value: '4.5 mm', label: '4.5 mm (Wide Platform)' },
  { value: '5.0 mm', label: '5.0 mm (Ultra-Wide Molar)' },
];

export const IMPLANT_LENGTH_OPTIONS = [
  { value: '', label: 'Select Length...' },
  { value: '8.0 mm', label: '8.0 mm (Short Fixture)' },
  { value: '10.0 mm', label: '10.0 mm (Standard Length)' },
  { value: '11.5 mm', label: '11.5 mm (Standard Long)' },
  { value: '13.0 mm', label: '13.0 mm (Long Fixture)' },
  { value: '16.0 mm', label: '16.0 mm (Zygomatic / Extended)' },
];

export const EXTRACTION_REASON_OPTIONS = [
  { value: '', label: 'Select Extraction Indication...' },
  { value: 'Non-Restorable Caries', label: 'Severe Non-Restorable Caries' },
  { value: 'Advanced Periodontitis', label: 'Advanced Periodontitis (Grade III Mobility)' },
  { value: 'Vertical Root Fracture', label: 'Vertical Root Fracture / Split' },
  { value: 'Orthodontic Serial', label: 'Serial Orthodontic Extraction' },
  { value: 'Impacted / Pericoronitis', label: 'Impacted Third Molar / Pericoronitis' },
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
            <div className="ml-4 flex items-center bg-slate-950 p-1 rounded-xl border border-slate-800 shadow-inner">
              <button
                onClick={() => setMode('findings')}
                className={`px-3.5 py-1.5 rounded-lg text-xs font-bold transition-all flex items-center gap-1.5 ${mode === 'findings' ? 'bg-indigo-600 text-white shadow' : 'text-slate-400 hover:text-slate-200'}`}
              >
                <span>🦷</span>
                <span>Chart Findings</span>
              </button>
              <button
                onClick={() => setMode('treatment')}
                className={`px-3.5 py-1.5 rounded-lg text-xs font-bold transition-all flex items-center gap-1.5 ${mode === 'treatment' ? 'bg-emerald-600 text-white shadow' : 'text-slate-400 hover:text-slate-200'}`}
              >
                <span>📋</span>
                <span>Plan Treatment</span>
              </button>
              <button
                onClick={() => setMode('pricing')}
                className={`px-3.5 py-1.5 rounded-lg text-xs font-bold transition-all flex items-center gap-1.5 ${mode === 'pricing' ? 'bg-amber-600 text-white shadow' : 'text-slate-400 hover:text-slate-200'}`}
              >
                <span>🏷️</span>
                <span>Procedure Pricing & Settings</span>
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
              onClick={() => setViewMode('clean')}
              className="text-xs px-3 py-1.5 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-lg transition-colors font-medium border border-slate-700 flex items-center gap-1.5 ml-auto"
            >
              <Eye className="w-3.5 h-3.5" />
              Clean 3D
            </button>

            <button 
              onClick={() => window.close()}
              className="text-xs px-3 py-1.5 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-lg transition-colors font-medium border border-slate-700 flex items-center gap-1.5"
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
                {mode === 'findings'
                  ? 'Clinical Findings & Technical Parameters'
                  : mode === 'treatment'
                  ? 'Propose Treatment Procedure'
                  : 'Fee Schedule & Pricing Settings'}
              </label>

              {mode === 'pricing' ? (
                <div className="flex flex-col gap-3.5">
                  <div className="bg-amber-950/20 border border-amber-900/40 p-3 rounded-xl flex flex-col gap-2">
                    <div className="flex items-center justify-between">
                      <span className="text-xs font-bold text-amber-400 uppercase tracking-wider flex items-center gap-1.5">
                        <span>🏷️</span>
                        <span>Procedure Fee Customizer</span>
                      </span>
                      <a
                        href="/admin/1/procedure-codes"
                        target="_blank"
                        rel="noreferrer"
                        className="text-[10px] font-bold px-2 py-0.5 rounded bg-slate-800 hover:bg-slate-700 text-slate-300 border border-slate-700 transition-colors"
                      >
                        Back-Office Catalog ↗
                      </a>
                    </div>
                    <p className="text-[11px] text-slate-400">
                      Customize standard fees and durations. Updates immediately sync to chairside treatment proposals and invoicing.
                    </p>
                    <div className="relative mt-1">
                      <input
                        type="text"
                        value={pricingSearch}
                        onChange={(e) => setPricingSearch(e.target.value)}
                        placeholder="Search code (e.g. D2330) or title..."
                        className="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-1.5 text-xs text-white placeholder-slate-500 focus:outline-none focus:border-amber-500"
                      />
                    </div>
                    <div className="flex flex-wrap gap-1 max-h-20 overflow-y-auto scrollbar-thin scrollbar-thumb-slate-700 pt-1">
                      <button
                        type="button"
                        onClick={() => setPricingCategory('all')}
                        className={`px-2 py-0.5 rounded text-[10px] font-bold transition-colors ${pricingCategory === 'all' ? 'bg-amber-600 text-white' : 'bg-slate-900 text-slate-400 hover:text-white'}`}
                      >
                        All ({procedureList.length})
                      </button>
                      {Array.from(new Set(procedureList.map((p) => p.category?.name).filter(Boolean))).map((catName: any) => (
                        <button
                          key={catName}
                          type="button"
                          onClick={() => setPricingCategory(catName)}
                          className={`px-2 py-0.5 rounded text-[10px] font-bold transition-colors ${pricingCategory === catName ? 'bg-amber-600 text-white' : 'bg-slate-900 text-slate-400 hover:text-white'}`}
                        >
                          {catName}
                        </button>
                      ))}
                    </div>
                  </div>

                  <div className="flex flex-col gap-2 max-h-[460px] overflow-y-auto scrollbar-thin scrollbar-thumb-slate-700 pr-1">
                    {filteredProcedures.map((proc: any) => {
                      const isEditing = editingPriceId === proc.id;
                      const isSuccess = priceUpdateSuccess === proc.id;
                      return (
                        <div
                          key={proc.id}
                          className={`p-2.5 rounded-xl border transition-all ${isSuccess ? 'bg-emerald-950/50 border-emerald-500/70' : 'bg-slate-950/70 border-slate-800 hover:border-slate-700'}`}
                        >
                          <div className="flex items-start justify-between gap-2">
                            <div className="flex-1 min-w-0">
                              <div className="flex items-center gap-1.5 flex-wrap">
                                <span className="px-1.5 py-0.5 rounded font-mono font-bold text-[11px] bg-slate-800 text-amber-300 border border-slate-700">
                                  {proc.code}
                                </span>
                                <span className="text-[10px] text-slate-400 truncate">
                                  {proc.category?.name || 'General'}
                                </span>
                              </div>
                              <div className="text-xs font-semibold text-slate-200 mt-1 line-clamp-2">
                                {proc.title}
                              </div>
                            </div>

                            <div className="text-right shrink-0">
                              {isEditing ? (
                                <div className="flex items-center gap-1">
                                  <span className="text-xs text-slate-400">$</span>
                                  <input
                                    type="number"
                                    step="0.01"
                                    value={editingFee}
                                    onChange={(e) => setEditingFee(e.target.value)}
                                    className="w-16 bg-slate-900 border border-amber-500 rounded px-1.5 py-0.5 text-xs text-white font-bold focus:outline-none"
                                  />
                                  <button
                                    type="button"
                                    onClick={() => handleSavePrice(proc.id)}
                                    className="px-2 py-0.5 bg-emerald-600 hover:bg-emerald-500 text-white rounded text-[11px] font-bold"
                                  >
                                    Save
                                  </button>
                                  <button
                                    type="button"
                                    onClick={() => setEditingPriceId(null)}
                                    className="px-1.5 py-0.5 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded text-[11px]"
                                  >
                                    ✕
                                  </button>
                                </div>
                              ) : (
                                <div className="flex items-center gap-1.5 justify-end">
                                  <div className="text-sm font-extrabold text-emerald-400">
                                    ${Number(proc.standard_fee).toFixed(2)}
                                  </div>
                                  <button
                                    type="button"
                                    onClick={() => handleStartEditPrice(proc)}
                                    className="p-1 rounded bg-slate-900 hover:bg-slate-800 text-slate-400 hover:text-white border border-slate-800 text-[11px] transition-colors"
                                    title="Edit Price"
                                  >
                                    ✏️
                                  </button>
                                </div>
                              )}
                              <div className="text-[10px] text-slate-400 mt-0.5">
                                Est. {proc.estimated_duration_minutes || 30} mins
                              </div>
                            </div>
                          </div>
                        </div>
                      );
                    })}
                  </div>
                </div>
              ) : mode === 'findings' ? (
                <div className="flex flex-col gap-3.5">
                  {/* Clinical Status & Decision Radio */}
                  <div className="bg-slate-950/70 p-3 rounded-xl border border-slate-800">
                    <label className="block text-[11px] font-bold uppercase tracking-wider text-slate-400 mb-2">
                      Clinical Status & Decision
                    </label>
                    <div className="grid grid-cols-3 gap-1.5 p-1 bg-slate-900 rounded-lg border border-slate-800">
                      <button
                        type="button"
                        onClick={() => handleStatusChange('existing')}
                        disabled={!selectedTooth}
                        className={`py-1.5 px-2 rounded text-xs font-bold transition-all text-center disabled:opacity-50 ${currentStatus === 'existing' ? 'bg-emerald-600 text-white shadow' : 'text-slate-400 hover:text-slate-200'}`}
                      >
                        Existing
                      </button>
                      <button
                        type="button"
                        onClick={() => handleStatusChange('needs_treatment')}
                        disabled={!selectedTooth}
                        className={`py-1.5 px-2 rounded text-xs font-bold transition-all text-center disabled:opacity-50 ${currentStatus === 'needs_treatment' ? 'bg-rose-600 text-white shadow animate-pulse' : 'text-slate-400 hover:text-slate-200'}`}
                      >
                        ⚠️ Needs Care
                      </button>
                      <button
                        type="button"
                        onClick={() => handleStatusChange('watch')}
                        disabled={!selectedTooth}
                        className={`py-1.5 px-2 rounded text-xs font-bold transition-all text-center disabled:opacity-50 ${currentStatus === 'watch' ? 'bg-amber-600 text-white shadow' : 'text-slate-400 hover:text-slate-200'}`}
                      >
                        👁️ Watch
                      </button>
                    </div>
                    <p className="text-[10px] text-slate-400 mt-1.5">
                      {currentStatus === 'existing' && '✓ Past clinical history in mouth (unbilled baseline).'}
                      {currentStatus === 'needs_treatment' && '⚠️ Needs treatment. Recorded on the chart; add a procedure to a plan when ready.'}
                      {currentStatus === 'watch' && '👁️ Watch and monitor at next recall exam.'}
                    </p>
                    {suggestion && suggestion.tooth === selectedTooth && currentStatus === 'needs_treatment' && (
                      <div className="mt-2 rounded-lg border border-sky-700/60 bg-sky-950/40 p-3">
                        <p className="text-[10px] font-bold uppercase tracking-wider text-sky-300">Suggested treatment</p>
                        <p className="mt-1 text-sm font-semibold text-white">{suggestion.code} · {suggestion.title}</p>
                        <p className="text-[11px] text-slate-400">{suggestion.reason} Fee: {Number(suggestion.fee).toLocaleString()}</p>
                        <div className="mt-2 flex gap-2">
                          <button
                            type="button"
                            onClick={() => handleTreatmentSelect(suggestion.code, undefined, suggestion.tooth)}
                            className="min-h-[44px] flex-1 rounded-md bg-sky-600 px-3 text-xs font-bold text-white hover:bg-sky-500"
                          >
                            Add to {activePlan ? 'plan' : 'a new plan'}
                          </button>
                          <button
                            type="button"
                            onClick={() => setSuggestion(null)}
                            className="min-h-[44px] rounded-md border border-slate-700 px-3 text-xs font-semibold text-slate-300 hover:text-white"
                          >
                            Dismiss
                          </button>
                        </div>
                      </div>
                    )}
                    {planError && <p className="mt-1.5 text-[11px] text-rose-400">{planError}</p>}
                  </div>

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

                  {/* Deep Clinical Inspector for Active Caries */}
                  {selectedTooth && currentCondition === 'active_caries' && (
                    <div className="bg-rose-950/20 border border-rose-900/40 rounded-xl p-3 flex flex-col gap-2.5">
                      <div className="text-xs font-bold text-rose-400 uppercase tracking-wider flex items-center gap-1.5">
                        <span>🔍</span>
                        <span>Caries Depth & G.V. Black Class</span>
                      </div>
                      <div className="grid grid-cols-2 gap-2">
                        <div>
                          <label className="block text-[11px] font-semibold text-slate-400 mb-1">Caries Depth</label>
                          <select
                            value={currentClinicalDetails.caries_depth || ''}
                            onChange={(e) => handleClinicalDetailFieldChange('caries_depth', e.target.value)}
                            className="w-full bg-slate-950 border border-slate-700 rounded-lg px-2 py-1.5 text-xs text-white focus:outline-none focus:border-rose-500 cursor-pointer"
                          >
                            {CARIES_DEPTH_OPTIONS.map((opt) => (
                              <option key={opt.value} value={opt.value}>{opt.label}</option>
                            ))}
                          </select>
                        </div>
                        <div>
                          <label className="block text-[11px] font-semibold text-slate-400 mb-1">G.V. Black Class</label>
                          <select
                            value={currentClinicalDetails.caries_class || ''}
                            onChange={(e) => handleClinicalDetailFieldChange('caries_class', e.target.value)}
                            className="w-full bg-slate-950 border border-slate-700 rounded-lg px-2 py-1.5 text-xs text-white focus:outline-none focus:border-rose-500 cursor-pointer"
                          >
                            {CARIES_CLASS_OPTIONS.map((opt) => (
                              <option key={opt.value} value={opt.value}>{opt.label}</option>
                            ))}
                          </select>
                        </div>
                      </div>
                    </div>
                  )}

                  {/* Deep Clinical Inspector for Crown */}
                  {selectedTooth && currentCondition === 'crown' && (
                    <div className="bg-amber-950/20 border border-amber-900/40 rounded-xl p-3 flex flex-col gap-2.5">
                      <div className="text-xs font-bold text-amber-400 uppercase tracking-wider flex items-center gap-1.5">
                        <span>👑</span>
                        <span>Crown Material & Margin Fit</span>
                      </div>
                      <div className="grid grid-cols-2 gap-2">
                        <div>
                          <label className="block text-[11px] font-semibold text-slate-400 mb-1">Material</label>
                          <select
                            value={currentClinicalDetails.crown_material || ''}
                            onChange={(e) => handleClinicalDetailFieldChange('crown_material', e.target.value)}
                            className="w-full bg-slate-950 border border-slate-700 rounded-lg px-2 py-1.5 text-xs text-white focus:outline-none focus:border-amber-500 cursor-pointer"
                          >
                            {CROWN_MATERIAL_OPTIONS.map((opt) => (
                              <option key={opt.value} value={opt.value}>{opt.label}</option>
                            ))}
                          </select>
                        </div>
                        <div>
                          <label className="block text-[11px] font-semibold text-slate-400 mb-1">Margin Fit</label>
                          <select
                            value={currentClinicalDetails.crown_margin_fit || ''}
                            onChange={(e) => handleClinicalDetailFieldChange('crown_margin_fit', e.target.value)}
                            className="w-full bg-slate-950 border border-slate-700 rounded-lg px-2 py-1.5 text-xs text-white focus:outline-none focus:border-amber-500 cursor-pointer"
                          >
                            {CROWN_MARGIN_OPTIONS.map((opt) => (
                              <option key={opt.value} value={opt.value}>{opt.label}</option>
                            ))}
                          </select>
                        </div>
                      </div>
                    </div>
                  )}

                  {/* Deep Clinical Inspector for Root Canal */}
                  {selectedTooth && currentCondition === 'root_canal' && (
                    <div className="bg-purple-950/20 border border-purple-900/40 rounded-xl p-3 flex flex-col gap-2.5">
                      <div className="text-xs font-bold text-purple-400 uppercase tracking-wider flex items-center gap-1.5">
                        <span>⚡</span>
                        <span>Endo Pulp & Canal Configuration</span>
                      </div>
                      <div className="grid grid-cols-2 gap-2">
                        <div>
                          <label className="block text-[11px] font-semibold text-slate-400 mb-1">Pulp Diagnosis</label>
                          <select
                            value={currentClinicalDetails.pulp_diagnosis || ''}
                            onChange={(e) => handleClinicalDetailFieldChange('pulp_diagnosis', e.target.value)}
                            className="w-full bg-slate-950 border border-slate-700 rounded-lg px-2 py-1.5 text-xs text-white focus:outline-none focus:border-purple-500 cursor-pointer"
                          >
                            {PULP_DIAGNOSIS_OPTIONS.map((opt) => (
                              <option key={opt.value} value={opt.value}>{opt.label}</option>
                            ))}
                          </select>
                        </div>
                        <div>
                          <label className="block text-[11px] font-semibold text-slate-400 mb-1">Canals Count</label>
                          <select
                            value={currentClinicalDetails.canals_count || ''}
                            onChange={(e) => handleClinicalDetailFieldChange('canals_count', e.target.value)}
                            className="w-full bg-slate-950 border border-slate-700 rounded-lg px-2 py-1.5 text-xs text-white focus:outline-none focus:border-purple-500 cursor-pointer"
                          >
                            {CANALS_COUNT_OPTIONS.map((opt) => (
                              <option key={opt.value} value={opt.value}>{opt.label}</option>
                            ))}
                          </select>
                        </div>
                      </div>
                      <div>
                        <label className="block text-[11px] font-semibold text-slate-400 mb-1">Periapical Diagnosis</label>
                        <select
                          value={currentClinicalDetails.periapical_diagnosis || ''}
                          onChange={(e) => handleClinicalDetailFieldChange('periapical_diagnosis', e.target.value)}
                          className="w-full bg-slate-950 border border-slate-700 rounded-lg px-2 py-1.5 text-xs text-white focus:outline-none focus:border-purple-500 cursor-pointer"
                        >
                          {PERIAPICAL_DIAGNOSIS_OPTIONS.map((opt) => (
                            <option key={opt.value} value={opt.value}>{opt.label}</option>
                          ))}
                        </select>
                      </div>
                    </div>
                  )}

                  {/* Deep Clinical Inspector for Implant */}
                  {selectedTooth && currentCondition === 'implant' && (
                    <div className="bg-cyan-950/20 border border-cyan-900/40 rounded-xl p-3 flex flex-col gap-2.5">
                      <div className="text-xs font-bold text-cyan-400 uppercase tracking-wider flex items-center gap-1.5">
                        <span>🔩</span>
                        <span>Implant Brand & Dimensions</span>
                      </div>
                      <div>
                        <label className="block text-[11px] font-semibold text-slate-400 mb-1">Fixture Brand</label>
                        <select
                          value={currentClinicalDetails.implant_brand || ''}
                          onChange={(e) => handleClinicalDetailFieldChange('implant_brand', e.target.value)}
                          className="w-full bg-slate-950 border border-slate-700 rounded-lg px-2 py-1.5 text-xs text-white focus:outline-none focus:border-cyan-500 cursor-pointer"
                        >
                          {IMPLANT_BRAND_OPTIONS.map((opt) => (
                            <option key={opt.value} value={opt.value}>{opt.label}</option>
                          ))}
                        </select>
                      </div>
                      <div className="grid grid-cols-2 gap-2">
                        <div>
                          <label className="block text-[11px] font-semibold text-slate-400 mb-1">Diameter</label>
                          <select
                            value={currentClinicalDetails.implant_diameter || ''}
                            onChange={(e) => handleClinicalDetailFieldChange('implant_diameter', e.target.value)}
                            className="w-full bg-slate-950 border border-slate-700 rounded-lg px-2 py-1.5 text-xs text-white focus:outline-none focus:border-cyan-500 cursor-pointer"
                          >
                            {IMPLANT_DIAMETER_OPTIONS.map((opt) => (
                              <option key={opt.value} value={opt.value}>{opt.label}</option>
                            ))}
                          </select>
                        </div>
                        <div>
                          <label className="block text-[11px] font-semibold text-slate-400 mb-1">Length</label>
                          <select
                            value={currentClinicalDetails.implant_length || ''}
                            onChange={(e) => handleClinicalDetailFieldChange('implant_length', e.target.value)}
                            className="w-full bg-slate-950 border border-slate-700 rounded-lg px-2 py-1.5 text-xs text-white focus:outline-none focus:border-cyan-500 cursor-pointer"
                          >
                            {IMPLANT_LENGTH_OPTIONS.map((opt) => (
                              <option key={opt.value} value={opt.value}>{opt.label}</option>
                            ))}
                          </select>
                        </div>
                      </div>
                    </div>
                  )}

                  {/* Deep Clinical Inspector for Missing Tooth */}
                  {selectedTooth && currentCondition === 'missing' && (
                    <div className="bg-slate-950 border border-slate-800 rounded-xl p-3 flex flex-col gap-2.5">
                      <div className="text-xs font-bold text-slate-400 uppercase tracking-wider flex items-center gap-1.5">
                        <span>🚫</span>
                        <span>Extraction Indication</span>
                      </div>
                      <div>
                        <label className="block text-[11px] font-semibold text-slate-400 mb-1">Reason</label>
                        <select
                          value={currentClinicalDetails.extraction_reason || ''}
                          onChange={(e) => handleClinicalDetailFieldChange('extraction_reason', e.target.value)}
                          className="w-full bg-slate-900 border border-slate-700 rounded-lg px-2 py-1.5 text-xs text-white focus:outline-none focus:border-slate-500 cursor-pointer"
                        >
                          {EXTRACTION_REASON_OPTIONS.map((opt) => (
                            <option key={opt.value} value={opt.value}>{opt.label}</option>
                          ))}
                        </select>
                      </div>
                    </div>
                  )}

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
