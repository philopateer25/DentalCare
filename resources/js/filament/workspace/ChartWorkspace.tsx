import React, { Suspense, useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { CONDITION_COLORS, CONDITION_LABELS, SURFACE_CONDITION_COLORS } from '../../Components/Dental3D/types';
import VoiceNoteModal from '../../Components/Dental3D/VoiceNoteModal';
import {
  DIAGNOSIS_OPTIONS, SEVERITY_OPTIONS, URGENCY_OPTIONS, MOBILITY_OPTIONS,
  CARIES_DEPTH_OPTIONS, CARIES_CLASS_OPTIONS, CROWN_MATERIAL_OPTIONS, CROWN_MARGIN_OPTIONS,
  PULP_DIAGNOSIS_OPTIONS, PERIAPICAL_DIAGNOSIS_OPTIONS, CANALS_COUNT_OPTIONS,
  IMPLANT_BRAND_OPTIONS, IMPLANT_DIAMETER_OPTIONS, IMPLANT_LENGTH_OPTIONS, EXTRACTION_REASON_OPTIONS,
} from '../../Components/Dental3D/clinicalOptions';
import { http, errorMessage, type ChartRecord, type Status, type Suggestion, type WorkspaceData } from './api';
import ToothMap from './ToothMap';
import SurfaceDiagram, { SURFACE_OPTIONS } from './SurfaceDiagram';
import PerioChart from './PerioChart';

const Viewer3DPanel = React.lazy(() => import('./Viewer3DPanel'));

type Tab = 'findings' | 'diagnosis' | 'treatment' | 'history';

const CONDITIONS = ['healthy', 'active_caries', 'composite_filled', 'root_canal', 'crown', 'missing', 'implant', 'custom'];
const STATUS_META: Record<Status, { label: string; on: string }> = {
  existing: { label: 'Existing', on: 'bg-brand-teal text-white' },
  needs_treatment: { label: 'Needs care', on: 'bg-brand-red text-white' },
  watch: { label: 'Watch', on: 'bg-amber-500 text-white' },
};

/** Condition-specific technical fields (stored in clinical_details). */
const TECH_FIELDS: Record<string, { key: string; label: string; options: { value: string; label: string }[] }[]> = {
  active_caries: [
    { key: 'caries_depth', label: 'Caries depth', options: CARIES_DEPTH_OPTIONS },
    { key: 'caries_class', label: 'G.V. Black class', options: CARIES_CLASS_OPTIONS },
  ],
  crown: [
    { key: 'crown_material', label: 'Crown material', options: CROWN_MATERIAL_OPTIONS },
    { key: 'crown_margin_fit', label: 'Margin fit', options: CROWN_MARGIN_OPTIONS },
  ],
  root_canal: [
    { key: 'pulp_diagnosis', label: 'Pulp diagnosis', options: PULP_DIAGNOSIS_OPTIONS },
    { key: 'periapical_diagnosis', label: 'Periapical diagnosis', options: PERIAPICAL_DIAGNOSIS_OPTIONS },
    { key: 'canals_count', label: 'Canals', options: CANALS_COUNT_OPTIONS },
  ],
  implant: [
    { key: 'implant_brand', label: 'Implant system', options: IMPLANT_BRAND_OPTIONS },
    { key: 'implant_diameter', label: 'Diameter', options: IMPLANT_DIAMETER_OPTIONS },
    { key: 'implant_length', label: 'Length', options: IMPLANT_LENGTH_OPTIONS },
  ],
  missing: [{ key: 'extraction_reason', label: 'Reason missing', options: EXTRACTION_REASON_OPTIONS }],
};

const DIAGNOSIS_FIELDS = [
  { key: 'diagnosis', label: 'Clinical diagnosis', options: DIAGNOSIS_OPTIONS },
  { key: 'severity', label: 'Severity / stage', options: SEVERITY_OPTIONS },
  { key: 'urgency', label: 'Urgency', options: URGENCY_OPTIONS },
  { key: 'mobility', label: 'Mobility grade', options: MOBILITY_OPTIONS },
];

const EMPTY: ChartRecord = { condition: 'healthy', status: 'existing', clinical_details: {}, notes: null, surfaces: {} };
const money = (n: any) => Number(n || 0).toLocaleString(undefined, { maximumFractionDigits: 2 });
const fmtDate = (d?: string | null) => (d ? new Date(d).toLocaleDateString(undefined, { day: 'numeric', month: 'short', year: 'numeric' }) : '');
const condLabel = (c: string) => (CONDITION_LABELS as any)[c]?.label || c.replace('_', ' ');

const card = 'rounded-xl bg-white shadow-[0_1px_2px_rgba(15,23,42,.05),0_0_0_1px_rgba(15,23,42,.04)] dark:bg-slate-900 dark:shadow-[0_0_0_1px_rgba(255,255,255,.06)]';
const label = 'text-[10px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400';
const select = 'min-h-[40px] w-full rounded-lg border border-slate-200 bg-slate-50 px-2.5 text-sm text-slate-900 focus:border-brand focus:outline-none dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100';

function prefers2D(): boolean {
  try {
    const saved = localStorage.getItem('dxw-view');
    if (saved === '2d' || saved === '3d') return saved === '2d';
  } catch { /* storage unavailable */ }
  const mem = (navigator as any).deviceMemory;
  return typeof mem === 'number' && mem <= 2; // light mode for older tablets
}

export default function ChartWorkspace({ config }: { config: any }) {
  const [data, setData] = useState<WorkspaceData | null>(null);
  const [loadError, setLoadError] = useState<string | null>(null);
  const [records, setRecords] = useState<Record<string, ChartRecord>>({});
  const [procs, setProcs] = useState<Record<string, any[]>>({});
  const [plans, setPlans] = useState<any[]>([]);
  const [exams, setExams] = useState<WorkspaceData['examinations']>([]);
  const [currentExamId, setCurrentExamId] = useState<number | null>(null);
  const [viewExamId, setViewExamId] = useState<number | null>(null);
  const [selected, setSelected] = useState<string>(() => new URLSearchParams(location.search).get('tooth') || '11');
  const [tab, setTab] = useState<Tab>('findings');
  const [view, setView] = useState<'teeth' | 'perio'>(() => (new URLSearchParams(location.search).get('view') === 'perio' ? 'perio' : 'teeth'));
  const [mode2d, setMode2d] = useState<boolean>(() => !config.has3d || prefers2D());
  const [brush, setBrush] = useState('active_caries');
  const [saving, setSaving] = useState<'idle' | 'saving' | 'saved' | 'error'>('idle');
  const [message, setMessage] = useState<string | null>(null);
  const [suggestion, setSuggestion] = useState<Suggestion | null>(null);
  const [procQuery, setProcQuery] = useState('');
  const [voice, setVoice] = useState<{ tooth: string; proc?: any } | null>(null);
  const [history, setHistory] = useState<Record<number, Record<string, ChartRecord>>>({});

  const timers = useRef<Record<string, any>>({});
  const pending = useRef<Record<string, ChartRecord>>({});

  // ---------- Load ----------
  const load = useCallback(async () => {
    try {
      const res = await http.get(config.dataUrl);
      const d: WorkspaceData = res.data;
      setData(d);
      setRecords(d.records || {});
      setProcs((d.procedures_by_tooth as any) || {});
      setPlans(d.plans || []);
      setExams(d.examinations || []);
      setCurrentExamId(d.current_exam_id);
      setViewExamId(d.current_exam_id);
    } catch (err) {
      setLoadError(errorMessage(err, 'Could not load the chart.'));
    }
  }, [config.dataUrl]);

  useEffect(() => { load(); }, [load]);

  const readOnly = viewExamId !== null && viewExamId !== currentExamId;
  const activePlan = useMemo(() => plans.find((p) => !['completed', 'cancelled', 'rejected'].includes(p.status)) || null, [plans]);
  const rec = records[selected] || EMPTY;

  // ---------- Saving (debounced per tooth: several quick edits = one request) ----------
  const flush = useCallback(async (tooth: string) => {
    const record = pending.current[tooth];
    if (!record || !currentExamId) return;
    delete pending.current[tooth];
    setSaving('saving');
    try {
      const res = await http.post(`/api/examinations/${currentExamId}/findings`, {
        tooth_number: tooth,
        condition: record.condition,
        status: record.status,
        clinical_details: record.clinical_details,
        surfaces: record.surfaces,
        notes: record.notes,
      });
      const s = res.data?.suggestion;
      const planned = (procs[tooth] || []).some((p) => p.status !== 'completed' && p.procedure_code?.code === s?.code);
      setSuggestion(s && !planned ? { tooth, ...s } : (prev) => (prev?.tooth === tooth ? null : prev));
      setSaving('saved');
      setMessage(null);
    } catch (err) {
      setSaving('error');
      setMessage(errorMessage(err, 'Saving failed. Your last change was not stored.'));
    }
  }, [currentExamId, procs]);

  const update = (patch: Partial<ChartRecord>, immediate = false) => {
    if (readOnly) return;
    const tooth = selected;
    const next: ChartRecord = { ...EMPTY, ...records[tooth], ...patch };
    setRecords((prev) => ({ ...prev, [tooth]: next }));
    pending.current[tooth] = next;
    clearTimeout(timers.current[tooth]);
    setSaving('saving');
    timers.current[tooth] = setTimeout(() => flush(tooth), immediate ? 0 : 700);
  };

  // Flush pending edits when leaving the page.
  useEffect(() => {
    const handler = () => Object.keys(pending.current).forEach((t) => flush(t));
    window.addEventListener('beforeunload', handler);
    return () => window.removeEventListener('beforeunload', handler);
  }, [flush]);

  // ---------- Findings logic ----------
  const setCondition = (condition: string) => update({ condition }, true);
  const setStatus = (status: Status) => update({ status }, true);
  const paintSurface = (surface: string) => {
    const surfaces = { ...rec.surfaces, [surface]: rec.surfaces[surface] === brush ? 'healthy' : brush };
    // Keep the tooth condition consistent with its surfaces: a healthy tooth with a carious or
    // restored surface is upgraded (never downgraded automatically).
    let condition = rec.condition;
    if (condition === 'healthy') {
      if (Object.values(surfaces).includes('active_caries')) condition = 'active_caries';
      else if (Object.values(surfaces).some((v) => ['composite_filled', 'amalgam'].includes(v))) condition = 'composite_filled';
    }
    update({ surfaces, condition });
  };
  const setDetail = (key: string, value: string) => update({ clinical_details: { ...rec.clinical_details, [key]: value } });

  // ---------- Treatment ----------
  const ensurePlan = async () => {
    if (activePlan) return activePlan;
    const res = await http.post(`/api/patients/${config.patientId}/treatment-plans`, { title: 'Comprehensive Treatment Plan' });
    setPlans((prev) => [res.data, ...prev]);
    return res.data;
  };

  const addProcedure = async (code: string, tooth = selected) => {
    setSaving('saving');
    try {
      const plan = await ensurePlan();
      const res = await http.post(`/api/treatment-plans/${plan.id}/procedures`, { tooth_number_fdi: Number(tooth), procedure_code: code });
      setProcs((prev) => ({ ...prev, [tooth]: [...(prev[tooth] || []), res.data.procedure] }));
      if (res.data.plan) setPlans((prev) => prev.map((p) => (p.id === res.data.plan.id ? res.data.plan : p)).concat(prev.some((p) => p.id === res.data.plan.id) ? [] : [res.data.plan]));
      if (suggestion?.tooth === tooth && suggestion.code === code) setSuggestion(null);
      setProcQuery('');
      setSaving('saved');
    } catch (err) {
      setSaving('error');
      setMessage(errorMessage(err, 'Could not add the procedure.'));
    }
  };

  const completeProcedure = async (procId: number, tooth: string) => {
    setSaving('saving');
    try {
      const res = await http.patch(`/api/procedures/${procId}/complete`);
      setProcs((prev) => ({ ...prev, [tooth]: (prev[tooth] || []).map((p) => (p.id === procId ? { ...p, status: 'completed' } : p)) }));
      setRecords((prev) => ({
        ...prev,
        [tooth]: { ...EMPTY, ...prev[tooth], condition: res.data?.condition || prev[tooth]?.condition || 'healthy', status: 'existing' },
      }));
      const [plansRes, chartRes] = await Promise.all([
        http.get(`/api/patients/${config.patientId}/treatment-plans`),
        currentExamId ? http.get(`/api/examinations/${currentExamId}/odontogram`, { params: { normalised: 1 } }) : Promise.resolve(null),
      ]);
      setPlans(plansRes.data);
      // Reload the chart: completion can change surfaces too (e.g. caries -> composite).
      if (chartRes && viewExamId === currentExamId) setRecords(chartRes.data || {});
      setSaving('saved');
    } catch (err) {
      setSaving('error');
      setMessage(errorMessage(err, 'Could not complete the procedure.'));
    }
  };

  // ---------- Exams ----------
  const viewExam = async (id: number) => {
    if (id === viewExamId) return;
    const res = await http.get(`/api/examinations/${id}/odontogram`, { params: { normalised: 1 } });
    setRecords(res.data || {});
    setViewExamId(id);
    setSuggestion(null);
  };

  const newExam = async () => {
    if (!confirm('Start a new examination? The current chart is carried forward and you update what changed.')) return;
    const res = await http.post(`/api/patients/${config.patientId}/examinations`, { type: 'periodic' });
    setExams((prev) => [res.data, ...prev]);
    setCurrentExamId(res.data.id);
    const chart = await http.get(`/api/examinations/${res.data.id}/odontogram`, { params: { normalised: 1 } });
    setRecords(chart.data || {});
    setViewExamId(res.data.id);
    setHistory({});
  };

  // History: this tooth across all exams (loaded on demand).
  useEffect(() => {
    if (tab !== 'history') return;
    exams.forEach((e) => {
      if (history[e.id]) return;
      http.get(`/api/examinations/${e.id}/odontogram`, { params: { normalised: 1 } })
        .then((r) => setHistory((h) => ({ ...h, [e.id]: r.data || {} })))
        .catch(() => {});
    });
  }, [tab, exams]); // eslint-disable-line react-hooks/exhaustive-deps

  const toggle2d = () => {
    setMode2d((v) => {
      try { localStorage.setItem('dxw-view', !v ? '2d' : '3d'); } catch { /* ignore */ }
      return !v;
    });
  };

  // ---------- Derived ----------
  const allRecs = Object.values(records);
  const needsCare = allRecs.filter((r) => r.status === 'needs_treatment').length;
  const watchCount = allRecs.filter((r) => r.status === 'watch').length;
  const counts = allRecs.reduce((acc, r) => { acc[r.condition] = (acc[r.condition] || 0) + 1; return acc; }, {} as Record<string, number>);
  const charted = allRecs.filter((r) => r.condition !== 'healthy').length;
  const planProcs = (activePlan?.phases || []).flatMap((ph: any) => ph.procedures || []);
  const planTotal = planProcs.reduce((s: number, p: any) => s + Number(p.net_amount ?? p.fee ?? 0), 0);
  const planDone = planProcs.filter((p: any) => p.status === 'completed').reduce((s: number, p: any) => s + Number(p.net_amount ?? p.fee ?? 0), 0);
  const toothProcs = procs[selected] || [];
  const codes = data?.procedure_codes || [];
  const matches = procQuery.trim().length >= 2
    ? codes.filter((c) => (c.code + ' ' + c.title).toLowerCase().includes(procQuery.trim().toLowerCase())).slice(0, 8)
    : [];
  const viewer3dRecords = useMemo(() => records as any, [records]);

  if (loadError) return <div className={`${card} p-6 text-sm text-brand-red`}>{loadError}</div>;
  if (!data) return <div className="dxw-skeleton">Loading dental chart…</div>;

  const p = data.patient;

  return (
    <div className="dxw-root flex flex-col gap-4 text-slate-900 dark:text-slate-100">
      {/* ===== Header ===== */}
      <div className={`${card} relative overflow-hidden p-4`}>
        <div className="absolute inset-x-0 top-0 h-1 bg-gradient-to-r from-brand-deep via-brand-teal to-brand-red" />
        <div className="flex flex-wrap items-start justify-between gap-3">
          <div className="min-w-0">
            <a href={config.patientUrl} className="text-xs font-semibold text-brand hover:underline dark:text-indigo-300">← Patient file</a>
            <h1 className="mt-0.5 text-[22px] font-bold leading-tight tracking-tight">Dental chart · {p.name}</h1>
            <p className="mt-0.5 flex flex-wrap gap-x-3 text-xs text-slate-500 dark:text-slate-400">
              {p.file_number && <span>#{p.file_number}</span>}
              {(p.gender || p.age) && <span>{[p.gender && p.gender[0].toUpperCase() + p.gender.slice(1), p.age && `${p.age} yrs`].filter(Boolean).join(', ')}</span>}
              {p.blood_type && <span>Blood {p.blood_type}</span>}
            </p>
            {data.alerts.length > 0 && (
              <div className="mt-2 flex flex-wrap gap-1.5">
                {data.alerts.map((a) => (
                  <span key={a.label} className={`rounded-full px-2 py-0.5 text-[11px] font-bold ${a.tone === 'danger' ? 'bg-red-100 text-red-800 dark:bg-red-950 dark:text-red-200' : 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-200'}`}>
                    ⚠ {a.label}
                  </span>
                ))}
              </div>
            )}
          </div>
          <div className="flex flex-wrap items-center gap-2">
            <div className="flex rounded-lg bg-slate-100 p-1 dark:bg-slate-800">
              {([['teeth', 'Teeth'], ['perio', 'Gum chart']] as const).map(([v, l]) => (
                <button key={v} type="button" onClick={() => setView(v)}
                  className={`min-h-[34px] rounded-md px-3 text-xs font-bold ${view === v ? 'bg-white text-brand-deep shadow dark:bg-slate-950 dark:text-indigo-200' : 'text-slate-600 dark:text-slate-300'}`}>
                  {l}
                </button>
              ))}
            </div>
            <span className={`text-xs font-semibold ${saving === 'error' ? 'text-brand-red' : saving === 'saving' ? 'text-slate-500' : 'text-brand-teal'}`}>
              {saving === 'saving' ? 'Saving…' : saving === 'error' ? 'Not saved' : saving === 'saved' ? '✓ Saved' : ''}
            </span>
            {config.has3d && view === 'teeth' && (
              <button type="button" onClick={toggle2d} className="min-h-[40px] rounded-lg bg-slate-100 px-3 text-xs font-bold text-brand-deep hover:bg-slate-200 dark:bg-slate-800 dark:text-indigo-200">
                {mode2d ? '3D view' : '2D view (light)'}
              </button>
            )}
            <a href={config.printUrl} target="_blank" rel="noopener" className="flex min-h-[40px] items-center rounded-lg bg-brand-deep px-3 text-xs font-bold text-white hover:bg-brand">
              Export PDF
            </a>
          </div>
        </div>

        {/* Exam timeline */}
        <div className="mt-3 flex items-center gap-1.5 overflow-x-auto">
          <span className={label}>Exams</span>
          {[...exams].sort((a, b) => a.id - b.id).map((e) => (
            <button
              key={e.id}
              type="button"
              onClick={() => viewExam(e.id)}
              className={`min-h-[34px] shrink-0 rounded-lg px-2.5 text-xs font-semibold ${e.id === viewExamId ? 'bg-brand text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-300'}`}
            >
              {fmtDate(e.examined_at || e.created_at)} · {e.type}{e.id === currentExamId ? ' (current)' : ''}
            </button>
          ))}
          <button type="button" onClick={newExam} className="min-h-[34px] shrink-0 rounded-lg border border-dashed border-brand px-2.5 text-xs font-bold text-brand dark:border-indigo-400 dark:text-indigo-300">
            + New exam
          </button>
        </div>
        {readOnly && (
          <p className="mt-2 rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-800 dark:bg-amber-950 dark:text-amber-200">
            Viewing a past exam (read-only).{' '}
            <button type="button" className="font-bold underline" onClick={() => currentExamId && viewExam(currentExamId)}>Back to current exam</button>
          </p>
        )}
        {message && <p className="mt-2 text-xs font-semibold text-brand-red">{message}</p>}
      </div>

      {view === 'perio' && (
        <div className={`${card} p-4`}>
          <PerioChart patientId={config.patientId} records={records} />
        </div>
      )}

      {/* ===== Body ===== */}
      <div className={`grid gap-4 lg:grid-cols-[minmax(0,1fr)_400px] ${view === 'perio' ? 'hidden' : ''}`}>
        {/* Chart */}
        <div className="flex min-w-0 flex-col gap-4">
          <div className={`${card} flex flex-wrap gap-1.5 p-3`}>
            {needsCare > 0 && <span className="rounded-full bg-brand-red px-2.5 py-1 text-[11px] font-bold text-white">{needsCare} need treatment</span>}
            {watchCount > 0 && <span className="rounded-full bg-amber-500 px-2.5 py-1 text-[11px] font-bold text-white">{watchCount} watch</span>}
            {CONDITIONS.filter((c) => c !== 'healthy' && counts[c]).map((c) => (
              <span key={c} className="flex items-center gap-1.5 rounded-full bg-slate-100 px-2.5 py-1 text-[11px] font-semibold dark:bg-slate-800">
                <span className="h-2.5 w-2.5 rounded-full" style={{ backgroundColor: (CONDITION_COLORS as any)[c] }} />
                {counts[c]} {condLabel(c)}
              </span>
            ))}
            <span className="rounded-full bg-slate-100 px-2.5 py-1 text-[11px] font-semibold text-slate-500 dark:bg-slate-800 dark:text-slate-400">
              {32 - charted} healthy / not charted
            </span>
          </div>

          {!mode2d && (
            <div className={`${card} relative h-[58vh] min-h-[380px] overflow-hidden !bg-slate-950`}>
              <Suspense fallback={<div className="grid h-full place-items-center text-sm text-slate-400">Loading 3D engine…</div>}>
                <Viewer3DPanel
                  teethRecords={viewer3dRecords}
                  plannedProcedures={procs}
                  selectedTooth={selected}
                  onSelectTooth={setSelected}
                  onVoiceNote={(tooth, proc) => setVoice({ tooth, proc })}
                  modelUrl={config.modelUrl}
                />
              </Suspense>
            </div>
          )}

          <div className={`${card} p-3`}>
            <div className="mb-2 flex items-center justify-between">
              <span className={label}>Tooth map (FDI)</span>
              <span className="text-[11px] text-slate-500 dark:text-slate-400">Red dot = needs care · amber = watch · blue ring = planned</span>
            </div>
            <ToothMap records={records} proceduresByTooth={procs} selected={selected} onSelect={setSelected} large={mode2d} />
          </div>
        </div>

        {/* Inspector */}
        <div className={`${card} flex min-w-0 flex-col self-start lg:sticky lg:top-20`}>
          <div className="flex items-center justify-between gap-2 border-b border-slate-100 p-4 dark:border-slate-800">
            <div className="flex items-center gap-2">
              <span className="h-4 w-4 rounded" style={{ backgroundColor: (CONDITION_COLORS as any)[rec.condition] }} />
              <div>
                <p className="text-lg font-bold leading-tight">Tooth #{selected}</p>
                <p className="text-xs text-slate-500 dark:text-slate-400">{condLabel(rec.condition)}</p>
              </div>
            </div>
            <span className={`rounded-full px-2.5 py-1 text-[11px] font-bold ${STATUS_META[rec.status].on}`}>{STATUS_META[rec.status].label}</span>
          </div>

          <div className="grid grid-cols-4 gap-1 border-b border-slate-100 p-2 dark:border-slate-800">
            {(['findings', 'diagnosis', 'treatment', 'history'] as Tab[]).map((t) => (
              <button
                key={t}
                type="button"
                onClick={() => setTab(t)}
                className={`min-h-[40px] rounded-lg text-xs font-bold capitalize ${tab === t ? 'bg-brand text-white' : 'text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800'}`}
              >
                {t}{t === 'treatment' && toothProcs.some((x) => x.status !== 'completed') ? ' •' : ''}
              </button>
            ))}
          </div>

          <div className="flex flex-col gap-4 p-4">
            {tab === 'findings' && (
              <>
                <div>
                  <p className={label}>Status</p>
                  <div className="mt-1.5 grid grid-cols-3 gap-1">
                    {(Object.keys(STATUS_META) as Status[]).map((s) => (
                      <button key={s} type="button" disabled={readOnly} onClick={() => setStatus(s)}
                        className={`min-h-[44px] rounded-lg text-xs font-bold disabled:opacity-50 ${rec.status === s ? STATUS_META[s].on : 'bg-slate-100 text-slate-600 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-300'}`}>
                        {STATUS_META[s].label}
                      </button>
                    ))}
                  </div>
                </div>

                {suggestion && suggestion.tooth === selected && rec.status === 'needs_treatment' && !readOnly && (
                  <div className="rounded-lg border border-sky-200 bg-sky-50 p-3 dark:border-sky-900 dark:bg-sky-950/60">
                    <p className="text-[10px] font-bold uppercase tracking-wider text-sky-700 dark:text-sky-300">Suggested treatment</p>
                    <p className="mt-0.5 text-sm font-semibold">{suggestion.code} · {suggestion.title}</p>
                    <p className="text-xs text-slate-600 dark:text-slate-400">{suggestion.reason} Fee {money(suggestion.fee)}</p>
                    <div className="mt-2 flex gap-1.5">
                      <button type="button" onClick={() => addProcedure(suggestion.code, suggestion.tooth)} className="min-h-[40px] flex-1 rounded-lg bg-sky-600 text-xs font-bold text-white hover:bg-sky-500">
                        Add to {activePlan ? 'plan' : 'a new plan'}
                      </button>
                      <button type="button" onClick={() => setSuggestion(null)} className="min-h-[40px] rounded-lg px-3 text-xs font-semibold text-slate-600 hover:bg-white dark:text-slate-300 dark:hover:bg-slate-800">Dismiss</button>
                    </div>
                  </div>
                )}

                <div>
                  <p className={label}>Condition</p>
                  <div className="mt-1.5 grid grid-cols-4 gap-1">
                    {CONDITIONS.map((c) => (
                      <button key={c} type="button" disabled={readOnly} onClick={() => setCondition(c)} title={(CONDITION_LABELS as any)[c]?.description}
                        className={`flex min-h-[52px] flex-col items-center justify-center gap-1 rounded-lg border text-[11px] font-semibold disabled:opacity-50 ${rec.condition === c ? 'border-brand bg-brand/10 text-brand-deep dark:text-indigo-200' : 'border-slate-200 hover:bg-slate-50 dark:border-slate-700 dark:hover:bg-slate-800'}`}>
                        <span className="h-3 w-3 rounded-full" style={{ backgroundColor: (CONDITION_COLORS as any)[c] }} />
                        {condLabel(c).split(' ')[0]}
                      </button>
                    ))}
                  </div>
                </div>

                <div>
                  <p className={label}>Surfaces</p>
                  <div className="mt-1.5 flex flex-wrap items-center gap-3">
                    <SurfaceDiagram tooth={selected} surfaces={rec.surfaces || {}} brush={brush} disabled={readOnly} onPaint={paintSurface} />
                    <div className="flex flex-1 flex-col gap-1">
                      <span className="text-[11px] text-slate-500 dark:text-slate-400">Pick, then tap a surface:</span>
                      {SURFACE_OPTIONS.map((o) => (
                        <button key={o.value} type="button" disabled={readOnly} onClick={() => setBrush(o.value)}
                          className={`flex min-h-[34px] items-center gap-2 rounded-lg px-2 text-xs font-semibold disabled:opacity-50 ${brush === o.value ? 'bg-slate-900 text-white dark:bg-white dark:text-slate-900' : 'hover:bg-slate-100 dark:hover:bg-slate-800'}`}>
                          <span className="h-3 w-3 rounded-sm border border-black/10" style={{ backgroundColor: (SURFACE_CONDITION_COLORS as any)[o.value] }} />
                          {o.label}
                        </button>
                      ))}
                    </div>
                  </div>
                </div>

                {(TECH_FIELDS[rec.condition] || []).length > 0 && (
                  <div className="grid gap-2">
                    <p className={label}>{condLabel(rec.condition)} details</p>
                    {TECH_FIELDS[rec.condition].map((f) => (
                      <label key={f.key} className="grid gap-1 text-xs font-semibold text-slate-600 dark:text-slate-300">
                        {f.label}
                        <select className={select} disabled={readOnly} value={rec.clinical_details?.[f.key] || ''} onChange={(e) => setDetail(f.key, e.target.value)}>
                          {f.options.map((o) => <option key={o.value} value={o.value}>{o.label}</option>)}
                        </select>
                      </label>
                    ))}
                  </div>
                )}

                <label className="grid gap-1">
                  <span className={label}>Notes</span>
                  <textarea
                    rows={3}
                    disabled={readOnly}
                    value={rec.notes || ''}
                    onChange={(e) => update({ notes: e.target.value })}
                    placeholder="Free-text clinical notes for this tooth"
                    className={`${select} min-h-[84px] py-2`}
                  />
                </label>
              </>
            )}

            {tab === 'diagnosis' && (
              <div className="grid gap-3">
                {DIAGNOSIS_FIELDS.map((f) => (
                  <label key={f.key} className="grid gap-1 text-xs font-semibold text-slate-600 dark:text-slate-300">
                    {f.label}
                    <select className={select} disabled={readOnly} value={rec.clinical_details?.[f.key] || ''} onChange={(e) => setDetail(f.key, e.target.value)}>
                      {f.options.map((o) => <option key={o.value} value={o.value}>{o.label}</option>)}
                    </select>
                  </label>
                ))}
                <p className="text-[11px] text-slate-500 dark:text-slate-400">Stored as structured data, separate from the tooth notes.</p>
              </div>
            )}

            {tab === 'treatment' && (
              <>
                <div>
                  <p className={label}>Procedures on #{selected}</p>
                  {toothProcs.length === 0 && <p className="mt-1 text-xs text-slate-500">None yet.</p>}
                  <ul className="mt-1.5 grid gap-1.5">
                    {toothProcs.map((x) => (
                      <li key={x.id} className="flex items-center justify-between gap-2 rounded-lg bg-slate-50 px-3 py-2 text-xs dark:bg-slate-800/70">
                        <span className="min-w-0">
                          <b>{x.procedure_code?.code}</b> {x.procedure_code?.title}
                          <span className="block text-slate-500">{money(x.net_amount ?? x.fee)}</span>
                        </span>
                        {x.status === 'completed' ? (
                          <span className="shrink-0 rounded-full bg-emerald-100 px-2 py-0.5 font-bold text-emerald-800 dark:bg-emerald-950 dark:text-emerald-200">Done</span>
                        ) : (
                          <span className="flex shrink-0 gap-1">
                            <button type="button" title="Post-op voice note" onClick={() => setVoice({ tooth: selected, proc: x })} className="min-h-[36px] rounded-lg bg-slate-200 px-2 dark:bg-slate-700">🎙</button>
                            <button type="button" onClick={() => completeProcedure(x.id, selected)} className="min-h-[36px] rounded-lg bg-brand-teal px-3 font-bold text-white">Done</button>
                          </span>
                        )}
                      </li>
                    ))}
                  </ul>
                </div>

                <div className="relative">
                  <p className={label}>Add procedure</p>
                  <input value={procQuery} onChange={(e) => setProcQuery(e.target.value)} placeholder="Search code or name…" className={`${select} mt-1.5`} />
                  {matches.length > 0 && (
                    <ul className="absolute inset-x-0 top-full z-20 mt-1 max-h-60 overflow-y-auto rounded-lg border border-slate-200 bg-white shadow-xl dark:border-slate-700 dark:bg-slate-900">
                      {matches.map((m) => (
                        <li key={m.id}>
                          <button type="button" onClick={() => addProcedure(m.code)} className="flex min-h-[44px] w-full items-center justify-between gap-2 px-3 text-left text-xs hover:bg-slate-50 dark:hover:bg-slate-800">
                            <span className="min-w-0 truncate"><b>{m.code}</b> {m.title}</span>
                            <span className="shrink-0 text-slate-500">{money(m.standard_fee)}</span>
                          </button>
                        </li>
                      ))}
                    </ul>
                  )}
                </div>

                <div className="rounded-lg bg-slate-50 p-3 dark:bg-slate-800/60">
                  <p className="text-sm font-bold">{activePlan ? activePlan.title : 'No active plan'}</p>
                  {activePlan ? (
                    <>
                      <div className="mt-2 grid grid-cols-3 gap-1.5 text-center text-xs">
                        <div><p className="text-slate-500">Total</p><p className="font-bold">{money(planTotal)}</p></div>
                        <div><p className="text-slate-500">Done</p><p className="font-bold text-brand-teal">{money(planDone)}</p></div>
                        <div><p className="text-slate-500">Remaining</p><p className="font-bold">{money(planTotal - planDone)}</p></div>
                      </div>
                      <div className="mt-2 h-1.5 overflow-hidden rounded-full bg-slate-200 dark:bg-slate-700">
                        <div className="h-full bg-brand-teal" style={{ width: `${planTotal ? (planDone / planTotal) * 100 : 0}%` }} />
                      </div>
                    </>
                  ) : (
                    <p className="mt-1 text-xs text-slate-500">Adding the first procedure starts a draft plan.</p>
                  )}
                </div>
                {data.can_edit_pricing && config.pricingUrl && (
                  <a href={config.pricingUrl} className="text-xs font-semibold text-brand hover:underline dark:text-indigo-300">Edit fee schedule (admin) →</a>
                )}
              </>
            )}

            {tab === 'history' && (
              <ul className="grid gap-2">
                {[...exams].sort((a, b) => b.id - a.id).map((e) => {
                  const r = history[e.id]?.[selected];
                  return (
                    <li key={e.id} className="rounded-lg bg-slate-50 px-3 py-2 text-xs dark:bg-slate-800/60">
                      <p className="font-bold">{fmtDate(e.examined_at || e.created_at)} · {e.type}{e.id === currentExamId ? ' (current)' : ''}</p>
                      {!history[e.id] ? <p className="text-slate-500">Loading…</p> : r ? (
                        <p className="text-slate-600 dark:text-slate-300">
                          {condLabel(r.condition)} · {STATUS_META[r.status as Status]?.label || r.status}
                          {r.clinical_details?.diagnosis ? ` · ${r.clinical_details.diagnosis}` : ''}
                          {r.notes ? ` — ${r.notes}` : ''}
                        </p>
                      ) : <p className="text-slate-500">Not charted (healthy).</p>}
                    </li>
                  );
                })}
                {toothProcs.filter((x) => x.status === 'completed').map((x) => (
                  <li key={`p${x.id}`} className="rounded-lg bg-emerald-50 px-3 py-2 text-xs dark:bg-emerald-950/40">
                    <p className="font-bold">Completed: {x.procedure_code?.code} {x.procedure_code?.title}</p>
                    {x.completed_at && <p className="text-slate-500">{fmtDate(x.completed_at)}</p>}
                  </li>
                ))}
              </ul>
            )}
          </div>
        </div>
      </div>

      {voice && (
        <VoiceNoteModal
          isOpen
          onClose={() => setVoice(null)}
          patient={{ id: p.id, first_name: (p as any).first_name, last_name: (p as any).last_name, phone: p.phone } as any}
          toothNumber={voice.tooth}
          procedure={voice.proc || null}
        />
      )}
    </div>
  );
}
