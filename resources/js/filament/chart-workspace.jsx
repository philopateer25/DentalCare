/**
 * Mounts the React Chart workspace into Filament (Patients → Chart).
 * The Blade view renders <div data-chart-workspace data-config="…" wire:ignore>.
 * All data goes through session-authenticated, tenant-checked JSON endpoints.
 */
import React from 'react';
import { createRoot } from 'react-dom/client';
import ChartWorkspace from './workspace/ChartWorkspace';

const roots = new WeakMap();

function mount(el) {
    if (roots.has(el)) return;
    let config = {};
    try {
        config = JSON.parse(el.dataset.config || '{}');
    } catch {
        config = {};
    }
    el.innerHTML = '';
    const root = createRoot(el);
    roots.set(el, root);
    root.render(<ChartWorkspace config={config} />);
}

function mountAll() {
    document.querySelectorAll('[data-chart-workspace]').forEach(mount);
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', mountAll);
} else {
    mountAll();
}
document.addEventListener('livewire:navigated', mountAll);
