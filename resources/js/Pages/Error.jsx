import React from 'react';
import { Head, Link } from '@inertiajs/react';
import { ShieldAlert, FileQuestion, RefreshCw, AlertTriangle, ArrowLeft, Home, Stethoscope } from 'lucide-react';
import { useLocale } from '../Contexts/LocaleContext';

export default function Error({ status = 404, appName = 'DentalCare' }) {
    const { t, locale } = useLocale();

    const titleMap = {
        403: {
            en: '403 - Access Forbidden',
            ar: '403 - غير مصرح بالوصول',
            fr: '403 - Accès Interdit',
        },
        404: {
            en: '404 - Page Not Found',
            ar: '404 - الصفحة غير موجودة',
            fr: '404 - Page Non Trouvée',
        },
        419: {
            en: '419 - Session Expired',
            ar: '419 - انتهت الجلسة',
            fr: '419 - Session Expirée',
        },
        500: {
            en: '500 - Server Error',
            ar: '500 - خطأ في الخادم',
            fr: '500 - Erreur Serveur',
        },
    };

    const descriptionMap = {
        403: {
            en: 'You do not have authorization to view this page or resource. Please contact your clinic administrator if you believe this is an error.',
            ar: 'ليس لديك صلاحية لعرض هذه الصفحة أو المورد. يرجى الاتصال بمسؤول العيادة إذا كنت تعتقد أن هذا خطأ.',
            fr: 'Vous n\'avez pas l\'autorisation d\'accéder à cette page. Veuillez contacter votre administrateur.',
        },
        404: {
            en: 'The page or resource you are looking for does not exist or has been moved.',
            ar: 'الصفحة أو المورد الذي تبحث عنه غير موجود أو تم نقله.',
            fr: 'La page ou la ressource que vous recherchez n\'existe pas ou a été déplacée.',
        },
        419: {
            en: 'Your security token or session has expired due to inactivity. Please refresh the page and try again.',
            ar: 'انتهت صلاحية رمز الأمان أو الجلسة بسبب عدم النشاط. يرجى تحديث الصفحة والمحاولة مرة أخرى.',
            fr: 'Votre session a expiré en raison d\'une inactivité. Veuillez rafraîchir la page.',
        },
        500: {
            en: 'An unexpected internal error occurred on our server. Our team has been notified. Please try again shortly.',
            ar: 'حدث خطأ داخلي غير متوقع في الخادم. تم إخطار فريقنا. يرجى المحاولة مرة أخرى قريباً.',
            fr: 'Une erreur interne inattendue s\'est produite. Veuillez réessayer sous peu.',
        },
    };

    const icons = {
        403: ShieldAlert,
        404: FileQuestion,
        419: RefreshCw,
        500: AlertTriangle,
    };

    const Icon = icons[status] || AlertTriangle;
    const currentLang = locale || 'en';

    const displayTitle = titleMap[status]?.[currentLang] || titleMap[status]?.en || `Error ${status}`;
    const displayDesc = descriptionMap[status]?.[currentLang] || descriptionMap[status]?.en || 'An unexpected error occurred.';

    return (
        <div className="min-h-screen bg-slate-950 text-slate-100 flex flex-col items-center justify-center p-6 relative overflow-hidden font-sans selection:bg-teal-500 selection:text-white">
            <Head title={displayTitle} />

            {/* Ambient Background Glows */}
            <div className="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[800px] h-[500px] bg-gradient-to-tr from-teal-500/15 via-cyan-500/10 to-indigo-500/15 blur-[140px] rounded-full pointer-events-none" />

            {/* Header Brand */}
            <div className="mb-8 flex items-center gap-3 relative z-10">
                <div className="h-10 w-10 rounded-2xl bg-gradient-to-tr from-teal-400 to-cyan-600 flex items-center justify-center shadow-lg shadow-teal-500/20">
                    <Stethoscope className="h-6 w-6 text-slate-950 font-bold" />
                </div>
                <span className="text-2xl font-extrabold tracking-tight text-white">
                    {appName}
                </span>
            </div>

            {/* Main Error Card */}
            <div className="max-w-md w-full bg-slate-900/90 border border-slate-800 backdrop-blur-2xl rounded-3xl p-8 sm:p-10 shadow-2xl relative z-10 text-center space-y-6">
                <div className="mx-auto w-16 h-16 rounded-2xl bg-teal-500/10 border border-teal-500/20 flex items-center justify-center text-teal-400">
                    <Icon className="w-8 h-8" />
                </div>

                <div className="space-y-2">
                    <h1 className="text-2xl sm:text-3xl font-bold tracking-tight text-white">
                        {displayTitle}
                    </h1>
                    <p className="text-sm text-slate-400 leading-relaxed">
                        {displayDesc}
                    </p>
                </div>

                {/* Actions */}
                <div className="pt-2 flex flex-col sm:flex-row items-center justify-center gap-3">
                    {status === 419 ? (
                        <button
                            type="button"
                            onClick={() => window.location.reload()}
                            className="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-6 py-3 rounded-xl bg-teal-500 hover:bg-teal-400 text-slate-950 font-bold text-sm shadow-lg shadow-teal-500/20 transition-all"
                        >
                            <RefreshCw className="w-4 h-4" />
                            <span>{t('Refresh Page') || 'Refresh Page'}</span>
                        </button>
                    ) : (
                        <Link
                            href="/dashboard"
                            className="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-6 py-3 rounded-xl bg-teal-500 hover:bg-teal-400 text-slate-950 font-bold text-sm shadow-lg shadow-teal-500/20 transition-all"
                        >
                            <Home className="w-4 h-4" />
                            <span>{t('Return to Dashboard') || 'Return to Dashboard'}</span>
                        </Link>
                    )}
                </div>
            </div>

            {/* Footer Notice */}
            <p className="mt-8 text-xs text-slate-500 relative z-10">
                &copy; {new Date().getFullYear()} {appName} Practice Intelligence Suite.
            </p>
        </div>
    );
}
