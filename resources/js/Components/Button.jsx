import React from 'react';

export default function Button({
    type = 'button',
    variant = 'primary',
    size = 'md',
    className = '',
    disabled = false,
    children,
    ...props
}) {
    const baseStyles = 'inline-flex items-center justify-center font-bold rounded-xl transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-teal-500 disabled:opacity-50 disabled:cursor-not-allowed';

    const variants = {
        primary: 'bg-gradient-to-r from-teal-400 to-cyan-500 text-slate-950 shadow-lg shadow-teal-500/25 hover:shadow-teal-500/40 hover:scale-[1.02] active:scale-[0.98]',
        secondary: 'bg-slate-900/90 text-teal-300 border border-teal-500/30 hover:border-teal-500/60 hover:bg-slate-800 shadow-sm',
        outline: 'bg-transparent text-slate-200 border border-slate-700 hover:border-slate-500 hover:bg-slate-800/50',
        ghost: 'bg-transparent text-slate-400 hover:text-white hover:bg-slate-800/50',
        danger: 'bg-red-600 text-white hover:bg-red-500 shadow-lg shadow-red-500/20',
    };

    const sizes = {
        sm: 'px-3 py-1.5 text-xs gap-1.5',
        md: 'px-5 py-2.5 text-sm gap-2',
        lg: 'px-7 py-3.5 text-base gap-2.5',
    };

    return (
        <button
            type={type}
            disabled={disabled}
            className={`${baseStyles} ${variants[variant] || variants.primary} ${sizes[size] || sizes.md} ${className}`}
            {...props}
        >
            {children}
        </button>
    );
}
