import '../css/app.css';
import React from 'react';
import { createRoot } from 'react-dom/client';
import { createInertiaApp } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';

import { LocaleProvider } from './Contexts/LocaleContext';

const appName = import.meta.env.VITE_APP_NAME || 'DentalCare';

createInertiaApp({
  title: (title) => `${title ? `${title} - ` : ''}${appName}`,
  resolve: (name) => {
    const pages = import.meta.glob('./Pages/**/*.{jsx,tsx}');
    return resolvePageComponent(`./Pages/${name}.tsx`, pages).catch(() =>
      resolvePageComponent(`./Pages/${name}.jsx`, pages)
    );
  },
  setup({ el, App, props }) {
    const root = createRoot(el);
    root.render(
      <LocaleProvider>
        <App {...props} />
      </LocaleProvider>
    );
  },
  progress: {
    color: '#10B981',
  },
});
