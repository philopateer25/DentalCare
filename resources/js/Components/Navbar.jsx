import React from 'react';
import { Link } from '@inertiajs/react';
import { Stethoscope, Lock, ExternalLink, ArrowRight, Menu } from 'lucide-react';
import Button from './Button';
import Badge from './Badge';

export default function Navbar({ auth, appName = 'DentalCare', onMenuClick }) {
    return (
        <header className="relative z-20 border-b border-neutral-200/80 dark:border-slate-800/80 bg-white/80 dark:bg-slate-950/70 backdrop-blur-xl sticky top-0 transition-colors">
            <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 sm:h-20 flex items-center justify-between">
                {/* Mobile Menu Trigger & Brand Logo */}
                <div className="flex items-center gap-3">
                    {onMenuClick && (
                        <button
                            type="button"
                            onClick={onMenuClick}
                            className="lg:hidden p-2 rounded-xl text-neutral-600 dark:text-slate-300 hover:bg-neutral-100 dark:hover:bg-slate-800"
                            aria-label="Open sidebar menu"
                        >
                            <Menu className="w-5 h-5" />
                        </button>
                    )}

                    <Link href="/" className="flex items-center gap-3 group">
                        <div className="h-10 w-10 sm:h-11 sm:w-11 rounded-2xl bg-gradient-to-tr from-teal-400 to-cyan-600 flex items-center justify-center shadow-lg shadow-teal-500/25 ring-1 ring-black/5 dark:ring-white/20 group-hover:scale-105 transition-transform">
                            <Stethoscope className="h-5 w-5 sm:h-6 sm:w-6 text-slate-950 font-bold" />
                        </div>
                        <div>
                            <span className="text-lg sm:text-xl font-extrabold tracking-tight text-neutral-900 dark:text-transparent dark:bg-gradient-to-r dark:from-white dark:via-slate-100 dark:to-teal-200 dark:bg-clip-text">
                                {appName}
                            </span>
                            <Badge variant="teal" className="hidden sm:inline-block ml-2 text-[10px] uppercase tracking-wider">
                                Clinical Suite
                            </Badge>
                        </div>
                    </Link>
                </div>

                {/* Middle Nav Links */}
                <nav className="hidden md:flex items-center gap-8 text-sm font-medium text-neutral-600 dark:text-slate-400">
                    <Link href="/dashboard" className="hover:text-black dark:hover:text-teal-300 transition-colors">Dashboard</Link>
                    <Link href="/patients" className="hover:text-black dark:hover:text-teal-300 transition-colors">Patients</Link>
                    <Link href="/operations" className="hover:text-black dark:hover:text-teal-300 transition-colors">Operations</Link>
                    <Link href="/finance" className="hover:text-black dark:hover:text-teal-300 transition-colors">Finance</Link>
                </nav>

                {/* Right Action Buttons */}
                <div className="flex items-center gap-3">
                    <a 
                        href="/admin" 
                        className="inline-flex items-center gap-2 px-3 sm:px-4 py-2 text-xs sm:text-sm font-semibold rounded-xl bg-neutral-100 dark:bg-slate-900 hover:bg-neutral-200 dark:hover:bg-slate-800 text-neutral-900 dark:text-teal-300 border border-neutral-300 dark:border-teal-500/30 shadow-sm transition-all"
                    >
                        <Lock className="w-3.5 h-3.5 sm:w-4 sm:h-4 text-teal-600 dark:text-teal-400" />
                        <span className="hidden sm:inline">Filament Admin</span>
                        <ExternalLink className="w-3.5 h-3.5 text-neutral-400" />
                    </a>

                    <a href="/dashboard" className="hidden sm:inline-flex">
                        <Button variant="primary" size="sm" className="shadow-lg">
                            <span>Open App</span>
                            <ArrowRight className="w-4 h-4" />
                        </Button>
                    </a>
                </div>
            </div>
        </header>
    );
}
