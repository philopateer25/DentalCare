/**
 * Mounts the React dental chart into Filament:
 * - Patients → Chart: the full Chart workspace;
 * - patient profile "3D Mouth Chart" tab: a read-only preview (config.preview = true).
 * Elements: <div data-chart-workspace data-config="…" wire:ignore>.
 * All data goes through session-authenticated, tenant-checked JSON endpoints.
 */
import React from 'react';
import { createRoot } from 'react-dom/client';
import ChartWorkspace from './workspace/ChartWorkspace';
import ChartPreview from './workspace/ChartPreview';

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
    root.render(config.preview ? <ChartPreview config={config} /> : <ChartWorkspace config={config} />);
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
// Infolist tabs can render their content later; mount anything new (at most once per frame).
let queued = false;
new MutationObserver(() => {
    if (queued) return;
    queued = true;
    requestAnimationFrame(() => {
        queued = false;
        mountAll();
    });
}).observe(document.body, { childList: true, subtree: true });
