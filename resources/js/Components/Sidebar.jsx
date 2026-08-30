import React, { useState, useEffect } from 'react';
import { Link, usePage } from '@inertiajs/react';
import {
    LayoutDashboard,
    Users,
    Activity,
    CreditCard,
    ShieldCheck,
    Package,
    FlaskConical,
    Home,
    Lock,
    PanelLeftClose,
    PanelLeftOpen,
    Sun,
    Moon,
    Stethoscope,
    ExternalLink
} from 'lucide-react';
import { useTheme } from '../Contexts/ThemeContext';

export default function Sidebar({ appName = 'DentalCare', isMobileOpen = false, onMobileClose = () => {} }) {
    const { url } = usePage();
    const { theme, toggleTheme } = useTheme();

    // Persist collapsed state in localStorage
    const [isCollapsed, setIsCollapsed] = useState(() => {
        if (typeof window !== 'undefined') {
            const saved = localStorage.getItem('sidebar_collapsed');
            return saved === 'true';
        }
        return false;
    });

    const [isHovered, setIsHovered] = useState(false);

    useEffect(() => {
        localStorage.setItem('sidebar_collapsed', isCollapsed.toString());
    }, [isCollapsed]);

    // Effective state: expanded if not collapsed, OR if collapsed but currently hovered
    const isExpanded = !isCollapsed || isHovered;

    const navigationItems = [
        {
            name: 'Dashboard',
            href: '/dashboard',
            icon: LayoutDashboard,
        },
        {
            name: 'Patients',
            href: '/patients',
            icon: Users,
        },
        {
            name: 'Operations',
            href: '/operations',
            icon: Activity,
        },
        {
            name: 'Finance',
            href: '/finance',
            icon: CreditCard,
        },
        {
            name: 'Insurance',
            href: '/insurance',
            icon: ShieldCheck,
        },
        {
            name: 'Inventory',
            href: '/inventory',
            icon: Package,
        },
        {
            name: 'Labs',
            href: '/labs',
            icon: FlaskConical,
        },
        {
            name: 'Home Portal',
            href: '/',
            icon: Home,
            exact: true,
        },
    ];

    const isCurrentActive = (item) => {
        if (item.exact) {
            return url === item.href;
        }
        return url.startsWith(item.href);
    };

    return (
        <>
            {/* Mobile Backdrop Overlay */}
            {isMobileOpen && (
                <div
                    className="fixed inset-0 z-40 bg-black/50 backdrop-blur-sm lg:hidden transition-opacity"
                    onClick={onMobileClose}
                />
            )}

            {/* Sidebar Container */}
            <aside
                onMouseEnter={() => {
                    if (isCollapsed) setIsHovered(true);
                }}
                onMouseLeave={() => {
                    if (isCollapsed) setIsHovered(false);
                }}
                className={`
                    fixed lg:sticky top-0 left-0 z-50 h-screen
                    bg-white dark:bg-zinc-950
                    border-r border-neutral-200 dark:border-zinc-800
                    flex flex-col justify-between
                    transition-all duration-300 ease-in-out select-none
                    shadow-xl lg:shadow-none
                    ${isMobileOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'}
                    ${isExpanded ? 'w-64' : 'w-20'}
                `}
            >
                {/* Top Section: Brand & Collapse Toggle */}
                <div>
                    <div className="h-16 px-4 flex items-center justify-between border-b border-neutral-100 dark:border-zinc-800/80">
                        {/* Logo / App Name */}
                        <Link
                            href="/"
                            className="flex items-center gap-3 overflow-hidden text-neutral-900 dark:text-white"
                        >
                            <div className="h-9 w-9 shrink-0 rounded-xl bg-black dark:bg-white text-white dark:text-black flex items-center justify-center font-bold shadow-sm">
                                <Stethoscope className="w-5 h-5" />
                            </div>
                            <span
                                className={`font-bold tracking-tight text-base whitespace-nowrap transition-all duration-200 ${
                                    isExpanded ? 'opacity-100 translate-x-0' : 'opacity-0 -translate-x-4 pointer-events-none'
                                }`}
                            >
                                {appName}
                            </span>
                        </Link>

                        {/* Collapse / Minimal Toggle Button */}
                        <button
                            type="button"
                            onClick={() => {
                                setIsCollapsed(!isCollapsed);
                                setIsHovered(false);
                            }}
                            title={isCollapsed ? 'Expand sidebar' : 'Make minimal (icons only)'}
                            className="p-2 rounded-lg text-neutral-500 hover:text-black dark:text-neutral-400 dark:hover:text-white hover:bg-neutral-100 dark:hover:bg-zinc-800/70 transition-colors focus:outline-none"
                        >
                            {isCollapsed ? (
                                <PanelLeftOpen className="w-5 h-5" />
                            ) : (
                                <PanelLeftClose className="w-5 h-5" />
                            )}
                        </button>
                    </div>

                    {/* Navigation Tabs */}
                    <nav className="p-3 space-y-1.5 overflow-y-auto max-h-[calc(100vh-140px)]">
                        {navigationItems.map((item) => {
                            const active = isCurrentActive(item);
                            const Icon = item.icon;

                            return (
                                <Link
                                    key={item.name}
                                    href={item.href}
                                    className={`
                                        group relative flex items-center gap-3.5 px-3 py-2.5 rounded-xl text-sm font-medium transition-all duration-150
                                        ${
                                            active
                                                ? 'bg-white text-black border-2 border-black dark:bg-zinc-900 dark:text-white dark:border-white shadow-none font-semibold'
                                                : 'text-neutral-600 hover:text-black hover:bg-neutral-100/80 border-2 border-transparent dark:text-neutral-400 dark:hover:text-white dark:hover:bg-zinc-900/60'
                                        }
                                        ${!isExpanded ? 'justify-center px-0' : ''}
                                    `}
                                >
                                    <Icon
                                        className={`w-5 h-5 shrink-0 transition-transform group-hover:scale-105 ${
                                            active
                                                ? 'text-black dark:text-white'
                                                : 'text-neutral-500 group-hover:text-black dark:text-neutral-400 dark:group-hover:text-white'
                                        }`}
                                    />

                                    {/* Tab Label */}
                                    <span
                                        className={`whitespace-nowrap transition-all duration-200 ${
                                            isExpanded
                                                ? 'opacity-100 inline-block'
                                                : 'opacity-0 hidden'
                                        }`}
                                    >
                                        {item.name}
                                    </span>

                                    {/* Tooltip for collapsed mode when not hovered/spread */}
                                    {!isExpanded && (
                                        <span className="pointer-events-none absolute left-full ml-3 px-2.5 py-1.5 text-xs font-semibold rounded-lg bg-neutral-900 text-white dark:bg-white dark:text-black shadow-lg opacity-0 -translate-x-1 group-hover:opacity-100 group-hover:translate-x-0 transition-all z-50 whitespace-nowrap hidden lg:block">
                                            {item.name}
                                        </span>
                                    )}
                                </Link>
                            );
                        })}
                    </nav>
                </div>

                {/* Bottom Section: Admin Link & Dark Mode Toggle */}
                <div className="p-3 border-t border-neutral-100 dark:border-zinc-800/80 space-y-1.5">
                    {/* Filament Admin Link */}
                    <a
                        href="/admin"
                        className={`
                            group relative flex items-center gap-3.5 px-3 py-2.5 rounded-xl text-sm font-medium transition-all duration-150
                            text-neutral-600 hover:text-black hover:bg-neutral-100/80 border-2 border-transparent dark:text-neutral-400 dark:hover:text-white dark:hover:bg-zinc-900/60
                            ${!isExpanded ? 'justify-center px-0' : ''}
                        `}
                    >
                        <Lock className="w-5 h-5 shrink-0 text-neutral-500 group-hover:text-black dark:text-neutral-400 dark:group-hover:text-white" />
                        <span
                            className={`whitespace-nowrap flex items-center gap-1.5 transition-all duration-200 ${
                                isExpanded ? 'opacity-100 inline-block' : 'opacity-0 hidden'
                            }`}
                        >
                            Filament Admin
                            <ExternalLink className="w-3.5 h-3.5 text-neutral-400 inline" />
                        </span>

                        {!isExpanded && (
                            <span className="pointer-events-none absolute left-full ml-3 px-2.5 py-1.5 text-xs font-semibold rounded-lg bg-neutral-900 text-white dark:bg-white dark:text-black shadow-lg opacity-0 -translate-x-1 group-hover:opacity-100 group-hover:translate-x-0 transition-all z-50 whitespace-nowrap hidden lg:block">
                                Filament Admin
                            </span>
                        )}
                    </a>

                    {/* Dark Mode Toggle Button */}
                    <button
                        type="button"
                        onClick={toggleTheme}
                        className={`
                            w-full group relative flex items-center gap-3.5 px-3 py-2.5 rounded-xl text-sm font-medium transition-all duration-150
                            text-neutral-600 hover:text-black hover:bg-neutral-100/80 border-2 border-transparent dark:text-neutral-400 dark:hover:text-white dark:hover:bg-zinc-900/60
                            ${!isExpanded ? 'justify-center px-0' : ''}
                        `}
                        title={theme === 'dark' ? 'Switch to Light Mode' : 'Switch to Dark Mode'}
                    >
                        {theme === 'dark' ? (
                            <Sun className="w-5 h-5 shrink-0 text-amber-400 group-hover:rotate-45 transition-transform" />
                        ) : (
                            <Moon className="w-5 h-5 shrink-0 text-neutral-600 group-hover:text-black transition-transform" />
                        )}

                        <span
                            className={`whitespace-nowrap transition-all duration-200 ${
                                isExpanded ? 'opacity-100 inline-block' : 'opacity-0 hidden'
                            }`}
                        >
                            {theme === 'dark' ? 'Light Mode' : 'Dark Mode'}
                        </span>

                        {!isExpanded && (
                            <span className="pointer-events-none absolute left-full ml-3 px-2.5 py-1.5 text-xs font-semibold rounded-lg bg-neutral-900 text-white dark:bg-white dark:text-black shadow-lg opacity-0 -translate-x-1 group-hover:opacity-100 group-hover:translate-x-0 transition-all z-50 whitespace-nowrap hidden lg:block">
                                {theme === 'dark' ? 'Light Mode' : 'Dark Mode'}
                            </span>
                        )}
                    </button>
                </div>
            </aside>
        </>
    );
}
