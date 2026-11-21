import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';

import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.jsx',
                // React calendar mounted inside Filament (AppointmentsCalendarWidget)
                'resources/css/filament-calendar.css',
                'resources/css/filament-theme.css',
                'resources/js/filament/appointments-calendar.jsx',
                // Chart workspace (Patients -> Chart)
                'resources/css/chart-workspace.css',
                'resources/js/filament/chart-workspace.jsx',
            ],
            refresh: true,
        }),
        react(),
        tailwindcss(),
    ],
    server: {
        host: '0.0.0.0',
        port: 5173,
        cors: true,
        hmr: {
            host: 'localhost', // Automatically replaced by Laravel Vite plugin dynamically
        },
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});

