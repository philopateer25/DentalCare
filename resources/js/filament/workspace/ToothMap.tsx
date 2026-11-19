import React from 'react';
import { CONDITION_COLORS } from '../../Components/Dental3D/types';
import type { ChartRecord } from './api';

const UPPER = ['18', '17', '16', '15', '14', '13', '12', '11', '21', '22', '23', '24', '25', '26', '27', '28'];
const LOWER = ['48', '47', '46', '45', '44', '43', '42', '41', '31', '32', '33', '34', '35', '36', '37', '38'];

interface Props {
  records: Record<string, ChartRecord>;
  proceduresByTooth: Record<string, any[]>;
  selected: string | null;
  onSelect: (tooth: string) => void;
  large?: boolean; // 2D mode: bigger cells, replaces the 3D view
}

/** FDI tooth map: tap to select; colour = condition, dot = status, ring = open procedures. */
export default function ToothMap({ records, proceduresByTooth, selected, onSelect, large }: Props) {
  const cell = (tooth: string, upper: boolean) => {
    const rec = records[tooth];
    const cond = rec?.condition || 'healthy';
    const color = (CONDITION_COLORS as any)[cond] || '#e5e7eb';
    const open = (proceduresByTooth[tooth] || []).some((p) => p.status !== 'completed');
    const isSel = selected === tooth;
    const missing = cond === 'missing';
    return (
      <button
        key={tooth}
        type="button"
        onClick={() => onSelect(tooth)}
        aria-pressed={isSel}
        aria-label={`Tooth ${tooth}`}
        className={`relative flex flex-col items-center justify-center gap-1 rounded-lg border transition-colors ${
          large ? 'min-h-[72px] min-w-[44px]' : 'min-h-[52px] min-w-[38px]'
        } ${isSel ? 'border-brand bg-brand/10 ring-2 ring-brand' : 'border-slate-200 bg-white hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:hover:bg-slate-800'}`}
      >
        {upper && <span className="text-[10px] font-bold text-slate-500 dark:text-slate-400">{tooth}</span>}
        <span
          className={`block rounded-md ${large ? 'h-7 w-6' : 'h-5 w-4'} ${missing ? 'border-2 border-dashed border-slate-400 bg-transparent' : ''} ${open ? 'ring-2 ring-sky-500 ring-offset-1 dark:ring-offset-slate-900' : ''}`}
          style={missing ? undefined : { backgroundColor: color, boxShadow: 'inset 0 0 0 1px rgba(0,0,0,.12)' }}
        />
        {!upper && <span className="text-[10px] font-bold text-slate-500 dark:text-slate-400">{tooth}</span>}
        {rec?.status === 'needs_treatment' && <span className="absolute right-1 top-1 h-2 w-2 rounded-full bg-brand-red" />}
        {rec?.status === 'watch' && <span className="absolute right-1 top-1 h-2 w-2 rounded-full bg-amber-500" />}
      </button>
    );
  };

  return (
    <div className="overflow-x-auto">
      <div className="mx-auto grid w-max gap-2">
        <div className="flex items-end gap-1">
          {UPPER.slice(0, 8).map((t) => cell(t, true))}
          <span className="mx-1 h-12 w-px self-center bg-slate-300 dark:bg-slate-600" />
          {UPPER.slice(8).map((t) => cell(t, true))}
        </div>
        <div className="flex items-start gap-1">
          {LOWER.slice(0, 8).map((t) => cell(t, false))}
          <span className="mx-1 h-12 w-px self-center bg-slate-300 dark:bg-slate-600" />
          {LOWER.slice(8).map((t) => cell(t, false))}
        </div>
      </div>
    </div>
  );
}
