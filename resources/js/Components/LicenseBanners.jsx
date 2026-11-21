import React from 'react';
import { usePage } from '@inertiajs/react';

const TONES = {
    danger: 'bg-red-500/15 border-red-500/40 text-red-200',
    warning: 'bg-amber-500/15 border-amber-500/40 text-amber-200',
    success: 'bg-emerald-500/15 border-emerald-500/40 text-emerald-200',
    info: 'bg-sky-500/15 border-sky-500/40 text-sky-200',
};

/** License banners (subscription ending, offline, read-only, announcements) shared by HandleInertiaRequests. */
export default function LicenseBanners() {
    const banners = usePage().props?.license?.banners ?? [];

    if (!banners.length) {
        return null;
    }

    return (
        <div className="relative z-20 mx-auto w-full max-w-7xl px-4 pt-3 space-y-2" role="status">
            {banners.map((banner) => (
                <div key={banner.id} className={`rounded-lg border px-4 py-3 text-sm font-medium ${TONES[banner.level] ?? TONES.info}`}>
                    {banner.message}
                </div>
            ))}
        </div>
    );
}
