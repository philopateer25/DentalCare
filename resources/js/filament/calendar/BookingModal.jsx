import React, { useEffect, useMemo, useRef, useState } from 'react';
import { AlertTriangle, Ban, CalendarPlus, CheckCircle2, Search, X } from 'lucide-react';
import MaintenancePopup from './MaintenancePopup';
import { nextQuarterHour, toLocalInput } from './utils';

const DURATIONS = [15, 30, 45, 60, 90, 120];

export default function BookingModal({ wire, config, options, initialStart, initialEnd, initialRoom, initialDoctor, onClose, onBooked }) {
    const start0 = initialStart ? new Date(initialStart) : nextQuarterHour();
    const dur0 = initialEnd ? Math.max(15, Math.round((new Date(initialEnd) - start0) / 60000)) : 30;

    const [form, setForm] = useState({
        patient: null,
        doctor_id: initialDoctor ? String(initialDoctor) : '',
        operatory_id: initialRoom ? String(initialRoom) : '',
        start: toLocalInput(start0),
        duration: dur0,
        chief_complaint: '',
    });
    const [query, setQuery] = useState('');
    const [results, setResults] = useState([]);
    const [searching, setSearching] = useState(false);
    const [assessment, setAssessment] = useState(null);
    const [checking, setChecking] = useState(false);
    const [error, setError] = useState('');
    const [saving, setSaving] = useState(false);
    const [showPopup, setShowPopup] = useState(false);
    const searchRef = useRef(null);

    const set = (k, v) => setForm((f) => ({ ...f, [k]: v }));

    useEffect(() => {
        searchRef.current?.focus();
        const onKey = (e) => e.key === 'Escape' && !showPopup && onClose();
        window.addEventListener('keydown', onKey);
        return () => window.removeEventListener('keydown', onKey);
    }, [onClose, showPopup]);

    // Debounced patient search
    useEffect(() => {
        if (form.patient) return undefined;
        setSearching(true);
        const t = setTimeout(() => {
            wire.searchPatients(query).then((r) => setResults(r || [])).finally(() => setSearching(false));
        }, 250);
        return () => clearTimeout(t);
    }, [query, form.patient, wire]);

    const endDate = useMemo(() => {
        const s = new Date(form.start);
        return new Date(s.getTime() + form.duration * 60000);
    }, [form.start, form.duration]);

    // Live slot check (maintenance + overlap) whenever room/time/doctor changes
    useEffect(() => {
        if (!form.operatory_id || !form.start) {
            setAssessment(null);
            return undefined;
        }
        setChecking(true);
        const t = setTimeout(() => {
            wire.checkSlot(
                Number(form.operatory_id),
                new Date(form.start).toISOString(),
                endDate.toISOString(),
                form.doctor_id ? Number(form.doctor_id) : null,
                null,
            )
                .then(setAssessment)
                .finally(() => setChecking(false));
        }, 200);
        return () => clearTimeout(t);
    }, [form.operatory_id, form.start, endDate, form.doctor_id, wire]);

    const closed = assessment?.closures?.length > 0;
    const conflict = assessment?.appointment_conflict;
    const warnings = assessment?.warnings ?? [];
    const ready = form.patient && (config.isDoctorOnly || form.doctor_id) && form.operatory_id && form.start;

    const submit = async (acknowledged = false) => {
        setError('');
        if (!ready) {
            setError('Please choose a patient, doctor, room and time.');
            return;
        }
        if (!acknowledged && warnings.length > 0 && !closed && !conflict) {
            setShowPopup(true);
            return;
        }
        setSaving(true);
        const res = await wire.bookAppointment(
            {
                patient_id: form.patient.id,
                doctor_id: form.doctor_id ? Number(form.doctor_id) : null,
                operatory_id: Number(form.operatory_id),
                start: new Date(form.start).toISOString(),
                end: endDate.toISOString(),
                chief_complaint: form.chief_complaint || null,
            },
            acknowledged,
        );
        setSaving(false);
        if (res?.ok) {
            onBooked(res.message);
            return;
        }
        if (res?.requiresAcknowledgement) {
            setAssessment(res.assessment);
            setShowPopup(true);
            return;
        }
        if (res?.assessment) setAssessment(res.assessment);
        setError(res?.message || 'Could not book this appointment.');
    };

    const roomsByBranch = useMemo(() => {
        const g = {};
        options.rooms.forEach((r) => (g[r.branch || 'Rooms'] ??= []).push(r));
        return g;
    }, [options.rooms]);

    return (
        <div className="dc-overlay" onMouseDown={(e) => e.target === e.currentTarget && onClose()}>
            <div className="dc-dialog" role="dialog" aria-modal="true" aria-labelledby="dc-book-title">
                <div className="dc-dialog-head">
                    <div className="dc-title-icon"><CalendarPlus size={18} /></div>
                    <h3 id="dc-book-title" className="dc-dialog-title">Book appointment</h3>
                    <button type="button" className="dc-icon-btn" aria-label="Close" onClick={onClose}><X size={18} /></button>
                </div>

                <div className="dc-form">
                    {/* Patient */}
                    <div className="dc-field dc-span-2">
                        <span className="dc-label">Patient</span>
                        {form.patient ? (
                            <div className="dc-picked">
                                <div>
                                    <strong>{form.patient.name}</strong>
                                    <span className="dc-muted">
                                        {form.patient.file_number ? `File ${form.patient.file_number}` : ''}
                                        {form.patient.phone ? ` · ${form.patient.phone}` : ''}
                                    </span>
                                </div>
                                <button type="button" className="dc-btn dc-btn-ghost" onClick={() => set('patient', null)}>Change</button>
                            </div>
                        ) : (
                            <div className="dc-combo">
                                <div className="dc-input-icon">
                                    <Search size={16} />
                                    <input
                                        ref={searchRef}
                                        className="dc-input"
                                        placeholder="Search name, file number or phone…"
                                        value={query}
                                        onChange={(e) => setQuery(e.target.value)}
                                    />
                                </div>
                                <div className="dc-combo-list">
                                    {searching && results.length === 0 && <div className="dc-muted dc-pad">Searching…</div>}
                                    {!searching && results.length === 0 && <div className="dc-muted dc-pad">No patients found.</div>}
                                    {results.map((p) => (
                                        <button key={p.id} type="button" className="dc-combo-item" onClick={() => set('patient', p)}>
                                            <strong>{p.name}</strong>
                                            <span className="dc-muted">{p.file_number || p.phone}</span>
                                        </button>
                                    ))}
                                </div>
                            </div>
                        )}
                    </div>

                    {/* Doctor */}
                    {!config.isDoctorOnly && (
                        <label className="dc-field">
                            <span className="dc-label">Doctor</span>
                            <select className="dc-input" value={form.doctor_id} onChange={(e) => set('doctor_id', e.target.value)}>
                                <option value="">Select doctor…</option>
                                {options.doctors.map((d) => <option key={d.id} value={d.id}>{d.name}</option>)}
                            </select>
                        </label>
                    )}

                    {/* Room */}
                    <label className="dc-field">
                        <span className="dc-label">Room / Chair</span>
                        <select className="dc-input" value={form.operatory_id} onChange={(e) => set('operatory_id', e.target.value)}>
                            <option value="">Select room…</option>
                            {Object.entries(roomsByBranch).map(([b, rooms]) => (
                                <optgroup key={b} label={b}>
                                    {rooms.map((r) => <option key={r.id} value={r.id}>{r.name}</option>)}
                                </optgroup>
                            ))}
                        </select>
                    </label>

                    {/* Time */}
                    <label className="dc-field">
                        <span className="dc-label">Start</span>
                        <input type="datetime-local" step="900" className="dc-input" value={form.start} onChange={(e) => set('start', e.target.value)} />
                    </label>

                    <div className="dc-field">
                        <span className="dc-label">Duration</span>
                        <div className="dc-segment dc-segment-sm">
                            {DURATIONS.map((d) => (
                                <button key={d} type="button" className={form.duration === d ? 'is-active' : ''} onClick={() => set('duration', d)}>
                                    {d < 60 ? `${d}m` : `${d / 60}h`}
                                </button>
                            ))}
                        </div>
                    </div>

                    <label className="dc-field dc-span-2">
                        <span className="dc-label">Reason / chief complaint</span>
                        <input
                            className="dc-input"
                            placeholder="e.g. Toothache, scaling, routine checkup"
                            maxLength={255}
                            value={form.chief_complaint}
                            onChange={(e) => set('chief_complaint', e.target.value)}
                        />
                    </label>

                    {/* Live availability */}
                    <div className="dc-span-2">
                        {checking && <div className="dc-banner is-muted">Checking room availability…</div>}
                        {!checking && closed && (
                            <div className="dc-banner is-danger">
                                <Ban size={18} />
                                <div>
                                    <strong>{assessment.operatory_name} is closed for maintenance</strong>
                                    {assessment.closures.map((c) => (
                                        <span key={c.id}>{c.title} · {c.impact_label} · {c.starts_at_label} → {c.ends_at_label}</span>
                                    ))}
                                    <span>Pick another room or time.</span>
                                </div>
                            </div>
                        )}
                        {!checking && !closed && conflict && (
                            <div className="dc-banner is-danger">
                                <Ban size={18} />
                                <div>
                                    <strong>Time slot conflict</strong>
                                    <span>This doctor or room already has an appointment at this time.</span>
                                </div>
                            </div>
                        )}
                        {!checking && !closed && !conflict && warnings.length > 0 && (
                            <div className="dc-banner is-warning">
                                <AlertTriangle size={18} />
                                <div>
                                    <strong>Something will be missing in {assessment.operatory_name}</strong>
                                    {warnings.map((w) => (
                                        <span key={w.id}>{w.unavailable_items || w.equipment_name || w.title} — until {w.ends_at_label}</span>
                                    ))}
                                </div>
                            </div>
                        )}
                        {!checking && assessment && !closed && !conflict && warnings.length === 0 && (
                            <div className="dc-banner is-success">
                                <CheckCircle2 size={18} />
                                <div><strong>{assessment.operatory_name} is available</strong></div>
                            </div>
                        )}
                        {error && <div className="dc-banner is-danger"><Ban size={18} /><div><span>{error}</span></div></div>}
                    </div>
                </div>

                <div className="dc-dialog-actions">
                    <button type="button" className="dc-btn dc-btn-ghost" onClick={onClose}>Cancel</button>
                    <button
                        type="button"
                        className="dc-btn dc-btn-primary"
                        disabled={!ready || closed || conflict || saving}
                        onClick={() => submit(false)}
                    >
                        {saving ? 'Booking…' : 'Book appointment'}
                    </button>
                </div>
            </div>

            {showPopup && (
                <MaintenancePopup
                    roomName={assessment?.operatory_name}
                    warnings={warnings}
                    onCancel={() => setShowPopup(false)}
                    onConfirm={() => {
                        setShowPopup(false);
                        submit(true);
                    }}
                />
            )}
        </div>
    );
}
