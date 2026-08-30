import React from 'react';

export default function Card({
    className = '',
    children,
    hover = false,
    ...props
}) {
    return (
        <div
            className={`rounded-3xl bg-slate-900/70 border border-slate-800 backdrop-blur-xl shadow-xl ${
                hover ? 'hover:border-teal-500/40 hover:bg-slate-900 transition-all duration-300' : ''
            } ${className}`}
            {...props}
        >
            {children}
        </div>
    );
}
