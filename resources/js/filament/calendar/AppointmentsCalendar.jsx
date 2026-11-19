import React, { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import FullCalendar from '@fullcalendar/react';
import dayGridPlugin from '@fullcalendar/daygrid';
import timeGridPlugin from '@fullcalendar/timegrid';
import interactionPlugin from '@fullcalendar/interaction';
import listPlugin from '@fullcalendar/list';
import arLocale from '@fullcalendar/core/locales/ar';
import {
    CalendarDays,
    ChevronLeft,
    ChevronRight,
    DoorClosed,
    Plus,
    RefreshCw,
    Stethoscope,
    Wrench,
} from 'lucide-react';
import BookingModal from './BookingModal';
import EventDrawer from './EventDrawer';
import MaintenancePopup from './MaintenancePopup';
import { Toasts, useToasts } from './Toasts';
import { STATUS_META, formatTime } from './utils';

const VIEWS = [
    { id: 'dayGridMonth', label: 'Month' },
    { id: 'timeGridWeek', label: 'Week' },
    { id: 'timeGridDay', label: 'Day' },
    { id: 'listWeek', label: 'List' },
];

export default function AppointmentsCalendar({ wire, config }) {
    const calendarRef = useRef(null);
    const filtersRef = useRef({ doctor_id: '', operatory_id: '', status: '' });
    const isRtl = document.documentElement.dir === 'rtl';
    const isNarrow = typeof window !== 'undefined' && window.innerWidth < 768;

    const [title, setTitle] = useState('');
    const [view, setView] = useState(isNarrow ? 'listWeek' : 'timeGridWeek');
    const [filters, setFilters] = useState(filtersRef.current);
    const [options, setOptions] = useState({ doctors: [], rooms: [] });
    const [maintenance, setMaintenance] = useState([]);
    const [loading, setLoading] = useState(false);
    const [counts, setCounts] = useState({ total: 0, today: 0 });

    const [booking, setBooking] = useState(null); // { start, end } | null
    const [selected, setSelected] = useState(null); // FullCalendar event
    const [pendingMove, setPendingMove] = useState(null); // { info, assessment }
    const { toasts, push } = useToasts();

    const api = () => calendarRef.current?.getApi();

    useEffect(() => {
        wire.fetchOptions().then((o) => o && setOptions(o));
    }, [wire]);

    // 30s polling, like the previous table widget
    useEffect(() => {
        const t = setInterval(() => api()?.refetchEvents(), 30000);
        return () => clearInterval(t);
    }, []);

    const updateFilter = (key, value) => {
        const next = { ...filtersRef.current, [key]: value };
        filtersRef.current = next;
        setFilters(next);
        api()?.refetchEvents();
    };

    const fetchEvents = useCallback(
        (info, success, failure) => {
            setLoading(true);
            wire.fetchEvents(info.startStr, info.endStr, filtersRef.current)
                .then((res) => {
                    const events = res?.events ?? [];
                    setMaintenance(res?.maintenance ?? []);
                    const appts = events.filter((e) => e.extendedProps?.kind === 'appointment');
                    const todayStr = new Date().toDateString();
                    setCounts({
                        total: appts.length,
                        today: appts.filter((e) => new Date(e.start).toDateString() === todayStr).length,
                    });
                    success(events);
                })
                .catch((err) => failure(err))
                .finally(() => setLoading(false));
        },
        [wire],
    );

    const changeView = (id) => {
        setView(id);
        api()?.changeView(id);
    };

    const openBooking = (start, end) => {
        setSelected(null);
        setBooking({ start, end });
    };

    // ── Drag & drop / resize reschedule ─────────────────────────────
    const commitMove = async (info, acknowledged = false) => {
        const id = info.event.extendedProps.appointmentId;
        const res = await wire.rescheduleAppointment(id, info.event.start.toISOString(), info.event.end.toISOString(), acknowledged);
        if (res?.ok) {
            push('success', res.message);
            api()?.refetchEvents();
            return;
        }
        if (res?.requiresAcknowledgement) {
            setPendingMove({ info, assessment: res.assessment });
            return;
        }
        info.revert();
        push('danger', res?.message || 'Could not move this appointment.');
    };

    const onMoveConfirmed = async () => {
        const { info } = pendingMove;
        setPendingMove(null);
        await commitMove(info, true);
    };

    const onMoveCancelled = () => {
        pendingMove?.info.revert();
        setPendingMove(null);
    };

    // ── Rendering helpers ───────────────────────────────────────────
    const renderEvent = (arg) => {
        const p = arg.event.extendedProps;
        if (p.kind === 'maintenance') {
            if (arg.view.type.startsWith('list')) {
                return (
                    <span className="dc-list-maint">
                        <Wrench size={14} /> {arg.event.title} · {p.rooms}
                    </span>
                );
            }
            return arg.view.type === 'dayGridMonth' ? null : (
                <div className="dc-maint-label">
                    {p.impact === 'no_closure' ? <Wrench size={12} /> : <DoorClosed size={12} />}
                    <span>{arg.event.title}</span>
                    <span className="dc-maint-rooms">{p.rooms}</span>
                </div>
            );
        }

        const meta = STATUS_META[p.status] ?? STATUS_META.booked;
        if (arg.view.type === 'dayGridMonth') {
            return (
                <div className="dc-ev-month">
                    <span className="dc-dot" style={{ background: meta.color }} />
                    <span className="dc-ev-time">{arg.timeText}</span>
                    <span className="dc-ev-name">{arg.event.title}</span>
                </div>
            );
        }
        if (arg.view.type.startsWith('list')) {
            return (
                <div className="dc-ev-list">
                    <strong>{arg.event.title}</strong>
                    <span className="dc-chip">{p.operatoryName}</span>
                    {!config.isDoctorOnly && <span className="dc-muted">{p.doctorName}</span>}
                    <span className="dc-status" style={{ '--c': meta.color }}>{meta.label}</span>
                    {(p.procedure || p.complaint) && <span className="dc-muted">{p.procedure || p.complaint}</span>}
                </div>
            );
        }
        return (
            <div className="dc-ev-body">
                <div className="dc-ev-top">
                    <span className="dc-ev-time">{arg.timeText}</span>
                    <span className="dc-status-pill" style={{ '--c': meta.color }}>{meta.short}</span>
                </div>
                <div className="dc-ev-name">{arg.event.title}</div>
                <div className="dc-ev-meta">
                    {p.operatoryName}
                    {!config.isDoctorOnly && p.doctorName ? ` · ${p.doctorName}` : ''}
                </div>
                {(p.procedure || p.complaint) && <div className="dc-ev-meta dc-ev-reason">{p.procedure || p.complaint}</div>}
            </div>
        );
    };

    const eventClassNames = (arg) => {
        const p = arg.event.extendedProps;
        if (p.kind !== 'appointment') return [];
        return ['dc-ev', `dc-ev--${p.status}`];
    };

    const roomsByBranch = useMemo(() => {
        const groups = {};
        options.rooms.forEach((r) => {
            (groups[r.branch || 'Rooms'] ??= []).push(r);
        });
        return groups;
    }, [options.rooms]);

    return (
        <div className="dc-calendar" dir={isRtl ? 'rtl' : 'ltr'}>
            {/* Header */}
            <div className="dc-header">
                <div className="dc-title-block">
                    <div className="dc-title-icon"><CalendarDays size={20} /></div>
                    <div>
                        <h2 className="dc-heading">{config.heading}</h2>
                        <p className="dc-subheading">
                            {counts.today} today · {counts.total} in view
                            {maintenance.length > 0 && (
                                <span className="dc-sub-maint"> · {maintenance.length} maintenance</span>
                            )}
                        </p>
                    </div>
                </div>
                <div className="dc-header-actions">
                    <a className="dc-btn dc-btn-ghost" href={config.urls.appointments}>All appointments</a>
                    {config.canBook && (
                        <button type="button" className="dc-btn dc-btn-primary" onClick={() => openBooking(null, null)}>
                            <Plus size={16} /> Book appointment
                        </button>
                    )}
                </div>
            </div>

            {/* Toolbar */}
            <div className="dc-toolbar">
                <div className="dc-nav">
                    <button type="button" className="dc-icon-btn" aria-label="Previous" onClick={() => api()?.prev()}>
                        {isRtl ? <ChevronRight size={18} /> : <ChevronLeft size={18} />}
                    </button>
                    <button type="button" className="dc-btn dc-btn-ghost" onClick={() => api()?.today()}>Today</button>
                    <button type="button" className="dc-icon-btn" aria-label="Next" onClick={() => api()?.next()}>
                        {isRtl ? <ChevronLeft size={18} /> : <ChevronRight size={18} />}
                    </button>
                    <span className="dc-range-title">{title}</span>
                    {loading && <RefreshCw size={14} className="dc-spin" />}
                </div>

                <div className="dc-filters">
                    {!config.isDoctorOnly && (
                        <label className="dc-select-wrap">
                            <Stethoscope size={14} />
                            <select value={filters.doctor_id} onChange={(e) => updateFilter('doctor_id', e.target.value)}>
                                <option value="">All doctors</option>
                                {options.doctors.map((d) => <option key={d.id} value={d.id}>{d.name}</option>)}
                            </select>
                        </label>
                    )}
                    <label className="dc-select-wrap">
                        <DoorClosed size={14} />
                        <select value={filters.operatory_id} onChange={(e) => updateFilter('operatory_id', e.target.value)}>
                            <option value="">All rooms</option>
                            {Object.entries(roomsByBranch).map(([branch, rooms]) => (
                                <optgroup key={branch} label={branch}>
                                    {rooms.map((r) => <option key={r.id} value={r.id}>{r.name}</option>)}
                                </optgroup>
                            ))}
                        </select>
                    </label>
                    <label className="dc-select-wrap">
                        <span className="dc-dot" style={{ background: STATUS_META[filters.status]?.color ?? 'rgb(var(--gray-400))' }} />
                        <select value={filters.status} onChange={(e) => updateFilter('status', e.target.value)}>
                            <option value="">All statuses</option>
                            {Object.entries(config.statuses).map(([k, v]) => <option key={k} value={k}>{v}</option>)}
                        </select>
                    </label>
                    <div className="dc-segment" role="tablist">
                        {VIEWS.map((v) => (
                            <button
                                key={v.id}
                                type="button"
                                role="tab"
                                aria-selected={view === v.id}
                                className={view === v.id ? 'is-active' : ''}
                                onClick={() => changeView(v.id)}
                            >
                                {v.label}
                            </button>
                        ))}
                    </div>
                </div>
            </div>

            {/* Active maintenance strip */}
            {maintenance.length > 0 && (
                <div className="dc-maint-strip">
                    {maintenance.map((m) => (
                        <a
                            key={m.id}
                            href={config.urls.maintenance}
                            className={`dc-maint-chip ${m.impact === 'no_closure' ? 'is-partial' : 'is-closed'}`}
                            title={m.unavailable || m.equipment || ''}
                        >
                            {m.impact === 'no_closure' ? <Wrench size={13} /> : <DoorClosed size={13} />}
                            <span className="dc-maint-chip-title">{m.title.replace(/^(Closed|Maintenance): /, '')}</span>
                            <span className="dc-maint-chip-meta">
                                {m.impactLabel} · {m.rooms} · until {formatTime(m.end, true)}
                            </span>
                        </a>
                    ))}
                </div>
            )}

            {/* Legend */}
            <div className="dc-legend">
                {['booked', 'arrived', 'in_chair', 'completed', 'cancelled'].map((s) => (
                    <span key={s}><span className="dc-dot" style={{ background: STATUS_META[s].color }} />{STATUS_META[s].label}</span>
                ))}
                <span><span className="dc-swatch is-closed" />Room closed</span>
                <span><span className="dc-swatch is-partial" />Equipment missing</span>
            </div>

            <div className="dc-fc">
                <FullCalendar
                    ref={calendarRef}
                    plugins={[dayGridPlugin, timeGridPlugin, interactionPlugin, listPlugin]}
                    initialView={view}
                    headerToolbar={false}
                    locales={[arLocale]}
                    locale={config.locale === 'ar' ? 'ar' : 'en'}
                    direction={isRtl ? 'rtl' : 'ltr'}
                    height="auto"
                    expandRows
                    nowIndicator
                    allDaySlot={false}
                    slotMinTime="07:00:00"
                    slotMaxTime="23:00:00"
                    scrollTime="08:00:00"
                    slotDuration="00:15:00"
                    slotLabelInterval="01:00"
                    snapDuration="00:15:00"
                    dayMaxEvents={4}
                    eventMinHeight={28}
                    firstDay={6}
                    selectable={config.canBook}
                    selectMirror
                    editable
                    eventDurationEditable
                    eventOverlap
                    longPressDelay={350}
                    events={fetchEvents}
                    eventContent={renderEvent}
                    eventClassNames={eventClassNames}
                    eventTimeFormat={{ hour: 'numeric', minute: '2-digit', meridiem: 'short' }}
                    slotLabelFormat={{ hour: 'numeric', meridiem: 'short' }}
                    datesSet={(arg) => setTitle(arg.view.title)}
                    select={(info) => {
                        api()?.unselect();
                        if (info.view.type === 'dayGridMonth') {
                            const s = new Date(info.start);
                            s.setHours(9, 0, 0, 0);
                            openBooking(s, null);
                        } else {
                            openBooking(info.start, info.end);
                        }
                    }}
                    eventClick={(info) => {
                        info.jsEvent.preventDefault();
                        if (info.event.extendedProps.kind === 'appointment') setSelected(info.event);
                    }}
                    eventDrop={(info) => commitMove(info)}
                    eventResize={(info) => commitMove(info)}
                    noEventsContent={() => (
                        <div className="dc-empty">
                            <CalendarDays size={28} />
                            <p>No appointments in this period.</p>
                            {config.canBook && (
                                <button type="button" className="dc-btn dc-btn-primary" onClick={() => openBooking(null, null)}>
                                    <Plus size={16} /> Book appointment
                                </button>
                            )}
                        </div>
                    )}
                />
            </div>

            {booking && (
                <BookingModal
                    wire={wire}
                    config={config}
                    options={options}
                    initialStart={booking.start}
                    initialEnd={booking.end}
                    initialRoom={filters.operatory_id}
                    initialDoctor={config.isDoctorOnly ? config.currentUserId : filters.doctor_id}
                    onClose={() => setBooking(null)}
                    onBooked={(msg) => {
                        setBooking(null);
                        push('success', msg);
                        api()?.refetchEvents();
                    }}
                />
            )}

            {selected && (
                <EventDrawer
                    event={selected}
                    config={config}
                    wire={wire}
                    onClose={() => setSelected(null)}
                    onChanged={(msg, ok = true) => {
                        push(ok ? 'success' : 'danger', msg);
                        if (ok) {
                            setSelected(null);
                            api()?.refetchEvents();
                        }
                    }}
                />
            )}

            {pendingMove && (
                <MaintenancePopup
                    roomName={pendingMove.assessment?.operatory_name}
                    warnings={pendingMove.assessment?.warnings ?? []}
                    confirmLabel="Move anyway"
                    onConfirm={onMoveConfirmed}
                    onCancel={onMoveCancelled}
                />
            )}

            <Toasts toasts={toasts} />
        </div>
    );
}
