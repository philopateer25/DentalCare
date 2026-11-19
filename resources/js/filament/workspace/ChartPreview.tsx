import React, { Suspense, useEffect, useRef, useState } from 'react';
import { CONDITION_COLORS, CONDITION_LABELS } from '../../Components/Dental3D/types';
import { http, errorMessage, type WorkspaceData } from './api';
import ToothMap from './ToothMap';

const Viewer3DPanel = React.lazy(() => import('./Viewer3DPanel'));

const STATUS: Record<string, string> = { existing: 'Existing', needs_treatment: 'Needs care', watch: 'Watch' };
const condLabel = (c: string) => (CONDITION_LABELS as any)[c]?.label || c.replace('_', ' ');

/**
 * Read-only chart preview for the patient profile's "3D Mouth Chart" tab (no iframe).
 * Loads the 3D engine only once the tab is actually visible, so a hidden tab never starts
 * WebGL at 0×0. Editing happens in the Chart workspace.
 */
export default function ChartPreview({ config }: { config: any }) {
  const ref = useRef<HTMLDivElement>(null);
  const [visible, setVisible] = useState(false);
  const [data, setData] = useState<WorkspaceData | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [selected, setSelected] = useState('11');

  // Wait until the tab is shown (offsetWidth > 0) before loading anything.
  useEffect(() => {
    const t = setInterval(() => {
      if (ref.current && ref.current.offsetWidth > 0) {
        setVisible(true);
        clearInterval(t);
      }
    }, 250);
    return () => clearInterval(t);
  }, []);

  useEffect(() => {
    if (!visible || data) return;
    http.get(config.dataUrl).then((r) => setData(r.data)).catch((e) => setError(errorMessage(e, 'Could not load the chart.')));
  }, [visible]); // eslint-disable-line react-hooks/exhaustive-deps

  const records = data?.records || {};
  const procs = (data?.procedures_by_tooth as any) || {};
  const rec = records[selected];
  const toothProcs: any[] = procs[selected] || [];
  const all = Object.values(records);
  const needs = all.filter((r) => r.status === 'needs_treatment').length;
  const counts = all.reduce((a, r) => { if (r.condition !== 'healthy') a[r.condition] = (a[r.condition] || 0) + 1; return a; }, {} as Record<string, number>);

  return (
    <div ref={ref} className="dxw-root flex flex-col gap-3 text-slate-900 dark:text-slate-100">
      <div className="flex flex-wrap items-center justify-between gap-2">
        <div className="flex flex-wrap gap-1.5">
          {needs > 0 && <span className="rounded-full bg-brand-red px-2.5 py-1 text-[11px] font-bold text-white">{needs} need treatment</span>}
          {Object.entries(counts).map(([c, n]) => (
            <span key={c} className="flex items-center gap-1.5 rounded-full bg-slate-100 px-2.5 py-1 text-[11px] font-semibold dark:bg-slate-800">
              <span className="h-2.5 w-2.5 rounded-full" style={{ backgroundColor: (CONDITION_COLORS as any)[c] }} />{n} {condLabel(c)}
            </span>
          ))}
          {data && needs === 0 && Object.keys(counts).length === 0 && <span className="rounded-full bg-slate-100 px-2.5 py-1 text-[11px] font-semibold text-slate-500 dark:bg-slate-800 dark:text-slate-400">No findings charted</span>}
        </div>
        <div className="flex gap-2">
          <a href={`${config.chartUrl}?view=perio`} className="flex min-h-[40px] items-center rounded-lg bg-slate-100 px-3 text-xs font-bold text-brand-deep hover:bg-slate-200 dark:bg-slate-800 dark:text-indigo-200">Gum chart</a>
          <a href={`${config.chartUrl}?tooth=${selected}`} className="flex min-h-[40px] items-center rounded-lg bg-brand-deep px-3 text-xs font-bold text-white hover:bg-brand">Open dental chart</a>
        </div>
      </div>

      {error && <p className="text-xs font-semibold text-brand-red">{error}</p>}

      <div className="grid gap-3 lg:grid-cols-[minmax(0,1fr)_260px]">
        <div className="dxw-viewer relative h-[52vh] min-h-[360px] overflow-hidden rounded-xl bg-slate-950">
          {visible && data && config.has3d ? (
            <Suspense fallback={<div className="grid h-full place-items-center text-sm text-slate-400">Loading 3D engine…</div>}>
              <Viewer3DPanel teethRecords={records as any} plannedProcedures={procs} selectedTooth={selected} onSelectTooth={setSelected} onVoiceNote={() => {}} modelUrl={config.modelUrl} />
            </Suspense>
          ) : (
            <div className="grid h-full place-items-center text-sm text-slate-400">{config.has3d ? 'Loading chart…' : '3D view is not enabled for this clinic.'}</div>
          )}
        </div>

        <div className="rounded-xl bg-slate-50 p-3 text-sm dark:bg-slate-800/60">
          <div className="flex items-center justify-between gap-2">
            <p className="text-base font-bold">Tooth #{selected}</p>
            {rec && <span className="rounded-full bg-white px-2 py-0.5 text-[11px] font-bold dark:bg-slate-900">{STATUS[rec.status] || rec.status}</span>}
          </div>
          <p className="mt-0.5 flex items-center gap-1.5 text-xs text-slate-600 dark:text-slate-300">
            <span className="h-2.5 w-2.5 rounded-full" style={{ backgroundColor: (CONDITION_COLORS as any)[rec?.condition || 'healthy'] }} />
            {condLabel(rec?.condition || 'healthy')}{rec?.clinical_details?.diagnosis ? ` · ${rec.clinical_details.diagnosis}` : ''}
          </p>
          {rec?.notes && <p className="mt-2 text-xs text-slate-500 dark:text-slate-400">{rec.notes}</p>}
          <p className="mt-3 text-[10px] font-bold uppercase tracking-wider text-slate-500">Procedures</p>
          {toothProcs.length === 0 ? <p className="text-xs text-slate-500">None.</p> : (
            <ul className="mt-1 grid gap-1">
              {toothProcs.map((p) => (
                <li key={p.id} className="flex justify-between gap-2 rounded-md bg-white px-2 py-1.5 text-xs dark:bg-slate-900">
                  <span className="min-w-0 truncate"><b>{p.procedure_code?.code}</b> {p.procedure_code?.title}</span>
                  <span className={`shrink-0 font-bold ${p.status === 'completed' ? 'text-brand-teal' : 'text-brand'}`}>{p.status === 'completed' ? 'Done' : 'Planned'}</span>
                </li>
              ))}
            </ul>
          )}
        </div>
      </div>

      <ToothMap records={records} proceduresByTooth={procs} selected={selected} onSelect={setSelected} />
    </div>
  );
}
