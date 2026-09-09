import React, { useState, useRef, useEffect } from 'react';
import { useLocale } from '../Contexts/LocaleContext';
import { Globe, Check, ChevronDown } from 'lucide-react';

export default function LanguageSelector({ className = '' }) {
    const { locale, availableLocales, changeLanguage, t } = useLocale();
    const [isOpen, setIsOpen] = useState(false);
    const dropdownRef = useRef(null);

    const active = availableLocales[locale] || availableLocales['en'] || {
        flag: '🇺🇸',
        native: 'English',
        name: 'English',
    };

    useEffect(() => {
        function handleClickOutside(event) {
            if (dropdownRef.current && !dropdownRef.current.contains(event.target)) {
                setIsOpen(false);
            }
        }
        document.addEventListener('mousedown', handleClickOutside);
        return () => document.removeEventListener('mousedown', handleClickOutside);
    }, []);

    return (
        <div className={`relative inline-block text-start ${className}`} ref={dropdownRef}>
            <button
                type="button"
                onClick={() => setIsOpen(!isOpen)}
                className="inline-flex items-center gap-2 px-3 py-2 rounded-xl bg-neutral-100 dark:bg-slate-900/90 hover:bg-neutral-200 dark:hover:bg-slate-800 border border-neutral-300 dark:border-slate-800 text-xs sm:text-sm font-semibold text-neutral-800 dark:text-slate-200 transition-all shadow-sm hover:border-teal-500/40 focus:outline-none"
                aria-haspopup="true"
                aria-expanded={isOpen}
            >
                <span className="text-base leading-none">{active.flag}</span>
                <span className="font-semibold">{active.native || locale.toUpperCase()}</span>
                <ChevronDown className={`w-3.5 h-3.5 text-neutral-500 dark:text-slate-400 transition-transform duration-200 ${isOpen ? 'rotate-180' : ''}`} />
            </button>

            {isOpen && (
                <div className="absolute end-0 mt-2 w-48 rounded-2xl bg-white dark:bg-slate-950/95 backdrop-blur-xl border border-neutral-200 dark:border-slate-800 shadow-2xl py-1.5 z-50 animate-in fade-in zoom-in-95 duration-100">
                    <div className="px-3 py-1.5 text-[10px] font-bold uppercase tracking-wider text-neutral-400 dark:text-slate-500 border-b border-neutral-100 dark:border-slate-800/80">
                        {t('Select Language')}
                    </div>
                    {Object.entries(availableLocales).map(([code, info]) => {
                        const isSelected = locale === code;
                        return (
                            <button
                                key={code}
                                type="button"
                                onClick={() => {
                                    setIsOpen(false);
                                    changeLanguage(code);
                                }}
                                className={`w-full flex items-center justify-between px-3 py-2 text-xs sm:text-sm transition-all text-start ${
                                    isSelected
                                        ? 'bg-teal-500/10 text-teal-600 dark:text-teal-300 font-bold'
                                        : 'text-neutral-700 dark:text-slate-300 hover:bg-neutral-100 dark:hover:bg-slate-900 hover:text-black dark:hover:text-white'
                                }`}
                            >
                                <div className="flex items-center gap-2.5">
                                    <span className="text-base leading-none">{info.flag}</span>
                                    <span>{info.native || info.name}</span>
                                </div>
                                {isSelected && <Check className="w-4 h-4 text-teal-600 dark:text-teal-400" />}
                            </button>
                        );
                    })}
                </div>
            )}
        </div>
    );
}
