import React, { useCallback, useState } from 'react';
import { CheckCircle2, XCircle } from 'lucide-react';

export function useToasts() {
    const [toasts, setToasts] = useState([]);
    const push = useCallback((tone, message) => {
        const id = Math.random().toString(36).slice(2);
        setToasts((t) => [...t, { id, tone, message }]);
        setTimeout(() => setToasts((t) => t.filter((x) => x.id !== id)), 4000);
    }, []);
    return { toasts, push };
}

export function Toasts({ toasts }) {
    return (
        <div className="dc-toasts" aria-live="polite">
            {toasts.map((t) => (
                <div key={t.id} className={`dc-toast is-${t.tone}`}>
                    {t.tone === 'success' ? <CheckCircle2 size={16} /> : <XCircle size={16} />}
                    <span>{t.message}</span>
                </div>
            ))}
        </div>
    );
}
