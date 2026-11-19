import React, { useEffect, useState } from 'react';
import { Clock, DoorClosed, ExternalLink, FileText, Pencil, Phone, Stethoscope, User, X } from 'lucide-react';
import { STATUS_META, TRANSITION_LABELS, formatTime } from './utils';

export default function EventDrawer({ event, config, wire, onClose, onChanged }) {
    const p = event.extendedProps;
    const meta = STATUS_META[p.status] ?? STATUS_META.booked;
    const next = config.transitions[p.status] ?? [];
    const canManage = !config.isDoctorOnly || p.doctorId === config.currentUserId;
    const [busy, setBusy] = useState(null);

    useEffect(() => {
        const onKey = (e) => e.key === 'Escape' && onClose();
        window.addEventListener('keydown', onKey);
        return () => window.removeEventListener('keydown', onKey);
    }, [onClose]);

    const change = async (status) => {
        setBusy(status);
        const res = await wire.updateStatus(p.appointmentId, status);
        setBusy(null);
        onChanged(res?.message || 'Updated', !!res?.ok);
    };

    const url = (tpl, id) => tpl.replace('__ID__', id);

    return (
        <div className="dc-overlay dc-overlay-drawer" onMouseDown={(e) => e.target === e.currentTarget && onClose()}>
            <aside className="dc-drawer" role="dialog" aria-modal="true" aria-label="Appointment details">
                <div className="dc-drawer-head" style={{ '--c': meta.color }}>
                    <span className="dc-status" style={{ '--c': meta.color }}>{meta.label}</span>
                    <button type="button" className="dc-icon-btn" aria-label="Close" onClick={onClose}><X size={18} /></button>
                </div>

                <h3 className="dc-drawer-title">{p.patientName || event.title}</h3>
                {p.fileNumber && <p className="dc-muted">File {p.fileNumber}</p>}

                <dl className="dc-details">
                    <div><dt><Clock size={15} /></dt><dd>{formatTime(event.start, true)} – {formatTime(event.end)}</dd></div>
                    <div><dt><DoorClosed size={15} /></dt><dd>{p.operatoryName || 'Unassigned room'}</dd></div>
                    <div><dt><Stethoscope size={15} /></dt><dd>{p.doctorName}</dd></div>
                    {p.phone && <div><dt><Phone size={15} /></dt><dd><a href={`tel:${p.phone}`}>{p.phone}</a></dd></div>}
                    {(p.procedure || p.complaint) && <div><dt><FileText size={15} /></dt><dd>{p.procedure || p.complaint}</dd></div>}
                    {p.notes && <div><dt><FileText size={15} /></dt><dd className="dc-muted">{p.notes}</dd></div>}
                </dl>

                {canManage && next.length > 0 && (
                    <div className="dc-drawer-section">
                        <span className="dc-label">Update status</span>
                        <div className="dc-transition-grid">
                            {next.map((s) => (
                                <button
                                    key={s}
                                    type="button"
                                    className="dc-btn dc-btn-status"
                                    style={{ '--c': STATUS_META[s]?.color }}
                                    disabled={!!busy}
                                    onClick={() => change(s)}
                                >
                                    {busy === s ? '…' : TRANSITION_LABELS[s] ?? s}
                                </button>
                            ))}
                        </div>
                    </div>
                )}

                <div className="dc-drawer-links">
                    <a className="dc-btn dc-btn-ghost" href={url(config.urls.patientView, p.patientId)}>
                        <User size={16} /> Open patient <ExternalLink size={13} />
                    </a>
                    {canManage && (
                        <a className="dc-btn dc-btn-ghost" href={url(config.urls.appointmentEdit, p.appointmentId)}>
                            <Pencil size={16} /> Edit appointment
                        </a>
                    )}
                </div>
            </aside>
        </div>
    );
}
