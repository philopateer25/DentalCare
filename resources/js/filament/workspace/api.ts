import axios from 'axios';

/** Same-origin JSON client. Session auth; axios sends the XSRF-TOKEN cookie as X-XSRF-TOKEN. */
export const http = axios.create({
  headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
  withCredentials: true,
});

export type Status = 'existing' | 'needs_treatment' | 'watch';

export interface ChartRecord {
  condition: string;
  status: Status;
  clinical_details: Record<string, any>;
  notes: string | null;
  surfaces: Record<string, string>;
}

export interface Suggestion { tooth: string; code: string; title: string; fee: number; reason: string }

export interface WorkspaceData {
  patient: { id: number; name: string; file_number?: string; gender?: string; age?: number; phone?: string; blood_type?: string };
  alerts: { label: string; tone: 'danger' | 'warning' }[];
  examinations: { id: number; type: string; examined_at: string | null; created_at: string }[];
  current_exam_id: number | null;
  records: Record<string, ChartRecord>;
  procedures_by_tooth: Record<string, any[]>;
  plans: any[];
  procedure_codes: { id: number; code: string; title: string; standard_fee: string | number; category?: { name: string } }[];
  can_edit_pricing: boolean;
}

export const errorMessage = (err: any, fallback: string) =>
  err?.response?.data?.message || err?.message || fallback;
