import React from 'react';

export default function InputLabel({
    value,
    className = '',
    children,
    ...props
}) {
    return (
        <label
            {...props}
            className={`block text-xs font-semibold uppercase tracking-wider text-slate-300 ${className}`}
        >
            {value ? value : children}
        </label>
    );
}
