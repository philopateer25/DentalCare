export type ToothCondition = 
  | 'healthy'
  | 'active_caries'
  | 'composite_filled'
  | 'crown'
  | 'root_canal'
  | 'missing'
  | 'implant'
  | 'custom';

export const CONDITION_COLORS: Record<ToothCondition, string> = {
  healthy: '#E8E8E8',
  active_caries: '#DC2626',
  composite_filled: '#3B82F6',
  crown: '#EAB308',
  root_canal: '#9333EA',
  missing: '#4B5563',
  implant: '#06B6D4',
  custom: '#F43F5E', // Rose
};

export const CONDITION_LABELS: Record<ToothCondition, { label: string; description: string; badgeBg: string }> = {
  healthy: {
    label: 'Healthy',
    description: 'Normal anatomical condition',
    badgeBg: 'bg-stone-200 text-stone-800 border-stone-300',
  },
  active_caries: {
    label: 'Active Caries',
    description: 'Active tooth decay / cavity',
    badgeBg: 'bg-red-500/15 text-red-600 border-red-500/30',
  },
  composite_filled: {
    label: 'Composite Filled',
    description: 'Restored with composite filling',
    badgeBg: 'bg-blue-500/15 text-blue-600 border-blue-500/30',
  },
  crown: {
    label: 'Crown',
    description: 'Full coverage dental crown',
    badgeBg: 'bg-amber-500/15 text-amber-600 border-amber-500/30',
  },
  root_canal: {
    label: 'Root Canal',
    description: 'Endodontically treated tooth',
    badgeBg: 'bg-purple-500/15 text-purple-600 border-purple-500/30',
  },
  missing: {
    label: 'Missing',
    description: 'Extracted or missing tooth',
    badgeBg: 'bg-gray-500/15 text-gray-400 border-gray-500/30',
  },
  implant: {
    label: 'Implant',
    description: 'Dental fixture / titanium post',
    badgeBg: 'bg-cyan-500/15 text-cyan-600 border-cyan-500/30',
  },
  custom: {
    label: 'Custom Note',
    description: 'Custom doctor specification',
    badgeBg: 'bg-rose-500/15 text-rose-600 border-rose-500/30',
  },
};

export interface ToothData {
  toothNumber: string;
  condition: ToothCondition;
  notes?: string;
}

export type SurfaceCondition = 'healthy' | 'active_caries' | 'composite_filled';

export interface ToothSurfaces {
  mesial?: SurfaceCondition;
  occlusal?: SurfaceCondition;
  distal?: SurfaceCondition;
  buccal?: SurfaceCondition;
  lingual?: SurfaceCondition;
}

export interface ToothRecord {
  condition: ToothCondition; // The main condition
  surfaces?: ToothSurfaces;
  notes?: string;
}

export interface PatientInfo {
  id: number;
  full_name: string;
  file_number: string;
  gender?: string;
  dob?: string;
}
