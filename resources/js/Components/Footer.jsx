import React from 'react';
import { Stethoscope, Lock, Phone, MapPin, Mail } from 'lucide-react';

export default function Footer({ appName = 'DentalCare' }) {
    return (
        <footer className="relative z-10 border-t border-neutral-200 dark:border-slate-800/80 bg-neutral-100/70 dark:bg-slate-950 py-12 transition-colors">
            <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div className="grid grid-cols-1 md:grid-cols-4 gap-8 pb-10 border-b border-neutral-200 dark:border-slate-800/80">
                    {/* Brand */}
                    <div className="space-y-4 md:col-span-1">
                        <div className="flex items-center gap-3">
                            <div className="h-9 w-9 rounded-xl bg-teal-500 text-slate-950 flex items-center justify-center font-bold shadow-sm">
                                <Stethoscope className="h-5 w-5" />
                            </div>
                            <span className="text-lg font-bold text-neutral-900 dark:text-white">{appName}</span>
                        </div>
                        <p className="text-xs text-neutral-600 dark:text-slate-400 leading-relaxed">
                            Advanced dentistry clinical suite integrated with Filament PHP, Inertia.js, and React.
                        </p>
                    </div>

                    {/* Clinic Hours */}
                    <div className="space-y-3">
                        <h4 className="text-xs font-bold uppercase tracking-wider text-teal-600 dark:text-teal-400">Opening Hours</h4>
                        <div className="space-y-1 text-xs text-neutral-600 dark:text-slate-400">
                            <p className="text-neutral-900 dark:text-slate-300 font-medium">Mon - Fri: 8:00 AM - 8:00 PM</p>
                            <p className="text-neutral-900 dark:text-slate-300 font-medium">Saturday: 9:00 AM - 5:00 PM</p>
                            <p className="text-neutral-500">Sunday: Emergency Only</p>
                        </div>
                    </div>

                    {/* Contact & Support */}
                    <div className="space-y-3">
                        <h4 className="text-xs font-bold uppercase tracking-wider text-teal-600 dark:text-teal-400">Emergency & Desk</h4>
                        <div className="space-y-2 text-xs text-neutral-600 dark:text-slate-400">
                            <div className="flex items-center gap-2">
                                <Phone className="w-3.5 h-3.5 text-teal-600 dark:text-teal-400" />
                                <span>+1 (800) 555-DENTAL</span>
                            </div>
                            <div className="flex items-center gap-2">
                                <Mail className="w-3.5 h-3.5 text-teal-600 dark:text-teal-400" />
                                <span>desk@dentalcare.clinic</span>
                            </div>
                            <div className="flex items-center gap-2">
                                <MapPin className="w-3.5 h-3.5 text-teal-600 dark:text-teal-400" />
                                <span>Downtown Medical Pavilion, Suite 400</span>
                            </div>
                        </div>
                    </div>

                    {/* Admin Access */}
                    <div className="space-y-3">
                        <h4 className="text-xs font-bold uppercase tracking-wider text-teal-600 dark:text-teal-400">Staff Portal</h4>
                        <p className="text-xs text-neutral-600 dark:text-slate-400">
                            Dentists, hygienists, and reception staff can log into the Filament back-office.
                        </p>
                        <a 
                            href="/admin" 
                            className="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg bg-white dark:bg-slate-900 text-neutral-900 dark:text-teal-300 border border-neutral-300 dark:border-teal-500/30 text-xs font-semibold hover:bg-neutral-100 dark:hover:bg-slate-800 shadow-sm transition-all"
                        >
                            <Lock className="w-3.5 h-3.5 text-teal-600 dark:text-teal-400" />
                            Filament Panel
                        </a>
                    </div>
                </div>

                {/* Bottom credits */}
                <div className="pt-6 flex flex-col sm:flex-row items-center justify-between gap-4 text-xs text-neutral-500 dark:text-slate-500">
                    <p>© {new Date().getFullYear()} {appName}. All rights reserved.</p>
                    <div className="flex items-center gap-4">
                        <span>Built with Inertia.js (React)</span>
                        <span>•</span>
                        <span>Filament 3 PHP</span>
                        <span>•</span>
                        <span>Laravel 13</span>
                    </div>
                </div>
            </div>
        </footer>
    );
}
