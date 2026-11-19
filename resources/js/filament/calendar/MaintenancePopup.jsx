import React, { useEffect } from 'react';
import { AlertTriangle, Wrench } from 'lucide-react';

/**
 * "Something will be missing in this room" confirmation. Shown when a room
 * stays operational during maintenance but equipment is unavailable.
 */
export default function MaintenancePopup({ roomName, warnings, confirmLabel = 'Book anyway', onConfirm, onCancel }) {
    useEffect(() => {
        const onKey = (e) => e.key === 'Escape' && onCancel();
        window.addEventListener('keydown', onKey);
        return () => window.removeEventListener('keydown', onKey);
    }, [onCancel]);

    return (
        <div className="dc-overlay dc-overlay-top" onMouseDown={(e) => e.target === e.currentTarget && onCancel()}>
            <div className="dc-dialog dc-dialog-sm" role="alertdialog" aria-modal="true" aria-labelledby="dc-maint-title">
                <div className="dc-dialog-icon is-warning"><AlertTriangle size={26} /></div>
                <h3 id="dc-maint-title" className="dc-dialog-title">
                    Something will be missing in {roomName || 'this room'}
                </h3>
                <p className="dc-dialog-desc">
                    The room stays open, but maintenance is under way during this appointment. Make sure the visit doesn't need it.
                </p>
                <div className="dc-warn-list">
                    {warnings.map((w) => (
                        <div key={w.id} className="dc-warn-item">
                            <Wrench size={16} />
                            <div>
                                <strong>{w.unavailable_items || w.equipment_name || w.title}</strong>
                                <span>
                                    {w.title}
                                    {w.equipment_name ? ` · ${w.equipment_name}` : ''} — until {w.ends_at_label}
                                </span>
                            </div>
                        </div>
                    ))}
                </div>
                <div className="dc-dialog-actions">
                    <button type="button" className="dc-btn dc-btn-ghost" onClick={onCancel}>Go back</button>
                    <button type="button" className="dc-btn dc-btn-warning" onClick={onConfirm} autoFocus>{confirmLabel}</button>
                </div>
            </div>
        </div>
    );
}
