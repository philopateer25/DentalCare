/**
 * Clinical option lists shared by the Chart workspace and the legacy odontogram page.
 * (Moved out of Pages/Dental/Test3DOdontogram.tsx so the workspace can use them without
 * loading the 3D engine.)
 */

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
