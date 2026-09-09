import React, { useState } from 'react';
import AppLayout from '../Layouts/AppLayout';
import Card from '../Components/Card';
import Badge from '../Components/Badge';
import Button from '../Components/Button';
import {
    CreditCard,
    DollarSign,
    TrendingUp,
    Clock,
    CheckCircle2,
    AlertCircle,
    ExternalLink,
    Receipt,
    Banknote,
    Calendar,
    Users,
    ArrowUpRight,
    Search,
    Filter,
    FileText,
    Percent,
    Building2,
    ShieldAlert
} from 'lucide-react';

export default function Finance({
    auth,
    tenantId = 1,
    stats = {},
    invoices = [],
    payments = [],
    installmentPlans = [],
    commissions = [],
    expenses = [],
    availableCurrencies = {
        'EGP': { name: 'Egyptian Pound', symbol: 'EGP' },
        'USD': { name: 'US Dollar', symbol: '$' },
        'SAR': { name: 'Saudi Riyal', symbol: 'SAR' },
        'AED': { name: 'UAE Dirham', symbol: 'AED' },
        'EUR': { name: 'Euro', symbol: '€' },
        'GBP': { name: 'British Pound', symbol: '£' },
        'KWD': { name: 'Kuwaiti Dinar', symbol: 'KWD' },
        'QAR': { name: 'Qatari Riyal', symbol: 'QAR' },
    }
}) {
    const [activeTab, setActiveTab] = useState('invoices');
    const [searchQuery, setSearchQuery] = useState('');
    const [statusFilter, setStatusFilter] = useState('all');
    const [selectedCurrency, setSelectedCurrency] = useState(stats.currency || 'EGP');

    const totalProduction = stats.totalProduction || 0;
    const totalCollections = stats.totalCollections || 0;
    const totalOutstandingAR = stats.totalOutstandingAR || 0;
    const totalExpenses = stats.totalExpenses || 0;
    const netProfit = totalCollections - totalExpenses;
    const collectionRate = totalProduction > 0 ? Math.round((totalCollections / totalProduction) * 100) : 0;

    // Format money helper
    const formatMoney = (amount) => {
        try {
            return new Intl.NumberFormat('en-US', {
                style: 'currency',
                currency: selectedCurrency,
                minimumFractionDigits: 2,
            }).format(amount || 0);
        } catch (e) {
            const sym = availableCurrencies[selectedCurrency]?.symbol || selectedCurrency;
            return `${sym} ${(Number(amount) || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
        }
    };

    // Filtered invoices
    const filteredInvoices = invoices.filter((inv) => {
        const matchesSearch =
            inv.invoice_number?.toLowerCase().includes(searchQuery.toLowerCase()) ||
            inv.patient?.first_name?.toLowerCase().includes(searchQuery.toLowerCase()) ||
            inv.patient?.last_name?.toLowerCase().includes(searchQuery.toLowerCase());
        const matchesStatus = statusFilter === 'all' || inv.status === statusFilter;
        return matchesSearch && matchesStatus;
    });

    const financeTabs = [
        { id: 'invoices', label: 'Invoices & Billing Hub', count: invoices.length, icon: FileText },
        { id: 'payments', label: 'Payments & Collections', count: payments.length, icon: Banknote },
        { id: 'installments', label: 'Patient Financing & Credit', count: installmentPlans.length, icon: Calendar },
        { id: 'commissions', label: 'Doctor Payroll & Splits', count: commissions.length, icon: Users },
        { id: 'expenses', label: 'Clinic Expenses & Overhead', count: expenses.length, icon: Building2 },
    ];

    const quickLinks = [
        {
            title: 'Invoices & Billing Hub',
            desc: 'View & issue formal invoices, adjust line items',
            href: `/admin/${tenantId}/invoices`,
            icon: FileText,
            color: 'from-blue-500/20 to-indigo-500/20 text-blue-400 border-blue-500/30'
        },
        {
            title: 'Payments & Cash Register',
            desc: 'Record cash, POS, InstaPay & bank transfers',
            href: `/admin/${tenantId}/payments`,
            icon: Banknote,
            color: 'from-teal-500/20 to-emerald-500/20 text-teal-400 border-teal-500/30'
        },
        {
            title: 'Patient Financing Contracts',
            desc: 'Manage installment plans & schedules',
            href: `/admin/${tenantId}/installment-plans`,
            icon: Calendar,
            color: 'from-purple-500/20 to-pink-500/20 text-purple-400 border-purple-500/30'
        },
        {
            title: 'Doctor Commissions Ledger',
            desc: 'Calculate lab deductions & commission splits',
            href: `/admin/${tenantId}/doctor-commissions`,
            icon: Users,
            color: 'from-amber-500/20 to-orange-500/20 text-amber-400 border-amber-500/30'
        },
        {
            title: 'Clinic Expenses & Overhead',
            desc: 'Track operational costs, lab fees & supplies',
            href: `/admin/${tenantId}/clinic-expenses`,
            icon: Building2,
            color: 'from-rose-500/20 to-red-500/20 text-rose-400 border-rose-500/30'
        },
    ];

    return (
        <AppLayout title="Finance & Treasury Hub" auth={auth}>
            <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-8">
                {/* Header Banner */}
                <div className="flex flex-col md:flex-row md:items-center md:justify-between gap-4 pb-6 border-b border-slate-800">
                    <div>
                        <div className="flex items-center gap-3">
                            <div className="p-3 rounded-2xl bg-teal-500/10 border border-teal-500/20 text-teal-400">
                                <CreditCard className="w-8 h-8" />
                            </div>
                            <div>
                                <h1 className="text-2xl sm:text-3xl font-extrabold tracking-tight text-white flex items-center gap-3">
                                    Finance & Treasury Hub
                                    <Badge variant="teal" className="text-xs">Live Ledger</Badge>
                                </h1>
                                <p className="text-sm text-slate-400 mt-1">
                                    Accounts receivable, billing, collections, patient installment plans & doctor payroll.
                                </p>
                            </div>
                        </div>
                    </div>

                    <div className="flex flex-wrap items-center gap-3">
                        <div className="flex items-center gap-2 px-3 py-1.5 rounded-xl bg-slate-800/80 border border-slate-700/80 shadow-inner">
                            <span className="text-xs font-semibold text-slate-400 uppercase tracking-wider">Currency:</span>
                            <select
                                value={selectedCurrency}
                                onChange={(e) => setSelectedCurrency(e.target.value)}
                                className="bg-transparent text-xs font-bold text-teal-300 focus:outline-none cursor-pointer border-none py-1 pr-6 pl-1"
                            >
                                {Object.entries(availableCurrencies).map(([code, info]) => (
                                    <option key={code} value={code} className="bg-slate-900 text-white">
                                        {code} - {info.name || code} ({info.symbol || code})
                                    </option>
                                ))}
                            </select>
                        </div>
                        <a
                            href={`/admin/${tenantId}/invoices`}
                            className="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-teal-500 hover:bg-teal-400 text-slate-950 font-semibold text-sm transition-all shadow-lg shadow-teal-500/20"
                        >
                            <span>Open Invoices in Admin</span>
                            <ExternalLink className="w-4 h-4" />
                        </a>
                    </div>
                </div>

                {/* Top Metrics Cards */}
                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    {/* Gross Invoiced */}
                    <Card className="p-6 relative overflow-hidden group hover:border-teal-500/40 transition-all">
                        <div className="flex items-center justify-between">
                            <span className="text-xs font-semibold uppercase tracking-wider text-slate-400">Gross Invoiced (Production)</span>
                            <div className="p-2 rounded-xl bg-blue-500/10 text-blue-400 border border-blue-500/20">
                                <FileText className="w-4 h-4" />
                            </div>
                        </div>
                        <div className="mt-3">
                            <span className="text-2xl sm:text-3xl font-black tracking-tight text-white">
                                {formatMoney(totalProduction)}
                            </span>
                        </div>
                        <div className="mt-2 flex items-center gap-2 text-xs text-slate-400">
                            <span className="text-blue-400 font-medium">All clinical billings</span>
                        </div>
                    </Card>

                    {/* Total Collections */}
                    <Card className="p-6 relative overflow-hidden group hover:border-teal-500/40 transition-all">
                        <div className="flex items-center justify-between">
                            <span className="text-xs font-semibold uppercase tracking-wider text-slate-400">Net Collections</span>
                            <div className="p-2 rounded-xl bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                                <Banknote className="w-4 h-4" />
                            </div>
                        </div>
                        <div className="mt-3">
                            <span className="text-2xl sm:text-3xl font-black tracking-tight text-emerald-400">
                                {formatMoney(totalCollections)}
                            </span>
                        </div>
                        <div className="mt-2 flex items-center gap-2 text-xs text-slate-400">
                            <Badge variant="emerald" className="text-[10px] py-0.5">
                                {collectionRate}% Efficiency
                            </Badge>
                            <span>Realized cash inflow</span>
                        </div>
                    </Card>

                    {/* Outstanding AR */}
                    <Card className="p-6 relative overflow-hidden group hover:border-teal-500/40 transition-all">
                        <div className="flex items-center justify-between">
                            <span className="text-xs font-semibold uppercase tracking-wider text-slate-400">Outstanding (A/R)</span>
                            <div className="p-2 rounded-xl bg-amber-500/10 text-amber-400 border border-amber-500/20">
                                <Clock className="w-4 h-4" />
                            </div>
                        </div>
                        <div className="mt-3">
                            <span className="text-2xl sm:text-3xl font-black tracking-tight text-amber-400">
                                {formatMoney(totalOutstandingAR)}
                            </span>
                        </div>
                        <div className="mt-2 flex items-center gap-2 text-xs text-slate-400">
                            <span>Pending patient & insurance balances</span>
                        </div>
                    </Card>

                    {/* Net Cash Profit */}
                    <Card className="p-6 relative overflow-hidden group hover:border-teal-500/40 transition-all">
                        <div className="flex items-center justify-between">
                            <span className="text-xs font-semibold uppercase tracking-wider text-slate-400">Net Cash Profit</span>
                            <div className="p-2 rounded-xl bg-teal-500/10 text-teal-400 border border-teal-500/20">
                                <TrendingUp className="w-4 h-4" />
                            </div>
                        </div>
                        <div className="mt-3">
                            <span className={`text-2xl sm:text-3xl font-black tracking-tight ${netProfit >= 0 ? 'text-teal-300' : 'text-rose-400'}`}>
                                {formatMoney(netProfit)}
                            </span>
                        </div>
                        <div className="mt-2 flex items-center gap-2 text-xs text-slate-400">
                            <span>Collections minus expenses</span>
                        </div>
                    </Card>
                </div>

                {/* Quick-Access Navigation Cards to all Finance Tabs */}
                <div>
                    <h2 className="text-sm font-semibold uppercase tracking-wider text-slate-400 mb-3">
                        Reachable Finance Modules (Filament Admin)
                    </h2>
                    <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
                        {quickLinks.map((item, i) => {
                            const Icon = item.icon;
                            return (
                                <a
                                    key={i}
                                    href={item.href}
                                    className="p-4 rounded-2xl bg-slate-900/60 border border-slate-800 hover:border-teal-500/50 hover:bg-slate-900 transition-all group flex flex-col justify-between"
                                >
                                    <div>
                                        <div className="flex items-center justify-between mb-2">
                                            <div className={`p-2 rounded-xl border bg-gradient-to-br ${item.color}`}>
                                                <Icon className="w-4 h-4" />
                                            </div>
                                            <ArrowUpRight className="w-4 h-4 text-slate-500 group-hover:text-teal-400 group-hover:translate-x-0.5 group-hover:-translate-y-0.5 transition-all" />
                                        </div>
                                        <span className="font-bold text-white text-sm block group-hover:text-teal-300 transition-colors">
                                            {item.title}
                                        </span>
                                        <p className="text-xs text-slate-400 mt-1 line-clamp-2">
                                            {item.desc}
                                        </p>
                                    </div>
                                    <div className="mt-3 pt-2 border-t border-slate-800/60 flex items-center justify-between text-[11px] text-teal-400 font-medium">
                                        <span>Open module</span>
                                        <ExternalLink className="w-3 h-3" />
                                    </div>
                                </a>
                            );
                        })}
                    </div>
                </div>

                {/* Tabs Section */}
                <Card className="p-6">
                    {/* Navigation Tab Header */}
                    <div className="flex flex-wrap items-center gap-2 border-b border-slate-800 pb-4">
                        {financeTabs.map((tab) => {
                            const Icon = tab.icon;
                            const isActive = activeTab === tab.id;
                            return (
                                <button
                                    key={tab.id}
                                    onClick={() => setActiveTab(tab.id)}
                                    className={`flex items-center gap-2 px-4 py-2.5 rounded-xl text-xs sm:text-sm font-semibold transition-all ${
                                        isActive
                                            ? 'bg-teal-500 text-slate-950 shadow-md shadow-teal-500/20'
                                            : 'text-slate-400 hover:text-white hover:bg-slate-800/60'
                                    }`}
                                >
                                    <Icon className="w-4 h-4" />
                                    <span>{tab.label}</span>
                                    <span
                                        className={`px-1.5 py-0.5 rounded-md text-[10px] ${
                                            isActive
                                                ? 'bg-slate-950/20 text-slate-950'
                                                : 'bg-slate-800 text-slate-400'
                                        }`}
                                    >
                                        {tab.count}
                                    </span>
                                </button>
                            );
                        })}
                    </div>

                    {/* Tab 1: Invoices & Billing Hub */}
                    {activeTab === 'invoices' && (
                        <div className="mt-6 space-y-4">
                            <div className="flex flex-col sm:flex-row items-center justify-between gap-3">
                                <div className="relative w-full sm:w-80">
                                    <Search className="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" />
                                    <input
                                        type="text"
                                        placeholder="Search by invoice # or patient..."
                                        value={searchQuery}
                                        onChange={(e) => setSearchQuery(e.target.value)}
                                        className="w-full pl-9 pr-4 py-2 rounded-xl bg-slate-950 border border-slate-800 text-sm text-white placeholder:text-slate-500 focus:outline-none focus:border-teal-500"
                                    />
                                </div>

                                <div className="flex items-center gap-2 w-full sm:w-auto">
                                    <Filter className="w-4 h-4 text-slate-400" />
                                    <select
                                        value={statusFilter}
                                        onChange={(e) => setStatusFilter(e.target.value)}
                                        className="px-3 py-2 rounded-xl bg-slate-950 border border-slate-800 text-xs text-white focus:outline-none focus:border-teal-500"
                                    >
                                        <option value="all">All Statuses</option>
                                        <option value="paid">Paid</option>
                                        <option value="partially_paid">Partially Paid</option>
                                        <option value="unpaid">Unpaid</option>
                                        <option value="overdue">Overdue</option>
                                    </select>

                                    <a
                                        href={`/admin/${tenantId}/invoices/create`}
                                        className="px-3 py-2 rounded-xl bg-teal-500/10 border border-teal-500/30 text-teal-300 hover:bg-teal-500/20 text-xs font-semibold whitespace-nowrap transition-colors"
                                    >
                                        + New Invoice
                                    </a>
                                </div>
                            </div>

                            <div className="overflow-x-auto rounded-2xl border border-slate-800">
                                <table className="w-full text-left text-sm text-slate-300">
                                    <thead className="bg-slate-950/80 text-xs uppercase tracking-wider text-slate-400 border-b border-slate-800">
                                        <tr>
                                            <th className="px-4 py-3">Invoice #</th>
                                            <th className="px-4 py-3">Patient</th>
                                            <th className="px-4 py-3">Date</th>
                                            <th className="px-4 py-3">Total</th>
                                            <th className="px-4 py-3">Paid</th>
                                            <th className="px-4 py-3">Balance Due</th>
                                            <th className="px-4 py-3">Status</th>
                                            <th className="px-4 py-3 text-right">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-slate-800/60 bg-slate-900/30">
                                        {filteredInvoices.length > 0 ? (
                                            filteredInvoices.map((inv) => (
                                                <tr key={inv.id} className="hover:bg-slate-800/40 transition-colors">
                                                    <td className="px-4 py-3 font-semibold text-white">
                                                        <a
                                                            href={`/admin/${tenantId}/invoices/${inv.id}/edit`}
                                                            className="text-teal-400 hover:underline flex items-center gap-1"
                                                        >
                                                            <span>{inv.invoice_number}</span>
                                                            <ExternalLink className="w-3 h-3 text-slate-500" />
                                                        </a>
                                                    </td>
                                                    <td className="px-4 py-3">
                                                        {inv.patient ? (
                                                            <a
                                                                href={`/admin/${tenantId}/patients/${inv.patient.id}`}
                                                                className="hover:text-teal-300 transition-colors"
                                                            >
                                                                {inv.patient.first_name} {inv.patient.last_name}
                                                            </a>
                                                        ) : (
                                                            <span className="text-slate-500">N/A</span>
                                                        )}
                                                    </td>
                                                    <td className="px-4 py-3 text-slate-400 text-xs">
                                                        {inv.issue_date || 'N/A'}
                                                    </td>
                                                    <td className="px-4 py-3 font-medium text-white">
                                                        {formatMoney(inv.total_amount)}
                                                    </td>
                                                    <td className="px-4 py-3 text-emerald-400 font-medium">
                                                        {formatMoney(inv.paid_amount)}
                                                    </td>
                                                    <td className="px-4 py-3 font-bold text-amber-400">
                                                        {formatMoney(inv.balance_due)}
                                                    </td>
                                                    <td className="px-4 py-3">
                                                        <Badge
                                                            variant={
                                                                inv.status === 'paid'
                                                                    ? 'emerald'
                                                                    : inv.status === 'partially_paid'
                                                                    ? 'amber'
                                                                    : 'slate'
                                                            }
                                                            className="capitalize text-[11px]"
                                                        >
                                                            {inv.status?.replace('_', ' ')}
                                                        </Badge>
                                                    </td>
                                                    <td className="px-4 py-3 text-right">
                                                        <a
                                                            href={`/admin/${tenantId}/invoices/${inv.id}/edit`}
                                                            className="text-xs px-2.5 py-1 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-200 border border-slate-700 transition-colors inline-block"
                                                        >
                                                            Edit
                                                        </a>
                                                    </td>
                                                </tr>
                                            ))
                                        ) : (
                                            <tr>
                                                <td colSpan={8} className="px-4 py-8 text-center text-slate-500">
                                                    No invoices match your query.
                                                </td>
                                            </tr>
                                        )}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    )}

                    {/* Tab 2: Payments & Collections */}
                    {activeTab === 'payments' && (
                        <div className="mt-6 space-y-4">
                            <div className="flex items-center justify-between">
                                <h3 className="text-base font-bold text-white">Recent Collections</h3>
                                <a
                                    href={`/admin/${tenantId}/payments/create`}
                                    className="px-3 py-1.5 rounded-xl bg-teal-500/10 border border-teal-500/30 text-teal-300 hover:bg-teal-500/20 text-xs font-semibold"
                                >
                                    + Record Payment
                                </a>
                            </div>

                            <div className="overflow-x-auto rounded-2xl border border-slate-800">
                                <table className="w-full text-left text-sm text-slate-300">
                                    <thead className="bg-slate-950/80 text-xs uppercase tracking-wider text-slate-400 border-b border-slate-800">
                                        <tr>
                                            <th className="px-4 py-3">Receipt / Ref</th>
                                            <th className="px-4 py-3">Patient</th>
                                            <th className="px-4 py-3">Linked Invoice</th>
                                            <th className="px-4 py-3">Method</th>
                                            <th className="px-4 py-3">Amount</th>
                                            <th className="px-4 py-3">Date</th>
                                            <th className="px-4 py-3 text-right">Admin</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-slate-800/60 bg-slate-900/30">
                                        {payments.length > 0 ? (
                                            payments.map((p) => (
                                                <tr key={p.id} className="hover:bg-slate-800/40 transition-colors">
                                                    <td className="px-4 py-3 font-semibold text-white">
                                                        {p.transaction_reference || `REC-${p.id}`}
                                                    </td>
                                                    <td className="px-4 py-3">
                                                        {p.patient ? `${p.patient.first_name} ${p.patient.last_name}` : 'N/A'}
                                                    </td>
                                                    <td className="px-4 py-3 text-teal-400 font-medium">
                                                        {p.invoice ? (
                                                            <a
                                                                href={`/admin/${tenantId}/invoices/${p.invoice.id}/edit`}
                                                                className="hover:underline flex items-center gap-1"
                                                            >
                                                                {p.invoice.invoice_number}
                                                                <ExternalLink className="w-3 h-3 text-slate-500" />
                                                            </a>
                                                        ) : (
                                                            'Direct'
                                                        )}
                                                    </td>
                                                    <td className="px-4 py-3">
                                                        <Badge variant="teal" className="capitalize text-[11px]">
                                                            {p.payment_method?.replace('_', ' ')}
                                                        </Badge>
                                                    </td>
                                                    <td className="px-4 py-3 font-bold text-emerald-400">
                                                        {formatMoney(p.amount)}
                                                    </td>
                                                    <td className="px-4 py-3 text-xs text-slate-400">
                                                        {p.paid_at ? new Date(p.paid_at).toLocaleDateString() : 'N/A'}
                                                    </td>
                                                    <td className="px-4 py-3 text-right">
                                                        <a
                                                            href={`/admin/${tenantId}/payments/${p.id}/edit`}
                                                            className="text-xs px-2.5 py-1 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-200 border border-slate-700"
                                                        >
                                                            View
                                                        </a>
                                                    </td>
                                                </tr>
                                            ))
                                        ) : (
                                            <tr>
                                                <td colSpan={7} className="px-4 py-8 text-center text-slate-500">
                                                    No payments recorded yet.
                                                </td>
                                            </tr>
                                        )}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    )}

                    {/* Tab 3: Patient Financing Contracts */}
                    {activeTab === 'installments' && (
                        <div className="mt-6 space-y-4">
                            <div className="flex items-center justify-between">
                                <h3 className="text-base font-bold text-white">Active Financing Contracts</h3>
                                <a
                                    href={`/admin/${tenantId}/installment-plans/create`}
                                    className="px-3 py-1.5 rounded-xl bg-teal-500/10 border border-teal-500/30 text-teal-300 hover:bg-teal-500/20 text-xs font-semibold"
                                >
                                    + New Plan
                                </a>
                            </div>

                            <div className="overflow-x-auto rounded-2xl border border-slate-800">
                                <table className="w-full text-left text-sm text-slate-300">
                                    <thead className="bg-slate-950/80 text-xs uppercase tracking-wider text-slate-400 border-b border-slate-800">
                                        <tr>
                                            <th className="px-4 py-3">Contract #</th>
                                            <th className="px-4 py-3">Patient</th>
                                            <th className="px-4 py-3">Financed Principal</th>
                                            <th className="px-4 py-3">Down Payment</th>
                                            <th className="px-4 py-3">Installments</th>
                                            <th className="px-4 py-3">Status</th>
                                            <th className="px-4 py-3 text-right">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-slate-800/60 bg-slate-900/30">
                                        {installmentPlans.length > 0 ? (
                                            installmentPlans.map((plan) => (
                                                <tr key={plan.id} className="hover:bg-slate-800/40 transition-colors">
                                                    <td className="px-4 py-3 font-semibold text-white">
                                                        Plan #{plan.id}
                                                    </td>
                                                    <td className="px-4 py-3">
                                                        {plan.invoice?.patient
                                                            ? `${plan.invoice.patient.first_name} ${plan.invoice.patient.last_name}`
                                                            : 'N/A'}
                                                    </td>
                                                    <td className="px-4 py-3 font-bold text-white">
                                                        {formatMoney(plan.total_funded_amount)}
                                                    </td>
                                                    <td className="px-4 py-3 text-emerald-400 font-medium">
                                                        {formatMoney(plan.down_payment)}
                                                    </td>
                                                    <td className="px-4 py-3 text-xs text-slate-300">
                                                        {plan.number_of_installments}x ({plan.frequency})
                                                    </td>
                                                    <td className="px-4 py-3">
                                                        <Badge variant={plan.status === 'completed' ? 'emerald' : 'teal'}>
                                                            {plan.status}
                                                        </Badge>
                                                    </td>
                                                    <td className="px-4 py-3 text-right">
                                                        <a
                                                            href={`/admin/${tenantId}/installment-plans/${plan.id}/edit`}
                                                            className="text-xs px-2.5 py-1 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-200 border border-slate-700"
                                                        >
                                                            Manage
                                                        </a>
                                                    </td>
                                                </tr>
                                            ))
                                        ) : (
                                            <tr>
                                                <td colSpan={7} className="px-4 py-8 text-center text-slate-500">
                                                    No installment plans found.
                                                </td>
                                            </tr>
                                        )}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    )}

                    {/* Tab 4: Doctor Payroll & Commissions */}
                    {activeTab === 'commissions' && (
                        <div className="mt-6 space-y-4">
                            <div className="flex items-center justify-between">
                                <h3 className="text-base font-bold text-white">Doctor Commissions & Split Ledgers</h3>
                                <a
                                    href={`/admin/${tenantId}/doctor-commissions/create`}
                                    className="px-3 py-1.5 rounded-xl bg-teal-500/10 border border-teal-500/30 text-teal-300 hover:bg-teal-500/20 text-xs font-semibold"
                                >
                                    + Add Split
                                </a>
                            </div>

                            <div className="overflow-x-auto rounded-2xl border border-slate-800">
                                <table className="w-full text-left text-sm text-slate-300">
                                    <thead className="bg-slate-950/80 text-xs uppercase tracking-wider text-slate-400 border-b border-slate-800">
                                        <tr>
                                            <th className="px-4 py-3">Doctor</th>
                                            <th className="px-4 py-3">Gross Rev</th>
                                            <th className="px-4 py-3">Lab Deduction</th>
                                            <th className="px-4 py-3">Split %</th>
                                            <th className="px-4 py-3">Net Commission</th>
                                            <th className="px-4 py-3">Status</th>
                                            <th className="px-4 py-3 text-right">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-slate-800/60 bg-slate-900/30">
                                        {commissions.length > 0 ? (
                                            commissions.map((comm) => (
                                                <tr key={comm.id} className="hover:bg-slate-800/40 transition-colors">
                                                    <td className="px-4 py-3 font-semibold text-white">
                                                        {comm.doctor?.name || `Doctor #${comm.doctor_id}`}
                                                    </td>
                                                    <td className="px-4 py-3 font-medium text-white">
                                                        {formatMoney(comm.gross_amount)}
                                                    </td>
                                                    <td className="px-4 py-3 text-rose-400 font-medium">
                                                        {formatMoney(comm.lab_deduction_amount)}
                                                    </td>
                                                    <td className="px-4 py-3 text-xs text-slate-300">
                                                        {comm.commission_percentage}%
                                                    </td>
                                                    <td className="px-4 py-3 font-bold text-emerald-400">
                                                        {formatMoney(comm.commission_amount)}
                                                    </td>
                                                    <td className="px-4 py-3">
                                                        <Badge variant={comm.status === 'settled' ? 'emerald' : 'amber'}>
                                                            {comm.status}
                                                        </Badge>
                                                    </td>
                                                    <td className="px-4 py-3 text-right">
                                                        <a
                                                            href={`/admin/${tenantId}/doctor-commissions/${comm.id}/edit`}
                                                            className="text-xs px-2.5 py-1 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-200 border border-slate-700"
                                                        >
                                                            Review
                                                        </a>
                                                    </td>
                                                </tr>
                                            ))
                                        ) : (
                                            <tr>
                                                <td colSpan={7} className="px-4 py-8 text-center text-slate-500">
                                                    No doctor commissions registered yet.
                                                </td>
                                            </tr>
                                        )}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    )}

                    {/* Tab 5: Clinic Expenses & Overhead */}
                    {activeTab === 'expenses' && (
                        <div className="mt-6 space-y-4">
                            <div className="flex items-center justify-between">
                                <h3 className="text-base font-bold text-white">Clinic Overhead & Operating Expenses</h3>
                                <a
                                    href={`/admin/${tenantId}/clinic-expenses/create`}
                                    className="px-3 py-1.5 rounded-xl bg-teal-500/10 border border-teal-500/30 text-teal-300 hover:bg-teal-500/20 text-xs font-semibold"
                                >
                                    + Add Expense
                                </a>
                            </div>

                            <div className="overflow-x-auto rounded-2xl border border-slate-800">
                                <table className="w-full text-left text-sm text-slate-300">
                                    <thead className="bg-slate-950/80 text-xs uppercase tracking-wider text-slate-400 border-b border-slate-800">
                                        <tr>
                                            <th className="px-4 py-3">Expense Voucher #</th>
                                            <th className="px-4 py-3">Category</th>
                                            <th className="px-4 py-3">Payee</th>
                                            <th className="px-4 py-3">Amount</th>
                                            <th className="px-4 py-3">Date</th>
                                            <th className="px-4 py-3">Payment Method</th>
                                            <th className="px-4 py-3 text-right">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-slate-800/60 bg-slate-900/30">
                                        {expenses.length > 0 ? (
                                            expenses.map((exp) => (
                                                <tr key={exp.id} className="hover:bg-slate-800/40 transition-colors">
                                                    <td className="px-4 py-3 font-semibold text-white">
                                                        {exp.expense_number}
                                                    </td>
                                                    <td className="px-4 py-3">
                                                        <Badge variant="slate" className="text-[11px]">
                                                            {exp.category}
                                                        </Badge>
                                                    </td>
                                                    <td className="px-4 py-3 font-medium text-slate-200">
                                                        {exp.payee}
                                                    </td>
                                                    <td className="px-4 py-3 font-bold text-rose-400">
                                                        {formatMoney(exp.amount)}
                                                    </td>
                                                    <td className="px-4 py-3 text-xs text-slate-400">
                                                        {exp.expense_date}
                                                    </td>
                                                    <td className="px-4 py-3 text-xs text-slate-400 capitalize">
                                                        {exp.payment_method?.replace('_', ' ')}
                                                    </td>
                                                    <td className="px-4 py-3 text-right">
                                                        <a
                                                            href={`/admin/${tenantId}/clinic-expenses/${exp.id}/edit`}
                                                            className="text-xs px-2.5 py-1 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-200 border border-slate-700"
                                                        >
                                                            Edit
                                                        </a>
                                                    </td>
                                                </tr>
                                            ))
                                        ) : (
                                            <tr>
                                                <td colSpan={7} className="px-4 py-8 text-center text-slate-500">
                                                    No clinic expenses recorded.
                                                </td>
                                            </tr>
                                        )}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    )}
                </Card>
            </div>
        </AppLayout>
    );
}
