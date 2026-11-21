export const STATUS_META = {
    booked: { label: 'Booked', short: 'Booked', color: 'rgb(var(--primary-500))' },
    arrived: { label: 'Arrived (Waiting)', short: 'Waiting', color: '#f59e0b' },
    in_chair: { label: 'In Chair', short: 'In chair', color: '#f43f5e' },
    completed: { label: 'Completed', short: 'Done', color: '#10b981' },
    cancelled: { label: 'Cancelled', short: 'Cancelled', color: '#94a3b8' },
    no_show: { label: 'No Show', short: 'No show', color: '#64748b' },
};

export const TRANSITION_LABELS = {
    arrived: 'Patient arrived',
    in_chair: 'Seat in chair',
    completed: 'Complete visit',
    cancelled: 'Cancel',
    no_show: 'No show',
    booked: 'Re-book',
};

const pad = (n) => String(n).padStart(2, '0');

/** Date -> value for <input type="datetime-local"> in local time. */
export function toLocalInput(date) {
    const d = date instanceof Date ? date : new Date(date);
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
}

export function formatTime(value, withDay = false) {
    if (!value) return '';
    const d = new Date(value);
    const time = d.toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' });
    if (!withDay) return time;
    return `${d.toLocaleDateString([], { weekday: 'short', day: 'numeric', month: 'short' })}, ${time}`;
}

export function nextQuarterHour() {
    const d = new Date();
    d.setMinutes(Math.ceil((d.getMinutes() + 1) / 15) * 15, 0, 0);
    return d;
}
