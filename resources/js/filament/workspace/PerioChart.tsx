import React, { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { http, errorMessage, type ChartRecord } from './api';

/**
 * 6-site periodontal (gum) chart.
 * Sites per tooth (stored order): 0 DB, 1 B, 2 MB, 3 DL, 4 L, 5 ML.
 * GM = gingival margin vs CEJ (negative = recession). CAL = PD − GM (computed, not stored).
 * Entry: tap a cell, then use the keypad; PD entry auto-advances to the next site.
 */

type Field = 'pd' | 'gm';
type Flag = 'bleeding' | 'suppuration' | 'plaque';

interface Chart {
  id: number;
  exam_date: string;
  doctor?: string;
  probe_depths: Record<string, (number | null)[]>;
  gingival_margins: Record<string, (number | null)[]>;
  bleeding: Record<string, (boolean | null)[]>;
  suppuration: Record<string, (boolean | null)[]>;
  plaque: Record<string, (boolean | null)[]>;
  mobility: Record<string, number>;
  furcation: Record<string, number>;
  notes: string | null;
  summary: { sites_recorded: number; mean_pd: number | null; sites_4_5: number; sites_6_plus: number; max_cal: number | null; bop_percent: number | null; plaque_percent: number | null };
}

const UPPER = ['18', '17', '16', '15', '14', '13', '12', '11', '21', '22', '23', '24', '25', '26', '27', '28'];
const LOWER = ['48', '47', '46', '45', '44', '43', '42', '41', '31', '32', '33', '34', '35', '36', '37', '38'];
const SITE_NAMES = ['DB', 'B', 'MB', 'DL', 'L', 'ML'];
const isMolar = (t: string) => Number(t.slice(-1)) >= 6;
const rightSide = (t: string) => t[0] === '1' || t[0] === '4';
/** Display order of the 3 sites on one side: distal→mesial on the patient's right, mesial→distal on the left. */
const order = (t: string, side: 'buccal' | 'lingual') => {
  const base = side === 'buccal' ? [0, 1, 2] : [3, 4, 5];
  return rightSide(t) ? base : [...base].reverse();
};
const six = <T,>(arr: T[] | undefined, fill: T): T[] => (arr && arr.length === 6 ? arr : Array(6).fill(fill));

export default function PerioChart({ patientId, records }: { patientId: number; records: Record<string, ChartRecord> }) {
  const [charts, setCharts] = useState<Chart[]>([]);
  const [chart, setChart] = useState<Chart | null>(null);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState<'idle' | 'saving' | 'saved' | 'error'>('idle');
  const [error, setError] = useState<string | null>(null);
  const [cursor, setCursor] = useState<{ tooth: string; site: number; field: Field } | null>(null);
  const timer = useRef<any>(null);

  const missing = useCallback((t: string) => records[t]?.condition === 'missing', [records]);
  const latestId = charts[0]?.id;
  const readOnly = !!chart && chart.id !== latestId;

  useEffect(() => {
    http.get(`/api/patients/${patientId}/perio`)
      .then((r) => { setCharts(r.data); setChart(r.data[0] || null); })
      .catch((e) => setError(errorMessage(e, 'Could not load gum charts.')))
      .finally(() => setLoading(false));
  }, [patientId]);

  const save = useCallback((next: Chart) => {
    clearTimeout(timer.current);
    setSaving('saving');
    timer.current = setTimeout(async () => {
      try {
        const res = await http.put(`/api/perio/${next.id}`, {
          probe_depths: next.probe_depths, gingival_margins: next.gingival_margins, bleeding: next.bleeding,
          suppuration: next.suppuration, plaque: next.plaque, mobility: next.mobility, furcation: next.furcation, notes: next.notes,
        });
        setChart((c) => (c && c.id === res.data.id ? { ...c, summary: res.data.summary } : c));
        setCharts((cs) => cs.map((c) => (c.id === res.data.id ? res.data : c)));
        setSaving('saved');
        setError(null);
      } catch (e) {
        setSaving('error');
        setError(errorMessage(e, 'Saving the gum chart failed.'));
      }
    }, 800);
  }, []);

  const edit = (mutate: (c: Chart) => Chart) => {
    if (!chart || readOnly) return;
    const next = mutate(structuredClone(chart));
    setChart(next);
    save(next);
  };

  const newChart = async () => {
    try {
      const res = await http.post(`/api/patients/${patientId}/perio`);
      setCharts((cs) => [res.data, ...cs]);
      setChart(res.data);
      setCursor({ tooth: UPPER.find((t) => !missing(t)) || '18', site: order(UPPER.find((t) => !missing(t)) || '18', 'buccal')[0], field: 'pd' });
    } catch (e) {
      setError(errorMessage(e, 'Could not start a gum chart.'));
    }
  };

  // Entry sequence: upper buccal L→R, upper lingual R→L, lower lingual L→R, lower buccal R→L (a standard probing path).
  const sequence = useMemo(() => {
    const seq: { tooth: string; site: number }[] = [];
    const push = (teeth: string[], side: 'buccal' | 'lingual', reverse: boolean) => {
      const list = reverse ? [...teeth].reverse() : teeth;
      list.filter((t) => !missing(t)).forEach((t) => {
        const o = order(t, side);
        (reverse ? [...o].reverse() : o).forEach((site) => seq.push({ tooth: t, site }));
      });
    };
    push(UPPER, 'buccal', false); push(UPPER, 'lingual', true); push(LOWER, 'lingual', false); push(LOWER, 'buccal', true);
    return seq;
  }, [missing]);

  const advance = () => {
    if (!cursor) return;
    const i = sequence.findIndex((s) => s.tooth === cursor.tooth && s.site === cursor.site);
    const next = sequence[i + 1];
    if (next) setCursor({ ...next, field: cursor.field });
  };

  const setValue = (value: number | null) => {
    if (!cursor) return;
    const { tooth, site, field } = cursor;
    edit((c) => {
      const key = field === 'pd' ? 'probe_depths' : 'gingival_margins';
      const arr = six(c[key][tooth], null as number | null);
      arr[site] = value;
      c[key] = { ...c[key], [tooth]: arr };
      return c;
    });
    if (field === 'pd' && value !== null) advance();
  };

  const toggleFlag = (flag: Flag) => {
    if (!cursor) return;
    const { tooth, site } = cursor;
    edit((c) => {
      const arr = six(c[flag][tooth], false as boolean | null);
      arr[site] = !arr[site];
      c[flag] = { ...c[flag], [tooth]: arr };
      return c;
    });
  };

  const setToothValue = (key: 'mobility' | 'furcation', tooth: string, max: number) => {
    edit((c) => {
      const current = c[key][tooth] ?? 0;
      const next = (current + 1) % (max + 1);
      const map = { ...c[key] };
      if (next === 0) delete map[tooth]; else map[tooth] = next;
      c[key] = map;
      return c;
    });
  };

  if (loading) return <div className="p-6 text-sm text-slate-500">Loading gum chart…</div>;

  const s = chart?.summary;
  const pdClass = (v: number | null | undefined) =>
    v == null ? 'text-slate-300 dark:text-slate-600' : v >= 6 ? 'bg-red-600 text-white' : v >= 4 ? 'bg-amber-400 text-slate-900' : 'text-slate-800 dark:text-slate-100';

  const siteCell = (t: string, site: number, field: Field) => {
    const v = field === 'pd' ? chart?.probe_depths[t]?.[site] : chart?.gingival_margins[t]?.[site];
    const sel = cursor && cursor.tooth === t && cursor.site === site && cursor.field === field;
    const bleed = chart?.bleeding[t]?.[site];
    const supp = chart?.suppuration[t]?.[site];
    const plaque = chart?.plaque[t]?.[site];
    return (
      <button
        key={`${field}${site}`}
        type="button"
        disabled={!chart || missing(t)}
        onClick={() => setCursor({ tooth: t, site, field })}
        title={`${t} ${SITE_NAMES[site]} ${field.toUpperCase()}`}
        className={`relative h-7 w-6 rounded text-[11px] font-bold tabular-nums disabled:opacity-30 ${field === 'pd' ? pdClass(v as any) : 'text-slate-600 dark:text-slate-300'} ${sel ? 'ring-2 ring-brand ring-offset-1 dark:ring-offset-slate-900' : 'hover:bg-slate-100 dark:hover:bg-slate-800'}`}
      >
        {v ?? '·'}
        {field === 'pd' && (bleed || supp || plaque) && (
          <span className="absolute -top-1 left-1/2 flex -translate-x-1/2 gap-px">
            {bleed && <span className="h-1.5 w-1.5 rounded-full bg-red-600" />}
            {supp && <span className="h-1.5 w-1.5 rounded-full bg-yellow-400" />}
            {plaque && <span className="h-1.5 w-1.5 rounded-full bg-sky-500" />}
          </span>
        )}
      </button>
    );
  };

  const calCell = (t: string, site: number) => {
    const pd = chart?.probe_depths[t]?.[site];
    const gm = chart?.gingival_margins[t]?.[site] ?? 0;
    const cal = pd == null ? null : pd - gm;
    return <span key={`cal${site}`} className={`inline-block w-6 text-center text-[10px] tabular-nums ${cal != null && cal >= 5 ? 'font-bold text-red-600 dark:text-red-400' : 'text-slate-500'}`}>{cal ?? ''}</span>;
  };

  const arch = (teeth: string[], label: string, buccalFirst: boolean) => {
    const sideRows = (side: 'buccal' | 'lingual') => (
      <>
        <tr>
          <th className="pr-2 text-right text-[10px] font-bold uppercase text-slate-500">{side === 'buccal' ? 'Buccal PD' : 'Lingual PD'}</th>
          {teeth.map((t) => <td key={t} className="px-0.5"><div className="flex justify-center gap-px">{order(t, side).map((site) => siteCell(t, site, 'pd'))}</div></td>)}
        </tr>
        <tr>
          <th className="pr-2 text-right text-[10px] font-bold uppercase text-slate-500">GM</th>
          {teeth.map((t) => <td key={t} className="px-0.5"><div className="flex justify-center gap-px">{order(t, side).map((site) => siteCell(t, site, 'gm'))}</div></td>)}
        </tr>
        <tr>
          <th className="pr-2 text-right text-[10px] font-bold uppercase text-slate-500">CAL</th>
          {teeth.map((t) => <td key={t} className="px-0.5"><div className="flex justify-center gap-px">{order(t, side).map((site) => calCell(t, site))}</div></td>)}
        </tr>
      </>
    );
    return (
      <div className="overflow-x-auto">
        <p className="mb-1 text-[10px] font-bold uppercase tracking-wider text-slate-500">{label}</p>
        <table className="border-separate border-spacing-y-0.5">
          <tbody>
            {buccalFirst ? sideRows('buccal') : sideRows('lingual')}
            <tr>
              <th className="pr-2 text-right text-[10px] font-bold uppercase text-slate-500">Tooth</th>
              {teeth.map((t) => (
                <td key={t} className="px-0.5 text-center">
                  <span className={`inline-block min-w-[78px] rounded-md py-1 text-xs font-bold ${missing(t) ? 'bg-slate-100 text-slate-400 line-through dark:bg-slate-800' : 'bg-slate-100 text-slate-800 dark:bg-slate-800 dark:text-slate-100'}`}>{t}</span>
                </td>
              ))}
            </tr>
            <tr>
              <th className="pr-2 text-right text-[10px] font-bold uppercase text-slate-500">Mob / Furc</th>
              {teeth.map((t) => (
                <td key={t} className="px-0.5 text-center">
                  <div className="flex justify-center gap-1">
                    <button type="button" disabled={!chart || readOnly || missing(t)} onClick={() => setToothValue('mobility', t, 3)} title="Mobility (tap to cycle 0–III)"
                      className={`h-7 min-w-[30px] rounded text-[11px] font-bold disabled:opacity-30 ${chart?.mobility[t] ? 'bg-violet-600 text-white' : 'bg-slate-100 text-slate-500 dark:bg-slate-800'}`}>
                      M{chart?.mobility[t] ? ['', 'I', 'II', 'III'][chart.mobility[t]] : ''}
                    </button>
                    {isMolar(t) && (
                      <button type="button" disabled={!chart || readOnly || missing(t)} onClick={() => setToothValue('furcation', t, 4)} title="Furcation (tap to cycle 0–IV)"
                        className={`h-7 min-w-[30px] rounded text-[11px] font-bold disabled:opacity-30 ${chart?.furcation[t] ? 'bg-orange-600 text-white' : 'bg-slate-100 text-slate-500 dark:bg-slate-800'}`}>
                        F{chart?.furcation[t] ? ['', 'I', 'II', 'III', 'IV'][chart.furcation[t]] : ''}
                      </button>
                    )}
                  </div>
                </td>
              ))}
            </tr>
            {buccalFirst ? sideRows('lingual') : sideRows('buccal')}
          </tbody>
        </table>
      </div>
    );
  };

  const cursorFlags = cursor && chart ? {
    bleeding: chart.bleeding[cursor.tooth]?.[cursor.site],
    suppuration: chart.suppuration[cursor.tooth]?.[cursor.site],
    plaque: chart.plaque[cursor.tooth]?.[cursor.site],
  } : null;

  return (
    <div className="flex flex-col gap-4">
      <div className="flex flex-wrap items-center gap-2">
        <span className="text-[10px] font-bold uppercase tracking-wider text-slate-500">Gum charts</span>
        {charts.map((c) => (
          <button key={c.id} type="button" onClick={() => { setChart(c); setCursor(null); }}
            className={`min-h-[34px] rounded-lg px-2.5 text-xs font-semibold ${chart?.id === c.id ? 'bg-brand text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-300'}`}>
            {new Date(c.exam_date).toLocaleDateString(undefined, { day: 'numeric', month: 'short', year: 'numeric' })}{c.id === latestId ? ' (latest)' : ''}
          </button>
        ))}
        <button type="button" onClick={newChart} className="min-h-[34px] rounded-lg border border-dashed border-brand px-2.5 text-xs font-bold text-brand dark:border-indigo-400 dark:text-indigo-300">+ New gum chart</button>
        <span className={`ml-auto text-xs font-semibold ${saving === 'error' ? 'text-red-600' : saving === 'saving' ? 'text-slate-500' : 'text-brand-teal'}`}>
          {saving === 'saving' ? 'Saving…' : saving === 'error' ? 'Not saved' : saving === 'saved' ? '✓ Saved' : ''}
        </span>
      </div>
      {error && <p className="text-xs font-semibold text-red-600">{error}</p>}
      {readOnly && <p className="rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-800 dark:bg-amber-950 dark:text-amber-200">Viewing an older gum chart (read-only, for comparison).</p>}

      {!chart ? (
        <div className="rounded-xl bg-slate-50 p-6 text-center text-sm text-slate-600 dark:bg-slate-800/60 dark:text-slate-300">
          No gum chart recorded yet. <button type="button" onClick={newChart} className="font-bold text-brand underline dark:text-indigo-300">Start one</button> to record 6-site probing depths, recession, bleeding, suppuration, plaque, mobility and furcation.
        </div>
      ) : (
        <>
          {s && (
            <div className="grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-6">
              {[
                ['Bleeding (BOP)', s.bop_percent != null ? `${s.bop_percent}%` : '—', s.bop_percent != null && s.bop_percent > 10],
                ['Plaque', s.plaque_percent != null ? `${s.plaque_percent}%` : '—', s.plaque_percent != null && s.plaque_percent > 20],
                ['Mean PD', s.mean_pd != null ? `${s.mean_pd} mm` : '—', false],
                ['Sites 4–5 mm', String(s.sites_4_5), s.sites_4_5 > 0],
                ['Sites ≥ 6 mm', String(s.sites_6_plus), s.sites_6_plus > 0],
                ['Worst CAL', s.max_cal != null ? `${s.max_cal} mm` : '—', s.max_cal != null && s.max_cal >= 5],
              ].map(([k, v, warn]) => (
                <div key={k as string} className={`rounded-lg p-2.5 ${warn ? 'bg-amber-50 dark:bg-amber-950/50' : 'bg-slate-50 dark:bg-slate-800/60'}`}>
                  <p className="text-[10px] font-bold uppercase tracking-wider text-slate-500">{k}</p>
                  <p className="text-base font-bold text-slate-900 dark:text-slate-100">{v}</p>
                </div>
              ))}
            </div>
          )}

          <div className="grid gap-4">
            {arch(UPPER, 'Upper arch (maxilla)', true)}
            {arch(LOWER, 'Lower arch (mandible)', false)}
          </div>

          {/* Keypad */}
          {cursor && !readOnly && (
            <div className="sticky bottom-3 z-10 rounded-xl border border-slate-200 bg-white/95 p-3 shadow-xl backdrop-blur dark:border-slate-700 dark:bg-slate-900/95">
              <div className="mb-2 flex flex-wrap items-center justify-between gap-2">
                <p className="text-sm font-bold">
                  #{cursor.tooth} · {SITE_NAMES[cursor.site]} ·{' '}
                  <span className="text-brand dark:text-indigo-300">{cursor.field === 'pd' ? 'Probing depth (mm)' : 'Gingival margin (mm, − = recession)'}</span>
                </p>
                <div className="flex gap-1">
                  {(['pd', 'gm'] as Field[]).map((f) => (
                    <button key={f} type="button" onClick={() => setCursor({ ...cursor, field: f })}
                      className={`min-h-[36px] rounded-lg px-3 text-xs font-bold ${cursor.field === f ? 'bg-brand text-white' : 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300'}`}>
                      {f.toUpperCase()}
                    </button>
                  ))}
                  <button type="button" onClick={() => setCursor(null)} className="min-h-[36px] rounded-lg px-3 text-xs font-semibold text-slate-500">Close</button>
                </div>
              </div>
              <div className="grid grid-cols-7 gap-1.5 sm:grid-cols-[repeat(14,minmax(0,1fr))]">
                {(cursor.field === 'gm' ? [-6, -5, -4, -3, -2, -1, 0, 1, 2, 3, 4, 5] : [0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12]).map((n) => (
                  <button key={n} type="button" onClick={() => setValue(n)}
                    className={`min-h-[44px] rounded-lg text-base font-bold ${cursor.field === 'pd' && n >= 6 ? 'bg-red-50 text-red-700 dark:bg-red-950 dark:text-red-200' : cursor.field === 'pd' && n >= 4 ? 'bg-amber-50 text-amber-800 dark:bg-amber-950 dark:text-amber-200' : 'bg-slate-100 text-slate-800 dark:bg-slate-800 dark:text-slate-100'}`}>
                    {n}
                  </button>
                ))}
                <button type="button" onClick={() => setValue(null)} className="min-h-[44px] rounded-lg bg-slate-100 text-xs font-bold text-slate-500 dark:bg-slate-800">Clear</button>
              </div>
              <div className="mt-2 flex flex-wrap gap-1.5">
                {([['bleeding', 'Bleeding', 'bg-red-600'], ['suppuration', 'Pus (SUP)', 'bg-yellow-500'], ['plaque', 'Plaque', 'bg-sky-600']] as [Flag, string, string][]).map(([f, l, on]) => (
                  <button key={f} type="button" onClick={() => toggleFlag(f)}
                    className={`min-h-[44px] flex-1 rounded-lg px-3 text-xs font-bold ${cursorFlags?.[f] ? `${on} text-white` : 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300'}`}>
                    {l}{cursorFlags?.[f] ? ' ✓' : ''}
                  </button>
                ))}
                <button type="button" onClick={advance} className="min-h-[44px] rounded-lg bg-brand-deep px-4 text-xs font-bold text-white">Next site →</button>
              </div>
            </div>
          )}

          <label className="grid gap-1">
            <span className="text-[10px] font-bold uppercase tracking-wider text-slate-500">Perio notes</span>
            <textarea rows={2} disabled={readOnly} value={chart.notes || ''} onChange={(e) => edit((c) => ({ ...c, notes: e.target.value }))}
              className="min-h-[64px] rounded-lg border border-slate-200 bg-slate-50 px-2.5 py-2 text-sm text-slate-900 focus:border-brand focus:outline-none dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100" />
          </label>
          <p className="text-[11px] text-slate-500">Dots on a depth: red = bleeding, yellow = pus, blue = plaque. Depth 4–5 mm amber, ≥ 6 mm red. CAL = PD − GM.</p>
        </>
      )}
    </div>
  );
}
