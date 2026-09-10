import React, { useState, useEffect } from 'react';
import AppLayout from '../Layouts/AppLayout';
import Card from '../Components/Card';
import Button from '../Components/Button';
import Badge from '../Components/Badge';
import TextInput from '../Components/TextInput';
import InputLabel from '../Components/InputLabel';
import {
    TrendingUp,
    DollarSign,
    Users,
    Activity,
    Calendar,
    Award,
    Building2,
    Filter,
    BarChart3,
    Clock,
    AlertCircle
} from 'lucide-react';

export default function AnalyticsDashboard({ auth }) {
    const [startDate, setStartDate] = useState('');
    const [endDate, setEndDate] = useState('');
    const [selectedBranch, setSelectedBranch] = useState('');

    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);
    const [metrics, setMetrics] = useState(null);

    const fetchAnalytics = async () => {
        setLoading(true);
        setError(null);
        try {
            let url = '/api/analytics?';
            if (startDate) url += `&start_date=${startDate}`;
            if (endDate) url += `&end_date=${endDate}`;
            if (selectedBranch) url += `&branch_id=${selectedBranch}`;

            const res = await fetch(url, { headers: { Accept: 'application/json' } });
            if (!res.ok) {
                throw new Error('Failed to load analytics metrics.');
            }
            const data = await res.json();
            setMetrics(data.metrics || null);
        } catch (err) {
            setError(err.message || 'Error fetching analytics data.');
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        fetchAnalytics();
    }, [startDate, endDate, selectedBranch]);

    const formatCurrency = (val) => {
        return new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(val || 0);
    };

    return (
        <AppLayout title="Clinic Analytics" auth={auth}>
            <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">
                {/* Header & Filter Controls */}
                <div className="flex flex-col md:flex-row md:items-center justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-bold text-slate-900 dark:text-slate-100 flex items-center gap-2">
                            <BarChart3 className="w-7 h-7 text-teal-500" /> Executive Analytics & Intelligence
                        </h1>
                        <p className="text-sm text-slate-500 dark:text-slate-400">
                            Real-time KPIs, production trends, operatory utilization, and clinical performance
                        </p>
                    </div>

                    {/* Filters */}
                    <Card className="bg-slate-900 border-slate-800 p-3 flex flex-wrap items-center gap-3">
                        <div className="flex items-center space-x-2">
                            <InputLabel value="From" className="text-slate-400 text-xs" />
                            <TextInput
                                type="date"
                                className="bg-slate-800 border-slate-700 text-slate-200 text-xs py-1 px-2"
                                value={startDate}
                                onChange={(e) => setStartDate(e.target.value)}
                            />
                        </div>
                        <div className="flex items-center space-x-2">
                            <InputLabel value="To" className="text-slate-400 text-xs" />
                            <TextInput
                                type="date"
                                className="bg-slate-800 border-slate-700 text-slate-200 text-xs py-1 px-2"
                                value={endDate}
                                onChange={(e) => setEndDate(e.target.value)}
                            />
                        </div>
                        {metrics?.branches?.length > 0 && (
                            <select
                                className="bg-slate-800 border-slate-700 text-slate-200 text-xs rounded-lg p-1.5"
                                value={selectedBranch}
                                onChange={(e) => setSelectedBranch(e.target.value)}
                            >
                                <option value="">All Branches</option>
                                {metrics.branches.map((b) => (
                                    <option key={b.id} value={b.id}>{b.name}</option>
                                ))}
                            </select>
                        )}
                    </Card>
                </div>

                {error && (
                    <div className="p-4 bg-rose-500/20 border border-rose-500 text-rose-300 rounded-xl text-sm flex items-center gap-2">
                        <AlertCircle className="w-5 h-5 shrink-0" /> {error}
                    </div>
                )}

                {loading ? (
                    <div className="p-16 text-center text-slate-400 space-y-3">
                        <div className="w-8 h-8 border-4 border-teal-500 border-t-transparent rounded-full animate-spin mx-auto" />
                        <p>Aggregating clinic performance data...</p>
                    </div>
                ) : !metrics ? (
                    <Card className="bg-slate-900 border-slate-800 p-12 text-center text-slate-400">
                        No analytics data available for selected period.
                    </Card>
                ) : (
                    <>
                        {/* KPI Cards */}
                        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                            <Card className="bg-slate-900 border-slate-800 p-5 space-y-2">
                                <div className="flex items-center justify-between text-slate-400">
                                    <span className="text-xs font-semibold uppercase tracking-wider">Gross Production</span>
                                    <TrendingUp className="w-5 h-5 text-teal-400" />
                                </div>
                                <div className="text-2xl font-black text-slate-100">
                                    {formatCurrency(metrics.summary?.total_production)}
                                </div>
                                <p className="text-xs text-slate-500">Total invoiced work</p>
                            </Card>

                            <Card className="bg-slate-900 border-slate-800 p-5 space-y-2">
                                <div className="flex items-center justify-between text-slate-400">
                                    <span className="text-xs font-semibold uppercase tracking-wider">Collections</span>
                                    <DollarSign className="w-5 h-5 text-emerald-400" />
                                </div>
                                <div className="text-2xl font-black text-slate-100">
                                    {formatCurrency(metrics.summary?.total_collections)}
                                </div>
                                <p className="text-xs text-slate-500">Actual received payments</p>
                            </Card>

                            <Card className="bg-slate-900 border-slate-800 p-5 space-y-2">
                                <div className="flex items-center justify-between text-slate-400">
                                    <span className="text-xs font-semibold uppercase tracking-wider">No-Show Rate</span>
                                    <Clock className="w-5 h-5 text-amber-400" />
                                </div>
                                <div className="text-2xl font-black text-amber-400">
                                    {metrics.no_show_stats?.no_show_rate}%
                                </div>
                                <p className="text-xs text-slate-500">
                                    {metrics.no_show_stats?.no_shows} no-shows / {metrics.no_show_stats?.total_appointments} total
                                </p>
                            </Card>

                            <Card className="bg-slate-900 border-slate-800 p-5 space-y-2">
                                <div className="flex items-center justify-between text-slate-400">
                                    <span className="text-xs font-semibold uppercase tracking-wider">Active Patients</span>
                                    <Users className="w-5 h-5 text-purple-400" />
                                </div>
                                <div className="text-2xl font-black text-slate-100">
                                    {metrics.summary?.total_patients}
                                </div>
                                <p className="text-xs text-slate-500">Registered patients</p>
                            </Card>
                        </div>

                        {/* Charts Section */}
                        <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                            {/* Production vs Collections Trend */}
                            <Card className="bg-slate-900 border-slate-800 p-6 space-y-4">
                                <h2 className="text-base font-bold text-slate-100 flex items-center gap-2">
                                    <TrendingUp className="w-5 h-5 text-teal-400" /> Production vs Collections Trend
                                </h2>
                                {metrics.production_vs_collections?.length === 0 ? (
                                    <p className="text-xs text-slate-500 py-8 text-center">No trend data for period.</p>
                                ) : (
                                    <div className="space-y-3 pt-2">
                                        {metrics.production_vs_collections.map((item, i) => {
                                            const max = Math.max(...metrics.production_vs_collections.map(d => Math.max(d.production, d.collections, 1)));
                                            const prodPct = (item.production / max) * 100;
                                            const collPct = (item.collections / max) * 100;

                                            return (
                                                <div key={i} className="space-y-1">
                                                    <div className="flex justify-between text-xs font-medium">
                                                        <span className="text-slate-300 font-semibold">{item.month}</span>
                                                        <span className="text-slate-400">
                                                            Prod: {formatCurrency(item.production)} | Coll: {formatCurrency(item.collections)}
                                                        </span>
                                                    </div>
                                                    <div className="space-y-1">
                                                        <div className="w-full bg-slate-800 h-2 rounded-full overflow-hidden">
                                                            <div className="bg-teal-400 h-full rounded-full transition-all" style={{ width: `${prodPct}%` }} title="Production" />
                                                        </div>
                                                        <div className="w-full bg-slate-800 h-2 rounded-full overflow-hidden">
                                                            <div className="bg-emerald-400 h-full rounded-full transition-all" style={{ width: `${collPct}%` }} title="Collections" />
                                                        </div>
                                                    </div>
                                                </div>
                                            );
                                        })}
                                    </div>
                                )}
                            </Card>

                            {/* Chair / Operatory Utilization */}
                            <Card className="bg-slate-900 border-slate-800 p-6 space-y-4">
                                <h2 className="text-base font-bold text-slate-100 flex items-center gap-2">
                                    <Building2 className="w-5 h-5 text-purple-400" /> Chair & Operatory Utilization
                                </h2>
                                {metrics.operatory_utilization?.length === 0 ? (
                                    <p className="text-xs text-slate-500 py-8 text-center">No operatories configured.</p>
                                ) : (
                                    <div className="space-y-4 pt-2">
                                        {metrics.operatory_utilization.map((op) => (
                                            <div key={op.operatory_id} className="space-y-1.5">
                                                <div className="flex justify-between text-xs">
                                                    <span className="text-slate-200 font-bold">{op.operatory_name}</span>
                                                    <span className="text-purple-400 font-bold">{op.utilization_rate}% ({op.booked_hours} hrs booked)</span>
                                                </div>
                                                <div className="w-full bg-slate-800 h-3 rounded-full overflow-hidden">
                                                    <div
                                                        className={`h-full rounded-full transition-all ${
                                                            op.utilization_rate > 80 ? 'bg-rose-500' : op.utilization_rate > 50 ? 'bg-amber-400' : 'bg-purple-500'
                                                        }`}
                                                        style={{ width: `${op.utilization_rate}%` }}
                                                    />
                                                </div>
                                            </div>
                                        ))}
                                    </div>
                                )}
                            </Card>
                        </div>

                        {/* Top Procedures & Doctor Leaderboard */}
                        <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                            {/* Top Procedures by Revenue */}
                            <Card className="bg-slate-900 border-slate-800 p-6 space-y-4">
                                <h2 className="text-base font-bold text-slate-100 flex items-center gap-2">
                                    <Activity className="w-5 h-5 text-amber-400" /> Top Procedures by Revenue
                                </h2>
                                {metrics.top_procedures_by_revenue?.length === 0 ? (
                                    <p className="text-xs text-slate-500 py-8 text-center">No completed procedures.</p>
                                ) : (
                                    <div className="space-y-3">
                                        {metrics.top_procedures_by_revenue.map((proc, i) => (
                                            <div key={i} className="flex items-center justify-between bg-slate-800/60 p-3 rounded-xl border border-slate-700/60">
                                                <span className="text-xs font-semibold text-slate-200">{proc.procedure_name}</span>
                                                <span className="text-xs font-bold text-emerald-400">{formatCurrency(proc.revenue)}</span>
                                            </div>
                                        ))}
                                    </div>
                                )}
                            </Card>

                            {/* Doctor Leaderboard */}
                            <Card className="bg-slate-900 border-slate-800 p-6 space-y-4">
                                <h2 className="text-base font-bold text-slate-100 flex items-center gap-2">
                                    <Award className="w-5 h-5 text-teal-400" /> Doctor Commission Leaderboard
                                </h2>
                                {metrics.doctor_leaderboard?.length === 0 ? (
                                    <p className="text-xs text-slate-500 py-8 text-center">No doctor commission records.</p>
                                ) : (
                                    <div className="space-y-3">
                                        {metrics.doctor_leaderboard.map((doc, i) => (
                                            <div key={i} className="flex items-center justify-between bg-slate-800/60 p-3 rounded-xl border border-slate-700/60">
                                                <div className="flex items-center space-x-3">
                                                    <span className="w-6 h-6 rounded-full bg-teal-500/20 text-teal-400 text-xs font-bold flex items-center justify-center">
                                                        #{i + 1}
                                                    </span>
                                                    <span className="text-xs font-bold text-slate-200">{doc.doctor_name}</span>
                                                </div>
                                                <div className="text-right">
                                                    <div className="text-xs font-bold text-teal-400">{formatCurrency(doc.total_commission)}</div>
                                                    <div className="text-[10px] text-slate-500">Gross: {formatCurrency(doc.total_production)}</div>
                                                </div>
                                            </div>
                                        ))}
                                    </div>
                                )}
                            </Card>
                        </div>
                    </>
                )}
            </div>
        </AppLayout>
    );
}
