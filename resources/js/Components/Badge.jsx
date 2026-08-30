import React from 'react';

export default function Badge({
    children,
    variant = 'teal',
    className = '',
    ...props
}) {
    const variants = {
        teal: 'bg-teal-500/10 text-teal-300 border-teal-500/30',
        cyan: 'bg-cyan-500/10 text-cyan-300 border-cyan-500/30',
        emerald: 'bg-emerald-500/10 text-emerald-300 border-emerald-500/30',
        amber: 'bg-amber-500/10 text-amber-300 border-amber-500/30',
        slate: 'bg-slate-800 text-slate-300 border-slate-700',
    };

    return (
        <span
            className={`inline-flex items-center gap-1.5 px-2.5 py-1 text-xs font-semibold rounded-full border ${variants[variant] || variants.teal} ${className}`}
            {...props}
        >
            {children}
        </span>
    );
}
