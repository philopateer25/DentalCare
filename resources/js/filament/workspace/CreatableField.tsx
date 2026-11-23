import React, { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { http } from './api';

/**
 * Phase 5 — "I don't have time to type" for the chart workspace.
 *
 * Tap → a list opens: what this clinic uses most first, then the built-in
 * options. Typing filters; if the text isn't there, the first line offers
 * "Add “…”". Every pick is counted on the server so the list learns.
 * mode="append" adds the picked phrase to a note instead of replacing a value.
 */

export type Option = { value: string; label: string };

const OWN_URL = '/api/practice-options';

/** Loads the clinic's own values for several lists once; `used()` records a pick and moves it to the front. */
export function usePracticeOptions(lists: string[]) {
  const key = lists.join(',');
  const [own, setOwn] = useState<Record<string, string[]>>({});

  useEffect(() => {
    let cancelled = false;
    http.get(OWN_URL, { params: { lists: key } })
      .then((res) => { if (!cancelled) setOwn(res.data || {}); })
      .catch(() => { /* lists are a convenience; the built-in options still work */ });
    return () => { cancelled = true; };
  }, [key]);

  const used = useCallback((list: string, value: string) => {
    const clean = value.replace(/\s+/g, ' ').trim();
    if (!clean) return;
    setOwn((prev) => ({ ...prev, [list]: [clean, ...(prev[list] || []).filter((v) => v.toLowerCase() !== clean.toLowerCase())] }));
    http.post(OWN_URL, { list, value: clean }).catch(() => { /* read-only mode or offline: picking still works */ });
  }, []);

  return { own, used };
}

export default function CreatableField({
  value,
  options,
  own,
  onPick,
  placeholder = 'Tap to choose…',
  disabled = false,
  mode = 'set',
  className = '',
}: {
  value: string;
  options: Option[];
  own: string[];
  onPick: (value: string) => void;
  placeholder?: string;
  disabled?: boolean;
  mode?: 'set' | 'append';
  className?: string;
}) {
  const [open, setOpen] = useState(false);
  const [query, setQuery] = useState('');
  const box = useRef<HTMLDivElement>(null);

  useEffect(() => {
    if (!open) return;
    const close = (e: MouseEvent | TouchEvent) => { if (box.current && !box.current.contains(e.target as Node)) setOpen(false); };
    document.addEventListener('mousedown', close);
    document.addEventListener('touchstart', close);
    return () => { document.removeEventListener('mousedown', close); document.removeEventListener('touchstart', close); };
  }, [open]);

  const labelFor = useMemo(() => new Map(options.map((o) => [o.value, o.label])), [options]);

  const items = useMemo(() => {
    const seen = new Set<string>();
    const list: { value: string; label: string; mine: boolean }[] = [];
    for (const v of own) {
      const k = v.toLowerCase();
      if (seen.has(k)) continue;
      seen.add(k);
      list.push({ value: v, label: labelFor.get(v) || v, mine: true });
    }
    for (const o of options) {
      if (!o.value || seen.has(o.value.toLowerCase())) continue;
      seen.add(o.value.toLowerCase());
      list.push({ value: o.value, label: o.label, mine: false });
    }
    const q = query.trim().toLowerCase();
    return q ? list.filter((i) => i.value.toLowerCase().includes(q) || i.label.toLowerCase().includes(q)) : list;
  }, [own, options, labelFor, query]);

  const typed = query.replace(/\s+/g, ' ').trim();
  const canAdd = typed !== '' && !items.some((i) => i.value.toLowerCase() === typed.toLowerCase());

  const pick = (v: string) => {
    onPick(v);
    setQuery('');
    setOpen(false);
  };

  const shown = mode === 'set' && value ? labelFor.get(value) || value : '';

  return (
    <div ref={box} className={`relative ${className}`}>
      <button
        type="button"
        disabled={disabled}
        onClick={() => setOpen((o) => !o)}
        aria-haspopup="listbox"
        aria-expanded={open}
        className="flex min-h-[44px] w-full items-center justify-between gap-2 rounded-lg border border-slate-200 bg-slate-50 px-2.5 text-start text-sm text-slate-900 focus:border-brand focus:outline-none disabled:opacity-60 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100"
      >
        <span className={shown ? '' : 'text-slate-400 dark:text-slate-500'}>{shown || placeholder}</span>
        <span aria-hidden="true" className="text-slate-400">▾</span>
      </button>

      {open && (
        <div className="absolute z-30 mt-1 w-full overflow-hidden rounded-lg border border-slate-200 bg-white shadow-lg dark:border-slate-700 dark:bg-slate-900">
          <input
            value={query}
            onChange={(e) => setQuery(e.target.value)}
            onKeyDown={(e) => {
              if (e.key === 'Escape') setOpen(false);
              if (e.key === 'Enter') { e.preventDefault(); if (canAdd) pick(typed); else if (items[0]) pick(items[0].value); }
            }}
            placeholder="Search or type new…"
            className="w-full border-0 border-b border-slate-200 bg-transparent px-2.5 py-2 text-sm text-slate-900 focus:outline-none dark:border-slate-700 dark:text-slate-100"
          />
          <ul role="listbox" className="max-h-64 overflow-y-auto py-1 text-sm">
            {canAdd && (
              <li>
                <button type="button" onClick={() => pick(typed)} className="flex min-h-[44px] w-full items-center px-2.5 text-start font-semibold text-brand hover:bg-slate-100 dark:hover:bg-slate-800">
                  + Add “{typed}”
                </button>
              </li>
            )}
            {mode === 'set' && value && !query && (
              <li>
                <button type="button" onClick={() => pick('')} className="flex min-h-[44px] w-full items-center px-2.5 text-start text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-800">
                  Clear
                </button>
              </li>
            )}
            {items.map((i) => (
              <li key={i.value}>
                <button
                  type="button"
                  role="option"
                  aria-selected={mode === 'set' && i.value === value}
                  onClick={() => pick(i.value)}
                  className={`flex min-h-[44px] w-full items-center justify-between gap-2 px-2.5 text-start hover:bg-slate-100 dark:hover:bg-slate-800 ${mode === 'set' && i.value === value ? 'font-semibold text-brand' : 'text-slate-800 dark:text-slate-200'}`}
                >
                  <span>{i.label}</span>
                  {i.mine && <span className="shrink-0 text-[10px] font-bold uppercase tracking-wider text-brand-teal">Used here</span>}
                </button>
              </li>
            ))}
            {items.length === 0 && !canAdd && <li className="px-2.5 py-2 text-slate-500">Nothing found</li>}
          </ul>
        </div>
      )}
    </div>
  );
}
