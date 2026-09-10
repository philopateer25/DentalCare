import React, { useState, useEffect, useRef } from 'react';
import { Bell, Check, CheckCheck, Clock, ShieldAlert, Calendar, FileText, Package, Sparkles } from 'lucide-react';

export default function NotificationCenter() {
    const [isOpen, setIsOpen] = useState(false);
    const [notifications, setNotifications] = useState([]);
    const [unreadCount, setUnreadCount] = useState(0);
    const [loading, setLoading] = useState(false);
    const dropdownRef = useRef(null);

    const fetchNotifications = async () => {
        try {
            const res = await fetch('/api/notifications', {
                headers: { 'Accept': 'application/json' },
            });
            if (res.ok) {
                const data = await res.json();
                setNotifications(data.notifications || []);
                setUnreadCount(data.unread_count || 0);
            }
        } catch (err) {
            console.error('Failed to fetch notifications:', err);
        }
    };

    useEffect(() => {
        fetchNotifications();
        const interval = setInterval(fetchNotifications, 30000); // 30s poll
        return () => clearInterval(interval);
    }, []);

    // Close dropdown on outside click
    useEffect(() => {
        const handleClickOutside = (e) => {
            if (dropdownRef.current && !dropdownRef.current.contains(e.target)) {
                setIsOpen(false);
            }
        };
        document.addEventListener('mousedown', handleClickOutside);
        return () => document.removeEventListener('mousedown', handleClickOutside);
    }, []);

    const markAsRead = async (id) => {
        try {
            const res = await fetch(`/api/notifications/${id}/read`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                },
            });
            if (res.ok) {
                setNotifications((prev) =>
                    prev.map((n) => (n.id === id ? { ...n, read_at: new Date().toISOString() } : n))
                );
                setUnreadCount((prev) => Math.max(0, prev - 1));
            }
        } catch (err) {
            console.error('Failed to mark notification as read:', err);
        }
    };

    const markAllAsRead = async () => {
        try {
            const res = await fetch('/api/notifications/read-all', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                },
            });
            if (res.ok) {
                setNotifications((prev) =>
                    prev.map((n) => ({ ...n, read_at: new Date().toISOString() }))
                );
                setUnreadCount(0);
            }
        } catch (err) {
            console.error('Failed to mark all as read:', err);
        }
    };

    const getIcon = (type) => {
        switch (type) {
            case 'appointment_reminder':
                return <Calendar className="w-4 h-4 text-blue-400" />;
            case 'invoice_overdue':
                return <FileText className="w-4 h-4 text-rose-400" />;
            case 'low_inventory':
                return <Package className="w-4 h-4 text-amber-400" />;
            case 'procedure_completed':
                return <Sparkles className="w-4 h-4 text-emerald-400" />;
            default:
                return <ShieldAlert className="w-4 h-4 text-cyan-400" />;
        }
    };

    return (
        <div className="relative" ref={dropdownRef}>
            {/* Bell Trigger */}
            <button
                type="button"
                onClick={() => setIsOpen(!isOpen)}
                className="relative p-2 rounded-xl text-neutral-600 dark:text-slate-300 hover:bg-neutral-100 dark:hover:bg-slate-800 transition-colors focus:outline-none"
                aria-label="Notifications"
            >
                <Bell className="w-5 h-5" />
                {unreadCount > 0 && (
                    <span className="absolute top-1 right-1 flex h-4 w-4 items-center justify-center rounded-full bg-rose-500 text-[10px] font-bold text-white shadow-sm">
                        {unreadCount > 9 ? '9+' : unreadCount}
                    </span>
                )}
            </button>

            {/* Notification Popover Dropdown */}
            {isOpen && (
                <div className="absolute right-0 mt-2 w-80 sm:w-96 rounded-2xl bg-white dark:bg-slate-900 border border-neutral-200 dark:border-slate-800 shadow-2xl z-50 overflow-hidden">
                    <div className="p-4 border-b border-neutral-100 dark:border-slate-800 flex items-center justify-between">
                        <div className="flex items-center space-x-2">
                            <h3 className="font-bold text-neutral-900 dark:text-slate-100">Notifications</h3>
                            {unreadCount > 0 && (
                                <span className="bg-teal-500/20 text-teal-600 dark:text-teal-400 text-xs px-2 py-0.5 rounded-full font-semibold">
                                    {unreadCount} unread
                                </span>
                            )}
                        </div>
                        {unreadCount > 0 && (
                            <button
                                type="button"
                                onClick={markAllAsRead}
                                className="text-xs text-teal-600 dark:text-teal-400 hover:underline flex items-center gap-1 font-medium"
                            >
                                <CheckCheck className="w-3.5 h-3.5" /> Mark all read
                            </button>
                        )}
                    </div>

                    <div className="max-h-80 overflow-y-auto divide-y divide-neutral-100 dark:divide-slate-800">
                        {notifications.length === 0 ? (
                            <div className="p-8 text-center text-sm text-neutral-500 dark:text-slate-400">
                                No notifications yet.
                            </div>
                        ) : (
                            notifications.map((n) => {
                                const isUnread = !n.read_at;
                                return (
                                    <div
                                        key={n.id}
                                        className={`p-3.5 flex items-start gap-3 transition-colors ${
                                            isUnread ? 'bg-teal-500/5 dark:bg-teal-500/10' : 'hover:bg-neutral-50 dark:hover:bg-slate-850'
                                        }`}
                                    >
                                        <div className="p-2 rounded-xl bg-neutral-100 dark:bg-slate-800 shrink-0 mt-0.5">
                                            {getIcon(n.type)}
                                        </div>
                                        <div className="flex-1 min-w-0">
                                            <div className="flex items-center justify-between">
                                                <p className="text-xs font-bold text-neutral-900 dark:text-slate-200 truncate">
                                                    {n.title}
                                                </p>
                                                <span className="text-[10px] text-neutral-400 dark:text-slate-500 flex items-center gap-1">
                                                    <Clock className="w-3 h-3 inline" /> {n.created_at}
                                                </span>
                                            </div>
                                            <p className="text-xs text-neutral-600 dark:text-slate-400 mt-1 line-clamp-2 leading-relaxed">
                                                {n.message}
                                            </p>
                                        </div>
                                        {isUnread && (
                                            <button
                                                type="button"
                                                onClick={() => markAsRead(n.id)}
                                                className="text-neutral-400 hover:text-teal-500 p-1 transition-colors"
                                                title="Mark as read"
                                            >
                                                <Check className="w-4 h-4" />
                                            </button>
                                        )}
                                    </div>
                                );
                            })
                        )}
                    </div>
                </div>
            )}
        </div>
    );
}
