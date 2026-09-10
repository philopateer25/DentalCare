import React, { useState, useEffect } from 'react';
import AppLayout from '../Layouts/AppLayout';
import Card from '../Components/Card';
import Button from '../Components/Button';
import Badge from '../Components/Badge';
import TextInput from '../Components/TextInput';
import InputLabel from '../Components/InputLabel';
import {
    LifeBuoy,
    Plus,
    MessageSquare,
    Send,
    CheckCircle2,
    Clock,
    AlertCircle,
    User,
    Building2,
    ShieldAlert
} from 'lucide-react';

export default function ClinicSupport({ auth }) {
    const [tickets, setTickets] = useState([]);
    const [loading, setLoading] = useState(true);
    const [selectedStatus, setSelectedStatus] = useState('');
    const [selectedPriority, setSelectedPriority] = useState('');

    // Create Modal
    const [isCreateOpen, setIsCreateOpen] = useState(false);
    const [subject, setSubject] = useState('');
    const [message, setMessage] = useState('');
    const [priority, setPriority] = useState('medium');
    const [createError, setCreateError] = useState('');

    // Detail Modal
    const [selectedTicket, setSelectedTicket] = useState(null);
    const [replyMessage, setReplyMessage] = useState('');
    const [replyError, setReplyError] = useState('');

    const fetchTickets = async () => {
        setLoading(true);
        try {
            let url = '/api/support/tickets?';
            if (selectedStatus) url += `&status=${selectedStatus}`;
            if (selectedPriority) url += `&priority=${selectedPriority}`;

            const res = await fetch(url, { headers: { Accept: 'application/json' } });
            if (res.ok) {
                const data = await res.json();
                setTickets(data.tickets || []);
            }
        } catch (err) {
            console.error('Failed to fetch tickets:', err);
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        fetchTickets();
    }, [selectedStatus, selectedPriority]);

    const handleCreateTicket = async (e) => {
        e.preventDefault();
        setCreateError('');

        try {
            const res = await fetch('/api/support/tickets', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                },
                body: JSON.stringify({ subject, message, priority }),
            });

            const data = await res.json();
            if (!res.ok) {
                setCreateError(data.message || 'Validation error');
            } else {
                setIsCreateOpen(false);
                setSubject('');
                setMessage('');
                fetchTickets();
            }
        } catch (err) {
            setCreateError('Failed to submit support ticket.');
        }
    };

    const handleSendReply = async (e) => {
        e.preventDefault();
        if (!replyMessage.trim() || !selectedTicket) return;

        setReplyError('');
        try {
            const res = await fetch(`/api/support/tickets/${selectedTicket.id}/reply`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                },
                body: JSON.stringify({ message: replyMessage }),
            });

            const data = await res.json();
            if (!res.ok) {
                setReplyError(data.message || 'Error sending reply');
            } else {
                setReplyMessage('');
                setSelectedTicket(data.ticket);
                fetchTickets();
            }
        } catch (err) {
            setReplyError('Failed to send reply.');
        }
    };

    const statusColor = (st) => {
        switch (st) {
            case 'open': return 'bg-rose-500/20 text-rose-400 border-rose-500/30';
            case 'in_progress': return 'bg-amber-500/20 text-amber-400 border-amber-500/30';
            case 'waiting': return 'bg-blue-500/20 text-blue-400 border-blue-500/30';
            case 'resolved': return 'bg-emerald-500/20 text-emerald-400 border-emerald-500/30';
            case 'closed': return 'bg-slate-500/20 text-slate-400 border-slate-500/30';
            default: return 'bg-slate-700 text-slate-300';
        }
    };

    return (
        <AppLayout title="Clinic Helpdesk & Support" auth={auth}>
            <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">
                {/* Header */}
                <div className="flex flex-col md:flex-row md:items-center justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-bold text-slate-900 dark:text-slate-100 flex items-center gap-2">
                            <LifeBuoy className="w-7 h-7 text-teal-500" /> DentalCare SaaS Helpdesk & Support
                        </h1>
                        <p className="text-sm text-slate-500 dark:text-slate-400">
                            Submit support requests, report technical issues, or contact system assistance
                        </p>
                    </div>

                    <Button
                        onClick={() => setIsCreateOpen(true)}
                        className="bg-teal-500 hover:bg-teal-400 text-slate-950 font-bold flex items-center gap-2 shadow-lg shadow-teal-500/20"
                    >
                        <Plus className="w-4 h-4" /> Create Support Ticket
                    </Button>
                </div>

                {/* Filter Toolbar */}
                <Card className="bg-slate-900 border-slate-800 p-4 flex flex-wrap items-center justify-between gap-3">
                    <div className="flex items-center space-x-3">
                        <select
                            className="bg-slate-800 border-slate-700 text-slate-200 text-xs rounded-xl p-2.5"
                            value={selectedStatus}
                            onChange={(e) => setSelectedStatus(e.target.value)}
                        >
                            <option value="">All Statuses</option>
                            <option value="open">Open</option>
                            <option value="in_progress">In Progress</option>
                            <option value="waiting">Waiting on Customer</option>
                            <option value="resolved">Resolved</option>
                            <option value="closed">Closed</option>
                        </select>

                        <select
                            className="bg-slate-800 border-slate-700 text-slate-200 text-xs rounded-xl p-2.5"
                            value={selectedPriority}
                            onChange={(e) => setSelectedPriority(e.target.value)}
                        >
                            <option value="">All Priorities</option>
                            <option value="low">Low</option>
                            <option value="medium">Medium</option>
                            <option value="high">High</option>
                            <option value="urgent">Urgent</option>
                        </select>
                    </div>
                </Card>

                {/* Tickets Table / List */}
                <Card className="bg-slate-900 border-slate-800 p-6">
                    {loading ? (
                        <div className="p-12 text-center text-slate-400">Loading support tickets...</div>
                    ) : tickets.length === 0 ? (
                        <div className="p-12 text-center text-slate-500">
                            No support tickets found. Click "Create Support Ticket" to submit a request.
                        </div>
                    ) : (
                        <div className="space-y-3">
                            {tickets.map((t) => (
                                <div
                                    key={t.id}
                                    onClick={() => setSelectedTicket(t)}
                                    className="bg-slate-800/60 hover:bg-slate-800 p-4 rounded-xl border border-slate-700/60 flex items-center justify-between cursor-pointer transition-colors"
                                >
                                    <div className="space-y-1">
                                        <div className="flex items-center space-x-2">
                                            <span className="text-xs font-mono font-bold text-teal-400">{t.ticket_number}</span>
                                            <span className={`text-[10px] font-bold uppercase px-2 py-0.5 rounded-full border ${statusColor(t.status)}`}>
                                                {t.status.replace('_', ' ')}
                                            </span>
                                            {t.practice && (
                                                <span className="text-xs text-slate-500 flex items-center gap-1">
                                                    <Building2 className="w-3 h-3 inline" /> {t.practice.name}
                                                </span>
                                            )}
                                        </div>
                                        <h3 className="text-sm font-bold text-slate-100">{t.subject}</h3>
                                        <p className="text-xs text-slate-400 line-clamp-1">{t.message}</p>
                                    </div>

                                    <div className="text-right text-xs text-slate-500">
                                        <span>{new Date(t.created_at).toLocaleDateString()}</span>
                                    </div>
                                </div>
                            ))}
                        </div>
                    )}
                </Card>
            </div>

            {/* Create Ticket Modal */}
            {isCreateOpen && (
                <div className="fixed inset-0 z-50 bg-black/70 backdrop-blur-sm flex items-center justify-center p-4">
                    <div className="bg-slate-900 border border-slate-800 rounded-2xl max-w-lg w-full p-6 space-y-4 shadow-2xl">
                        <h2 className="text-xl font-bold text-slate-100 border-b border-slate-800 pb-3">Create Support Request</h2>

                        {createError && (
                            <div className="p-3 bg-rose-500/20 border border-rose-500 text-rose-300 rounded-lg text-xs">
                                {createError}
                            </div>
                        )}

                        <form onSubmit={handleCreateTicket} className="space-y-4">
                            <div>
                                <InputLabel value="Subject" className="text-slate-300" />
                                <TextInput
                                    type="text"
                                    required
                                    className="w-full mt-1 bg-slate-800 border-slate-700 text-slate-100"
                                    placeholder="Brief summary of issue"
                                    value={subject}
                                    onChange={(e) => setSubject(e.target.value)}
                                />
                            </div>

                            <div>
                                <InputLabel value="Priority" className="text-slate-300" />
                                <select
                                    className="w-full mt-1 bg-slate-800 border-slate-700 text-slate-100 rounded-lg p-2.5 text-sm"
                                    value={priority}
                                    onChange={(e) => setPriority(e.target.value)}
                                >
                                    <option value="low">Low</option>
                                    <option value="medium">Medium</option>
                                    <option value="high">High</option>
                                    <option value="urgent">Urgent</option>
                                </select>
                            </div>

                            <div>
                                <InputLabel value="Detailed Message" className="text-slate-300" />
                                <textarea
                                    required
                                    rows={4}
                                    className="w-full mt-1 bg-slate-800 border-slate-700 text-slate-100 rounded-lg p-3 text-sm"
                                    placeholder="Describe the issue, error messages, or assistance required..."
                                    value={message}
                                    onChange={(e) => setMessage(e.target.value)}
                                />
                            </div>

                            <div className="flex justify-end space-x-3 pt-4 border-t border-slate-800">
                                <Button type="button" onClick={() => setIsCreateOpen(false)} className="bg-slate-800 hover:bg-slate-700 text-slate-300">
                                    Cancel
                                </Button>
                                <Button type="submit" className="bg-teal-500 hover:bg-teal-400 text-slate-950 font-bold">
                                    Submit Ticket
                                </Button>
                            </div>
                        </form>
                    </div>
                </div>
            )}

            {/* Ticket Thread & Reply Drawer */}
            {selectedTicket && (
                <div className="fixed inset-0 z-50 bg-black/70 backdrop-blur-sm flex items-center justify-center p-4">
                    <div className="bg-slate-900 border border-slate-800 rounded-2xl max-w-2xl w-full p-6 space-y-4 shadow-2xl max-h-[90vh] flex flex-col">
                        <div className="flex items-center justify-between border-b border-slate-800 pb-3">
                            <div>
                                <span className="text-xs font-mono font-bold text-teal-400">{selectedTicket.ticket_number}</span>
                                <h2 className="text-lg font-bold text-slate-100">{selectedTicket.subject}</h2>
                            </div>
                            <span className={`text-[10px] font-bold uppercase px-2 py-0.5 rounded-full border ${statusColor(selectedTicket.status)}`}>
                                {selectedTicket.status.replace('_', ' ')}
                            </span>
                        </div>

                        {/* Messages Thread */}
                        <div className="flex-1 overflow-y-auto space-y-3 pr-2">
                            {/* Original Ticket Message */}
                            <div className="bg-slate-800 p-4 rounded-xl border border-slate-700 space-y-2">
                                <div className="flex justify-between text-xs text-slate-400">
                                    <span className="font-bold text-slate-200">{selectedTicket.creator?.name || 'Staff'}</span>
                                    <span>{new Date(selectedTicket.created_at).toLocaleString()}</span>
                                </div>
                                <p className="text-sm text-slate-300 leading-relaxed whitespace-pre-wrap">{selectedTicket.message}</p>
                            </div>

                            {/* Replies */}
                            {selectedTicket.replies?.map((r) => (
                                <div
                                    key={r.id}
                                    className={`p-4 rounded-xl border space-y-2 ${
                                        r.is_staff_reply ? 'bg-teal-500/10 border-teal-500/30 ml-4' : 'bg-slate-800/80 border-slate-700'
                                    }`}
                                >
                                    <div className="flex justify-between text-xs text-slate-400">
                                        <span className="font-bold text-teal-400">
                                            {r.user?.name || 'User'} {r.is_staff_reply ? '(DentalCare Support Team)' : ''}
                                        </span>
                                        <span>{new Date(r.created_at).toLocaleString()}</span>
                                    </div>
                                    <p className="text-sm text-slate-300 leading-relaxed whitespace-pre-wrap">{r.message}</p>
                                </div>
                            ))}
                        </div>

                        {/* Reply Input Form */}
                        {replyError && (
                            <div className="p-2 bg-rose-500/20 text-rose-300 text-xs rounded-lg">{replyError}</div>
                        )}

                        <form onSubmit={handleSendReply} className="pt-2 border-t border-slate-800 space-y-3">
                            <textarea
                                required
                                rows={2}
                                className="w-full bg-slate-800 border-slate-700 text-slate-100 rounded-xl p-3 text-xs"
                                placeholder="Write a reply..."
                                value={replyMessage}
                                onChange={(e) => setReplyMessage(e.target.value)}
                            />
                            <div className="flex justify-between items-center">
                                <Button type="button" onClick={() => setSelectedTicket(null)} className="bg-slate-800 text-slate-300">
                                    Close
                                </Button>
                                <Button type="submit" className="bg-teal-500 hover:bg-teal-400 text-slate-950 font-bold flex items-center gap-1.5">
                                    <Send className="w-3.5 h-3.5" /> Send Reply
                                </Button>
                            </div>
                        </form>
                    </div>
                </div>
            )}
        </AppLayout>
    );
}
