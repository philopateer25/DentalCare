import React, { useMemo, useState } from 'react';
import { CONDITION_COLORS, CONDITION_LABELS, ToothCondition, ToothRecord } from './types';

/**
 * Overlay for the embedded (clean) 3D chart — phases 1 and 2:
 * chart summary + legend, selected-tooth quick actions, exam timeline, treatment-plan panel.
 * All writes go through the page's existing handlers, so the clinical rules stay server-side
 * (see App\Services\Dental\OdontogramRules).
 */

type Status = 'existing' | 'needs_treatment' | 'watch';

export interface Suggestion { tooth: string; code: string; title: string; fee: number; reason: string }

interface Props {
  patientId: number;
  teethRecords: Record<string, ToothRecord>;
  plannedProcedures: Record<string, any[]>;
  selectedTooth: string | null;
  procedureList: any[];
  activePlan: any | null;
  examinations: any[];
  activeExam: any;
  currentExamId: number;
  suggestion: Suggestion | null;
  planError: string | null;
  savingStatus: 'idle' | 'saving' | 'saved' | 'error';
  onSetCondition: (condition: ToothCondition) => void;
  onSetStatus: (status: Status) => void;
  onAddProcedure: (code: string, tooth: string) => void;
  onComplete: (procedureId: number, tooth: string) => void;
  onVoiceNote: (tooth: string, proc?: any) => void;
  onExamChange: (examId: number) => void;
  onDismissSuggestion: () => void;
}

const QUICK_CONDITIONS: ToothCondition[] = ['healthy', 'active_caries', 'composite_filled', 'root_canal', 'crown', 'missing', 'implant'];

const STATUS_META: Record<Status, { label: string; className: string }> = {
  existing: { label: 'Existing', className: 'bg-emerald-600' },
  needs_treatment: { label: 'Needs care', className: 'bg-rose-600' },
  watch: { label: 'Watch', className: 'bg-amber-600' },
};

const money = (n: number) => Number(n || 0).toLocaleString(undefined, { maximumFractionDigits: 2 });
const examDate = (e: any) => new Date(e.examined_at || e.created_at).toLocaleDateString(undefined, { day: 'numeric', month: 'short', year: 'numeric' });

export default function ChartOverlay(props: Props) {
  const {
    teethRecords, plannedProcedures, selectedTooth, procedureList, activePlan, examinations, activeExam,
    suggestion, planError, savingStatus,
  } = props;

  const [panel, setPanel] = useState<'tooth' | 'plan' | null>('tooth');
  const [procQuery, setProcQuery] = useState('');

  const latestExamId = props.currentExamId;
  const readOnly = activeExam && activeExam.id !== latestExamId;
  const timeline = useMemo(() => [...examinations].sort((a, b) => a.id - b.id), [examinations]);

  const records = Object.values(teethRecords);
  const counts = records.reduce((acc, r) => {
    const c = (r.condition || 'healthy') as ToothCondition;
    acc[c] = (acc[c] || 0) + 1;
    return acc;
  }, {} as Record<string, number>);
  const needsCare = records.filter((r) => r.status === 'needs_treatment').length;
  const watch = records.filter((r) => r.status === 'watch').length;

  const rec = selectedTooth ? teethRecords[selectedTooth] : undefined;
  const cond = ((rec?.condition as ToothCondition) || 'healthy') as ToothCondition;
  const status = ((rec?.status as Status) || 'existing') as Status;
  const toothProcs = (selectedTooth ? plannedProcedures[selectedTooth] : []) || [];

  const matches = useMemo(() => {
    const q = procQuery.trim().toLowerCase();
    if (q.length < 2) return [];
    return procedureList
      .filter((p) => p.code.toLowerCase().includes(q) || p.title.toLowerCase().includes(q))
      .slice(0, 6);
  }, [procQuery, procedureList]);

  // Plan summary (from the active plan's phases; procedures carry fee + status).
  const phases: any[] = activePlan?.phases || [];
  const planProcs = phases.flatMap((ph) => (ph.procedures || []).map((p: any) => ({ ...p, phaseName: ph.name })));
  const planTotal = planProcs.reduce((s, p) => s + Number(p.net_amount ?? p.fee ?? 0), 0);
  const doneTotal = planProcs.filter((p) => p.status === 'completed').reduce((s, p) => s + Number(p.net_amount ?? p.fee ?? 0), 0);
  const openCount = planProcs.filter((p) => p.status !== 'completed').length;

  const btn = 'min-h-[40px] rounded-lg px-2.5 text-[11px] font-bold transition-colors disabled:opacity-40 disabled:cursor-not-allowed';

  return (
    <>
      {/* Summary chips + legend */}
      <div className="pointer-events-none absolute right-3 top-3 z-10 flex max-w-[60%] flex-wrap justify-end gap-1.5 max-sm:left-2 max-sm:right-2 max-sm:top-[8rem] max-sm:max-w-none max-sm:justify-start">
        {needsCare > 0 && <span className="rounded-full bg-rose-600/90 px-2.5 py-1 text-[11px] font-bold text-white shadow">{needsCare} need treatment</span>}
        {watch > 0 && <span className="rounded-full bg-amber-600/90 px-2.5 py-1 text-[11px] font-bold text-white shadow">{watch} watch</span>}
        {openCount > 0 && <span className="rounded-full bg-sky-700/90 px-2.5 py-1 text-[11px] font-bold text-white shadow">{openCount} planned</span>}
        {(Object.keys(CONDITION_COLORS) as ToothCondition[])
          .filter((c) => c !== 'healthy' && (counts[c] || 0) > 0)
          .map((c) => (
            <span key={c} className="flex items-center gap-1.5 rounded-full bg-slate-900/85 px-2.5 py-1 text-[11px] font-semibold text-slate-100 shadow backdrop-blur">
              <span className="h-2.5 w-2.5 rounded-full" style={{ backgroundColor: CONDITION_COLORS[c] }} />
              {counts[c]} {CONDITION_LABELS[c]?.label || c}
            </span>
          ))}
        {records.filter((r) => r.condition && r.condition !== 'healthy').length === 0 && needsCare === 0 && (
          <span className="rounded-full bg-slate-900/85 px-2.5 py-1 text-[11px] font-semibold text-slate-300 shadow">No findings charted</span>
        )}
      </div>

      {/* Panel switcher */}
      <div className="absolute left-3 top-[5.5rem] z-20 flex gap-1 rounded-lg bg-slate-900/90 p-1 shadow-xl backdrop-blur max-sm:top-auto max-sm:bottom-[9rem] max-sm:left-2">
        {(['tooth', 'plan'] as const).map((p) => (
          <button
            key={p}
            type="button"
            onClick={() => setPanel(panel === p ? null : p)}
            className={`min-h-[36px] rounded-md px-3 text-[11px] font-bold ${panel === p ? 'bg-indigo-600 text-white' : 'text-slate-300 hover:text-white'}`}
          >
            {p === 'tooth' ? (selectedTooth ? `Tooth #${selectedTooth}` : 'Tooth') : `Plan${openCount ? ` (${openCount})` : ''}`}
          </button>
        ))}
        {savingStatus === 'saving' && <span className="self-center px-2 text-[10px] text-slate-400">Saving…</span>}
        {savingStatus === 'saved' && <span className="self-center px-2 text-[10px] text-emerald-400">Saved</span>}
        {savingStatus === 'error' && <span className="self-center px-2 text-[10px] text-rose-400">Error</span>}
      </div>

      {/* Tooth panel with quick actions */}
      {panel === 'tooth' && selectedTooth && (
        <div className="absolute left-3 top-[8.25rem] z-20 max-h-[calc(100%-13rem)] w-72 overflow-y-auto rounded-xl border border-slate-700/70 bg-slate-900/95 p-3 text-slate-100 shadow-2xl backdrop-blur max-sm:inset-x-2 max-sm:bottom-[12rem] max-sm:top-auto max-sm:max-h-[45%] max-sm:w-auto">
          <div className="flex items-center justify-between gap-2">
            <div className="flex items-center gap-2">
              <span className="h-3 w-3 rounded-full" style={{ backgroundColor: CONDITION_COLORS[cond] }} />
              <span className="text-sm font-bold">Tooth #{selectedTooth}</span>
              <span className="text-xs text-slate-400">{CONDITION_LABELS[cond]?.label || cond}</span>
            </div>
            <span className={`rounded-full px-2 py-0.5 text-[10px] font-bold text-white ${STATUS_META[status].className}`}>{STATUS_META[status].label}</span>
          </div>

          {readOnly && (
            <p className="mt-2 rounded-md bg-amber-900/40 px-2 py-1.5 text-[11px] text-amber-200">Past exam: read-only. Go to the latest exam to chart.</p>
          )}

          <p className="mt-3 text-[10px] font-bold uppercase tracking-wider text-slate-400">Condition</p>
          <div className="mt-1 grid grid-cols-4 gap-1">
            {QUICK_CONDITIONS.map((c) => (
              <button
                key={c}
                type="button"
                disabled={readOnly}
                onClick={() => props.onSetCondition(c)}
                title={CONDITION_LABELS[c]?.description}
                className={`${btn} flex flex-col items-center justify-center gap-0.5 border ${cond === c ? 'border-white/70 bg-slate-700 text-white' : 'border-slate-700 text-slate-300 hover:bg-slate-800'}`}
              >
                <span className="h-2.5 w-2.5 rounded-full" style={{ backgroundColor: CONDITION_COLORS[c] }} />
                <span className="leading-tight">{(CONDITION_LABELS[c]?.label || c).split(' ')[0]}</span>
              </button>
            ))}
          </div>

          <p className="mt-3 text-[10px] font-bold uppercase tracking-wider text-slate-400">Status</p>
          <div className="mt-1 grid grid-cols-3 gap-1">
            {(Object.keys(STATUS_META) as Status[]).map((s) => (
              <button
                key={s}
                type="button"
                disabled={readOnly}
                onClick={() => props.onSetStatus(s)}
                className={`${btn} ${status === s ? `${STATUS_META[s].className} text-white` : 'border border-slate-700 text-slate-300 hover:bg-slate-800'}`}
              >
                {STATUS_META[s].label}
              </button>
            ))}
          </div>

          {suggestion && suggestion.tooth === selectedTooth && status === 'needs_treatment' && !readOnly && (
            <div className="mt-3 rounded-lg border border-sky-700/60 bg-sky-950/50 p-2.5">
              <p className="text-[10px] font-bold uppercase tracking-wider text-sky-300">Suggested treatment</p>
              <p className="mt-0.5 text-xs font-semibold">{suggestion.code} · {suggestion.title}</p>
              <p className="text-[11px] text-slate-400">{suggestion.reason} Fee {money(suggestion.fee)}</p>
              <div className="mt-2 flex gap-1.5">
                <button type="button" onClick={() => props.onAddProcedure(suggestion.code, suggestion.tooth)} className={`${btn} flex-1 bg-sky-600 text-white hover:bg-sky-500`}>
                  Add to {activePlan ? 'plan' : 'a new plan'}
                </button>
                <button type="button" onClick={props.onDismissSuggestion} className={`${btn} border border-slate-700 text-slate-300`}>Dismiss</button>
              </div>
            </div>
          )}

          <p className="mt-3 text-[10px] font-bold uppercase tracking-wider text-slate-400">Procedures on this tooth</p>
          {toothProcs.length === 0 && <p className="mt-1 text-[11px] text-slate-500">None yet.</p>}
          <ul className="mt-1 space-y-1">
            {toothProcs.map((p: any) => (
              <li key={p.id} className="flex items-center justify-between gap-2 rounded-md bg-slate-800/70 px-2 py-1.5 text-[11px]">
                <span className="min-w-0 truncate">
                  <b>{p.procedure_code?.code}</b> {p.procedure_code?.title}
                  <span className="text-slate-400"> · {money(p.net_amount ?? p.fee)}</span>
                </span>
                {p.status === 'completed' ? (
                  <span className="shrink-0 rounded bg-emerald-800 px-1.5 py-0.5 font-bold text-emerald-100">Done</span>
                ) : (
                  <span className="flex shrink-0 gap-1">
                    <button type="button" onClick={() => props.onVoiceNote(selectedTooth, p)} title="Post-op voice note" className="min-h-[32px] rounded bg-slate-700 px-1.5 hover:bg-slate-600">🎙</button>
                    <button type="button" onClick={() => props.onComplete(p.id, selectedTooth)} className="min-h-[32px] rounded bg-emerald-700 px-2 font-bold text-white hover:bg-emerald-600">Done</button>
                  </span>
                )}
              </li>
            ))}
          </ul>

          {!readOnly && (
            <div className="relative mt-2">
              <input
                value={procQuery}
                onChange={(e) => setProcQuery(e.target.value)}
                placeholder="Add procedure: code or name…"
                className="min-h-[40px] w-full rounded-lg border border-slate-700 bg-slate-950 px-2.5 text-xs text-slate-100 placeholder:text-slate-500 focus:border-indigo-500 focus:outline-none"
              />
              {matches.length > 0 && (
                <ul className="absolute inset-x-0 top-full z-30 mt-1 max-h-48 overflow-y-auto rounded-lg border border-slate-700 bg-slate-900 shadow-2xl">
                  {matches.map((m) => (
                    <li key={m.id}>
                      <button
                        type="button"
                        onClick={() => { props.onAddProcedure(m.code, selectedTooth); setProcQuery(''); }}
                        className="flex min-h-[40px] w-full items-center justify-between gap-2 px-2.5 text-left text-[11px] hover:bg-slate-800"
                      >
                        <span className="min-w-0 truncate"><b>{m.code}</b> {m.title}</span>
                        <span className="shrink-0 text-slate-400">{money(m.standard_fee)}</span>
                      </button>
                    </li>
                  ))}
                </ul>
              )}
            </div>
          )}
          {planError && <p className="mt-1.5 text-[11px] text-rose-400">{planError}</p>}

          <a
            href={`/patients/${props.patientId}/odontogram-details?tooth=${selectedTooth}`}
            target="_top"
            className="mt-3 flex min-h-[40px] items-center justify-center rounded-lg bg-indigo-600 text-xs font-bold text-white hover:bg-indigo-500"
          >
            Open dental chart (surfaces, diagnosis, history)
          </a>
        </div>
      )}

      {/* Treatment plan panel */}
      {panel === 'plan' && (
        <div className="absolute left-3 top-[8.25rem] z-20 max-h-[calc(100%-13rem)] w-80 overflow-y-auto rounded-xl border border-slate-700/70 bg-slate-900/95 p-3 text-slate-100 shadow-2xl backdrop-blur max-sm:inset-x-2 max-sm:bottom-[12rem] max-sm:top-auto max-sm:max-h-[45%] max-sm:w-auto">
          {!activePlan ? (
            <p className="text-xs text-slate-400">No treatment plan yet. Add a procedure to a tooth to start one.</p>
          ) : (
            <>
              <p className="text-sm font-bold">{activePlan.title}</p>
              <p className="text-[11px] capitalize text-slate-400">{String(activePlan.status || '').replace('_', ' ')}</p>
              <div className="mt-2 grid grid-cols-3 gap-1.5 text-center">
                <div className="rounded-lg bg-slate-800 p-1.5"><p className="text-[10px] text-slate-400">Total</p><p className="text-xs font-bold">{money(planTotal)}</p></div>
                <div className="rounded-lg bg-emerald-900/60 p-1.5"><p className="text-[10px] text-emerald-300">Done</p><p className="text-xs font-bold">{money(doneTotal)}</p></div>
                <div className="rounded-lg bg-sky-900/60 p-1.5"><p className="text-[10px] text-sky-300">Remaining</p><p className="text-xs font-bold">{money(planTotal - doneTotal)}</p></div>
              </div>
              <div className="mt-2 h-1.5 overflow-hidden rounded-full bg-slate-800">
                <div className="h-full bg-emerald-500" style={{ width: `${planTotal ? Math.round((doneTotal / planTotal) * 100) : 0}%` }} />
              </div>
              {phases.map((ph) => {
                const procs = ph.procedures || [];
                const sub = procs.reduce((s: number, p: any) => s + Number(p.net_amount ?? p.fee ?? 0), 0);
                return (
                  <div key={ph.id} className="mt-3">
                    <div className="flex items-center justify-between text-[11px] font-bold text-slate-300">
                      <span>{ph.name}</span><span>{money(sub)}</span>
                    </div>
                    {procs.length === 0 && <p className="text-[11px] text-slate-500">No procedures.</p>}
                    <ul className="mt-1 space-y-1">
                      {procs.map((p: any) => (
                        <li key={p.id} className="flex items-center justify-between gap-2 rounded-md bg-slate-800/70 px-2 py-1.5 text-[11px]">
                          <span className="min-w-0 truncate">
                            {p.tooth_number_fdi ? <b>#{p.tooth_number_fdi} </b> : null}
                            {p.procedure_code?.code} {p.procedure_code?.title}
                          </span>
                          <span className={`shrink-0 rounded px-1.5 py-0.5 font-bold ${p.status === 'completed' ? 'bg-emerald-800 text-emerald-100' : 'bg-sky-800 text-sky-100'}`}>
                            {p.status === 'completed' ? 'Done' : money(p.net_amount ?? p.fee)}
                          </span>
                        </li>
                      ))}
                    </ul>
                  </div>
                );
              })}
            </>
          )}
        </div>
      )}

      {/* Exam timeline */}
      {timeline.length > 0 && (
        <div className="absolute bottom-[4.25rem] left-1/2 z-10 flex max-w-[92%] -translate-x-1/2 items-center gap-1 overflow-x-auto rounded-xl bg-slate-900/90 p-1 shadow-xl backdrop-blur max-sm:bottom-[5.5rem] max-sm:left-2 max-sm:right-2 max-sm:max-w-none max-sm:translate-x-0">
          <span className="px-2 text-[10px] font-bold uppercase tracking-wider text-slate-400">Exams</span>
          {timeline.map((e) => (
            <button
              key={e.id}
              type="button"
              onClick={() => props.onExamChange(e.id)}
              className={`min-h-[36px] shrink-0 rounded-lg px-2.5 text-[11px] font-semibold ${e.id === activeExam?.id ? 'bg-indigo-600 text-white' : 'text-slate-300 hover:bg-slate-800'}`}
              title={`${e.type} exam`}
            >
              {examDate(e)}{e.id === latestExamId ? ' · latest' : ''}
            </button>
          ))}
        </div>
      )}
    </>
  );
}
