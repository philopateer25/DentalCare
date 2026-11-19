/**
 * Mounts the React FullCalendar into Filament's AppointmentsCalendarWidget.
 *
 * The Blade view renders <div data-appointments-calendar data-config="…" wire:ignore>.
 * We locate the owning Livewire component and pass its $wire proxy to React, so
 * every data call goes through the widget's tenant/role-scoped PHP methods.
 */
import React from 'react';
import { createRoot } from 'react-dom/client';
import AppointmentsCalendar from './calendar/AppointmentsCalendar';

const roots = new WeakMap();

function resolveWire(el) {
    const host = el.closest('[wire\\:id]');
    if (!host || !window.Livewire) return null;
    return window.Livewire.find(host.getAttribute('wire:id'));
}

function mount(el) {
    if (roots.has(el)) return;
    const wire = resolveWire(el);
    if (!wire) return;

    let config = {};
    try {
        config = JSON.parse(el.dataset.config || '{}');
    } catch {
        config = {};
    }

    el.innerHTML = '';
    const root = createRoot(el);
    roots.set(el, root);
    root.render(<AppointmentsCalendar wire={wire} config={config} />);
}

function mountAll() {
    document.querySelectorAll('[data-appointments-calendar]').forEach(mount);
}

// Livewire may initialise before or after this module loads.
if (window.Livewire) {
    queueMicrotask(mountAll);
}
document.addEventListener('livewire:initialized', mountAll);
document.addEventListener('livewire:navigated', mountAll);
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => setTimeout(mountAll, 0));
} else {
    setTimeout(mountAll, 0);
}
