import React, { useState, useEffect } from 'react';
import AppLayout from '../Layouts/AppLayout';
import Card from '../Components/Card';
import Button from '../Components/Button';
import Badge from '../Components/Badge';
import TextInput from '../Components/TextInput';
import InputLabel from '../Components/InputLabel';
import {
    Calendar as CalendarIcon,
    Clock,
    User,
    Building2,
    Filter,
    Plus,
    ChevronLeft,
    ChevronRight,
    AlertCircle,
    CheckCircle2,
    XCircle,
    UserCheck,
    Stethoscope
} from 'lucide-react';

export default function DetailedCalendar({ auth }) {
    const [viewMode, setViewMode] = useState('week'); // 'day' | 'week'
    const [currentDate, setCurrentDate] = useState(new Date());

    const [appointments, setAppointments] = useState([]);
    const [doctors, setDoctors] = useState([]);
    const [operatories, setOperatories] = useState([]);
    const [patients, setPatients] = useState([]);
    const [statuses, setStatuses] = useState([]);

    const [selectedDoctor, setSelectedDoctor] = useState('');
    const [selectedOperatory, setSelectedOperatory] = useState('');
    const [selectedStatus, setSelectedStatus] = useState('');
    const [loading, setLoading] = useState(false);

    // Create Modal State
    const [isCreateOpen, setIsCreateOpen] = useState(false);
    const [createData, setCreateData] = useState({
        patient_id: '',
        doctor_id: '',
        operatory_id: '',
        start_time: '',
        end_time: '',
        chief_complaint: '',
        procedure_name: '',
        consultation_fee: 50,
        notes: '',
    });
    const [createError, setCreateError] = useState('');

    // Detail / Action Modal State
    const [selectedApp, setSelectedApp] = useState(null);
    const [statusError, setStatusError] = useState('');

    const fetchCalendarData = async () => {
        setLoading(true);
        try {
            const startStr = new Date(currentDate.getFullYear(), currentDate.getMonth(), 1).toISOString();
            const endStr = new Date(currentDate.getFullYear(), currentDate.getMonth() + 2, 0).toISOString();

            let url = `/api/calendar/appointments?start=${startStr}&end=${endStr}`;
            if (selectedDoctor) url += `&doctor_id=${selectedDoctor}`;
            if (selectedOperatory) url += `&operatory_id=${selectedOperatory}`;
            if (selectedStatus) url += `&status=${selectedStatus}`;

            const res = await fetch(url, { headers: { Accept: 'application/json' } });
            if (res.ok) {
                const data = await res.json();
                setAppointments(data.appointments || []);
                setDoctors(data.doctors || []);
                setOperatories(data.operatories || []);
                setPatients(data.patients || []);
                setStatuses(data.statuses || []);
            }
        } catch (err) {
            console.error('Failed to load calendar appointments:', err);
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        fetchCalendarData();
    }, [currentDate, selectedDoctor, selectedOperatory, selectedStatus]);

    const handleCreateSubmit = async (e) => {
        e.preventDefault();
        setCreateError('');

        try {
            const res = await fetch('/api/calendar/appointments', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                },
                body: JSON.stringify(createData),
            });

            const data = await res.json();

            if (!res.ok) {
                setCreateError(data.message || 'Error creating appointment');
            } else {
                setIsCreateOpen(false);
                setCreateData({
                    patient_id: '',
                    doctor_id: '',
                    operatory_id: '',
                    start_time: '',
                    end_time: '',
                    chief_complaint: '',
                    procedure_name: '',
                    consultation_fee: 50,
                    notes: '',
                });
                fetchCalendarData();
            }
        } catch (err) {
            setCreateError('Server error creating appointment.');
        }
    };

    const handleStatusChange = async (appId, newStatus) => {
        setStatusError('');
        try {
            const res = await fetch(`/api/calendar/appointments/${appId}/status`, {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                },
                body: JSON.stringify({ status: newStatus }),
            });

            if (!res.ok) {
                const data = await res.json();
                setStatusError(data.message || 'Error updating status');
            } else {
                setSelectedApp(null);
                fetchCalendarData();
            }
        } catch (err) {
            setStatusError('Failed to update status.');
        }
    };

    const statusBadgeColor = (st) => {
        switch (st) {
            case 'booked': return 'bg-blue-500/20 text-blue-400 border-blue-500/30';
            case 'arrived': return 'bg-amber-500/20 text-amber-400 border-amber-500/30';
            case 'in_chair': return 'bg-purple-500/20 text-purple-400 border-purple-500/30';
            case 'completed': return 'bg-emerald-500/20 text-emerald-400 border-emerald-500/30';
            case 'no_show': return 'bg-slate-500/20 text-slate-400 border-slate-500/30';
            case 'cancelled': return 'bg-rose-500/20 text-rose-400 border-rose-500/30';
            default: return 'bg-slate-700 text-slate-300';
        }
    };

    const formatHour = (h) => {
        const ampm = h >= 12 ? 'PM' : 'AM';
        const displayH = h % 12 === 0 ? 12 : h % 12;
        return `${displayH}:00 ${ampm}`;
    };

    return (
        <AppLayout title="Appointments Calendar" auth={auth}>
            <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">
                {/* Header & Controls */}
                <div className="flex flex-col md:flex-row md:items-center justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-bold text-slate-900 dark:text-slate-100 flex items-center gap-2">
                            <CalendarIcon className="w-7 h-7 text-teal-500" /> Clinic Appointments Calendar
                        </h1>
                        <p className="text-sm text-slate-500 dark:text-slate-400">
                            Manage patient schedules, chairs, and doctor availability
                        </p>
                    </div>

                    <div className="flex items-center gap-3">
                        <Button
                            type="button"
                            onClick={() => setIsCreateOpen(true)}
                            className="bg-teal-500 hover:bg-teal-400 text-slate-950 font-bold flex items-center gap-2 shadow-lg shadow-teal-500/20"
                        >
                            <Plus className="w-4 h-4" /> Book Appointment
                        </Button>
                    </div>
                </div>

                {/* Filters & View Switcher Toolbar */}
                <Card className="bg-slate-900 border-slate-800 p-4">
                    <div className="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
                        {/* Date Navigation & View Mode */}
                        <div className="flex items-center space-x-3">
                            <div className="flex items-center bg-slate-800 rounded-xl p-1 border border-slate-700">
                                <button
                                    onClick={() => {
                                        const d = new Date(currentDate);
                                        d.setDate(d.getDate() - (viewMode === 'week' ? 7 : 1));
                                        setCurrentDate(d);
                                    }}
                                    className="p-1.5 rounded-lg text-slate-400 hover:text-slate-100 hover:bg-slate-700"
                                >
                                    <ChevronLeft className="w-5 h-5" />
                                </button>
                                <button
                                    onClick={() => setCurrentDate(new Date())}
                                    className="px-3 py-1 text-xs font-semibold text-teal-400 hover:underline"
                                >
                                    Today
                                </button>
                                <button
                                    onClick={() => {
                                        const d = new Date(currentDate);
                                        d.setDate(d.getDate() + (viewMode === 'week' ? 7 : 1));
                                        setCurrentDate(d);
                                    }}
                                    className="p-1.5 rounded-lg text-slate-400 hover:text-slate-100 hover:bg-slate-700"
                                >
                                    <ChevronRight className="w-5 h-5" />
                                </button>
                            </div>

                            <span className="text-slate-200 font-bold text-sm">
                                {currentDate.toLocaleDateString('en-US', { month: 'long', year: 'numeric', day: 'numeric' })}
                            </span>

                            <div className="bg-slate-800 p-1 rounded-xl flex border border-slate-700">
                                <button
                                    onClick={() => setViewMode('day')}
                                    className={`px-3 py-1 rounded-lg text-xs font-bold transition-colors ${
                                        viewMode === 'day' ? 'bg-teal-500 text-slate-950' : 'text-slate-400 hover:text-slate-200'
                                    }`}
                                >
                                    Day
                                </button>
                                <button
                                    onClick={() => setViewMode('week')}
                                    className={`px-3 py-1 rounded-lg text-xs font-bold transition-colors ${
                                        viewMode === 'week' ? 'bg-teal-500 text-slate-950' : 'text-slate-400 hover:text-slate-200'
                                    }`}
                                >
                                    Week
                                </button>
                            </div>
                        </div>

                        {/* Filter Selects */}
                        <div className="flex flex-wrap items-center gap-3">
                            <select
                                className="bg-slate-800 border-slate-700 text-slate-200 text-xs rounded-xl p-2.5"
                                value={selectedDoctor}
                                onChange={(e) => setSelectedDoctor(e.target.value)}
                            >
                                <option value="">All Doctors</option>
                                {doctors.map((doc) => (
                                    <option key={doc.id} value={doc.id}>{doc.name}</option>
                                ))}
                            </select>

                            <select
                                className="bg-slate-800 border-slate-700 text-slate-200 text-xs rounded-xl p-2.5"
                                value={selectedOperatory}
                                onChange={(e) => setSelectedOperatory(e.target.value)}
                            >
                                <option value="">All Operatories / Chairs</option>
                                {operatories.map((op) => (
                                    <option key={op.id} value={op.id}>{op.name}</option>
                                ))}
                            </select>

                            <select
                                className="bg-slate-800 border-slate-700 text-slate-200 text-xs rounded-xl p-2.5"
                                value={selectedStatus}
                                onChange={(e) => setSelectedStatus(e.target.value)}
                            >
                                <option value="">All Statuses</option>
                                {statuses.map((st) => (
                                    <option key={st} value={st}>{st.replace('_', ' ')}</option>
                                ))}
                            </select>
                        </div>
                    </div>
                </Card>

                {/* Calendar Grid View */}
                <Card className="bg-slate-900 border-slate-800 p-6 overflow-x-auto shadow-2xl">
                    {loading ? (
                        <div className="p-12 text-center text-slate-400">Loading appointments...</div>
                    ) : (
                        <div className="space-y-4">
                            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                                {appointments.length === 0 ? (
                                    <div className="col-span-full p-12 text-center text-slate-500">
                                        No appointments scheduled for selected criteria.
                                    </div>
                                ) : (
                                    appointments.map((app) => (
                                        <div
                                            key={app.id}
                                            onClick={() => setSelectedApp(app)}
                                            className="bg-slate-800/80 hover:bg-slate-800 p-4 rounded-xl border border-slate-700 cursor-pointer transition-all hover:border-teal-500/50 space-y-3"
                                        >
                                            <div className="flex items-center justify-between">
                                                <span className={`text-[10px] font-extrabold uppercase tracking-wider px-2 py-0.5 rounded-full border ${statusBadgeColor(app.status)}`}>
                                                    {app.status.replace('_', ' ')}
                                                </span>
                                                <span className="text-xs text-slate-400 font-mono">
                                                    ${app.consultation_fee}
                                                </span>
                                            </div>

                                            <div>
                                                <h3 className="font-bold text-slate-100 text-sm truncate">{app.patient_name}</h3>
                                                <p className="text-xs text-slate-400 truncate">{app.procedure_name || app.chief_complaint || 'General Consultation'}</p>
                                            </div>

                                            <div className="text-xs space-y-1 text-slate-400 pt-2 border-t border-slate-700/60">
                                                <div className="flex items-center gap-2">
                                                    <Clock className="w-3.5 h-3.5 text-teal-400" />
                                                    <span>
                                                        {new Date(app.start_time).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })} - {new Date(app.end_time).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}
                                                    </span>
                                                </div>
                                                <div className="flex items-center gap-2">
                                                    <Stethoscope className="w-3.5 h-3.5 text-purple-400" />
                                                    <span className="truncate">{app.doctor_name}</span>
                                                </div>
                                                <div className="flex items-center gap-2">
                                                    <Building2 className="w-3.5 h-3.5 text-amber-400" />
                                                    <span className="truncate">{app.operatory_name}</span>
                                                </div>
                                            </div>
                                        </div>
                                    ))
                                )}
                            </div>
                        </div>
                    )}
                </Card>
            </div>

            {/* Create Appointment Modal */}
            {isCreateOpen && (
                <div className="fixed inset-0 z-50 bg-black/70 backdrop-blur-sm flex items-center justify-center p-4">
                    <div className="bg-slate-900 border border-slate-800 rounded-2xl max-w-lg w-full p-6 space-y-4 shadow-2xl">
                        <h2 className="text-xl font-bold text-slate-100 border-b border-slate-800 pb-3">Book New Appointment</h2>

                        {createError && (
                            <div className="p-3 bg-rose-500/20 border border-rose-500 text-rose-300 rounded-lg text-xs flex items-center gap-2">
                                <AlertCircle className="w-4 h-4 shrink-0" /> {createError}
                            </div>
                        )}

                        <form onSubmit={handleCreateSubmit} className="space-y-4">
                            <div>
                                <InputLabel value="Patient" className="text-slate-300" />
                                <select
                                    required
                                    className="w-full mt-1 bg-slate-800 border-slate-700 text-slate-100 rounded-lg p-2.5 text-sm"
                                    value={createData.patient_id}
                                    onChange={(e) => setCreateData({ ...createData, patient_id: e.target.value })}
                                >
                                    <option value="">Select Patient</option>
                                    {patients.map((p) => (
                                        <option key={p.id} value={p.id}>{p.first_name} {p.last_name} ({p.phone})</option>
                                    ))}
                                </select>
                            </div>

                            <div className="grid grid-cols-2 gap-3">
                                <div>
                                    <InputLabel value="Doctor" className="text-slate-300" />
                                    <select
                                        required
                                        className="w-full mt-1 bg-slate-800 border-slate-700 text-slate-100 rounded-lg p-2.5 text-sm"
                                        value={createData.doctor_id}
                                        onChange={(e) => setCreateData({ ...createData, doctor_id: e.target.value })}
                                    >
                                        <option value="">Select Doctor</option>
                                        {doctors.map((d) => (
                                            <option key={d.id} value={d.id}>{d.name}</option>
                                        ))}
                                    </select>
                                </div>
                                <div>
                                    <InputLabel value="Operatory / Chair" className="text-slate-300" />
                                    <select
                                        required
                                        className="w-full mt-1 bg-slate-800 border-slate-700 text-slate-100 rounded-lg p-2.5 text-sm"
                                        value={createData.operatory_id}
                                        onChange={(e) => setCreateData({ ...createData, operatory_id: e.target.value })}
                                    >
                                        <option value="">Select Operatory</option>
                                        {operatories.map((op) => (
                                            <option key={op.id} value={op.id}>{op.name}</option>
                                        ))}
                                    </select>
                                </div>
                            </div>

                            <div className="grid grid-cols-2 gap-3">
                                <div>
                                    <InputLabel value="Start Time" className="text-slate-300" />
                                    <TextInput
                                        type="datetime-local"
                                        required
                                        className="w-full mt-1 bg-slate-800 border-slate-700 text-slate-100 text-xs"
                                        value={createData.start_time}
                                        onChange={(e) => setCreateData({ ...createData, start_time: e.target.value })}
                                    />
                                </div>
                                <div>
                                    <InputLabel value="End Time" className="text-slate-300" />
                                    <TextInput
                                        type="datetime-local"
                                        required
                                        className="w-full mt-1 bg-slate-800 border-slate-700 text-slate-100 text-xs"
                                        value={createData.end_time}
                                        onChange={(e) => setCreateData({ ...createData, end_time: e.target.value })}
                                    />
                                </div>
                            </div>

                            <div>
                                <InputLabel value="Chief Complaint / Procedure" className="text-slate-300" />
                                <TextInput
                                    type="text"
                                    className="w-full mt-1 bg-slate-800 border-slate-700 text-slate-100"
                                    placeholder="e.g. Toothache / Composite Filling"
                                    value={createData.chief_complaint}
                                    onChange={(e) => setCreateData({ ...createData, chief_complaint: e.target.value })}
                                />
                            </div>

                            <div className="flex justify-end space-x-3 pt-4 border-t border-slate-800">
                                <Button
                                    type="button"
                                    onClick={() => setIsCreateOpen(false)}
                                    className="bg-slate-800 hover:bg-slate-700 text-slate-300"
                                >
                                    Cancel
                                </Button>
                                <Button
                                    type="submit"
                                    className="bg-teal-500 hover:bg-teal-400 text-slate-950 font-bold"
                                >
                                    Book Appointment
                                </Button>
                            </div>
                        </form>
                    </div>
                </div>
            )}

            {/* Appointment Detail & Status Update Modal */}
            {selectedApp && (
                <div className="fixed inset-0 z-50 bg-black/70 backdrop-blur-sm flex items-center justify-center p-4">
                    <div className="bg-slate-900 border border-slate-800 rounded-2xl max-w-md w-full p-6 space-y-4 shadow-2xl">
                        <div className="flex items-center justify-between border-b border-slate-800 pb-3">
                            <h2 className="text-lg font-bold text-slate-100">{selectedApp.patient_name}</h2>
                            <span className={`text-[10px] font-extrabold uppercase px-2 py-0.5 rounded-full border ${statusBadgeColor(selectedApp.status)}`}>
                                {selectedApp.status.replace('_', ' ')}
                            </span>
                        </div>

                        {statusError && (
                            <div className="p-3 bg-rose-500/20 border border-rose-500 text-rose-300 rounded-lg text-xs">
                                {statusError}
                            </div>
                        )}

                        <div className="space-y-2 text-sm text-slate-300">
                            <p><strong className="text-slate-400">Doctor:</strong> {selectedApp.doctor_name}</p>
                            <p><strong className="text-slate-400">Operatory:</strong> {selectedApp.operatory_name}</p>
                            <p><strong className="text-slate-400">Time:</strong> {new Date(selectedApp.start_time).toLocaleString()}</p>
                            <p><strong className="text-slate-400">Complaint:</strong> {selectedApp.chief_complaint || 'N/A'}</p>
                        </div>

                        <div className="pt-2 border-t border-slate-800 space-y-2">
                            <InputLabel value="Update Status" className="text-slate-400 text-xs" />
                            <div className="grid grid-cols-3 gap-2">
                                {statuses.map((st) => (
                                    <button
                                        key={st}
                                        onClick={() => handleStatusChange(selectedApp.id, st)}
                                        className={`px-2 py-1.5 rounded-lg text-xs font-bold border transition-colors ${
                                            selectedApp.status === st ? 'bg-teal-500 text-slate-950 border-teal-400' : 'bg-slate-800 text-slate-300 border-slate-700 hover:bg-slate-700'
                                        }`}
                                    >
                                        {st.replace('_', ' ')}
                                    </button>
                                ))}
                            </div>
                        </div>

                        <div className="flex justify-end pt-4">
                            <Button onClick={() => setSelectedApp(null)} className="bg-slate-800 hover:bg-slate-700 text-slate-300">
                                Close
                            </Button>
                        </div>
                    </div>
                </div>
            )}
        </AppLayout>
    );
}
