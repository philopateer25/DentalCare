<?php

namespace App\Services;

use Filament\Facades\Filament;

class LanguageHelper
{
    public const LOCALES = [
        'en' => [
            'name' => 'English',
            'native' => 'English',
            'flag' => '🇺🇸',
            'dir' => 'ltr',
        ],
        'ar' => [
            'name' => 'Arabic',
            'native' => 'العربية',
            'flag' => '🇪🇬',
            'dir' => 'rtl',
        ],
        'fr' => [
            'name' => 'French',
            'native' => 'Français',
            'flag' => '🇫🇷',
            'dir' => 'ltr',
        ],
    ];

    public static function supportedLocales(): array
    {
        return array_keys(self::LOCALES);
    }

    public static function currentLocale(): string
    {
        // 1. Session precedence
        if (session()->has('locale')) {
            $locale = session('locale');
            if (isset(self::LOCALES[$locale])) {
                return $locale;
            }
        }

        // 2. Authenticated user preference
        if (auth()->check()) {
            $userLocale = auth()->user()->locale;
            if ($userLocale && isset(self::LOCALES[$userLocale])) {
                return $userLocale;
            }
        }

        // 3. Filament active tenant practice preference
        try {
            $tenant = Filament::getTenant();
            if ($tenant && !empty($tenant->locale) && isset(self::LOCALES[$tenant->locale])) {
                return $tenant->locale;
            }
        } catch (\Throwable) {
            // Not in Filament tenant context
        }

        // 4. Default database practice preference
        try {
            $practice = \App\Models\Practice::first();
            if ($practice && !empty($practice->locale) && isset(self::LOCALES[$practice->locale])) {
                return $practice->locale;
            }
        } catch (\Throwable) {
            // Database not ready
        }

        return config('app.locale', 'en');
    }

    public static function direction(?string $locale = null): string
    {
        $code = $locale ?? self::currentLocale();
        return self::LOCALES[$code]['dir'] ?? 'ltr';
    }

    public static function isRtl(?string $locale = null): bool
    {
        return self::direction($locale) === 'rtl';
    }

    public static function getOptions(): array
    {
        $options = [];
        foreach (self::LOCALES as $code => $info) {
            $options[$code] = "{$info['flag']} {$info['native']} ({$info['name']})";
        }
        return $options;
    }

    public static function getFrontendTranslations(?string $locale = null): array
    {
        $code = $locale ?? self::currentLocale();

        $translations = [
            'en' => [
                'nav_dashboard' => 'Dashboard',
                'nav_patients' => 'Patients',
                'nav_operations' => 'Operations',
                'nav_finance' => 'Finance',
                'nav_insurance' => 'Insurance',
                'nav_inventory' => 'Inventory',
                'nav_labs' => 'Dental Labs',
                'admin_panel' => 'Filament Admin',
                'open_app' => 'Open App',
                'welcome_title' => 'Next-Gen Dental Practice Intelligence',
                'welcome_subtitle' => 'Unified clinical workflows, 3D odontograms, smart patient management, multi-currency financing, and automated lab operations.',
                'language' => 'Language',
                'currency' => 'Currency',
                'login' => 'Sign In',
                'register' => 'Register Clinic',
                'quick_access' => 'Quick Access',
                'view_all' => 'View All',
                'total_production' => 'Gross Invoiced (Production)',
                'total_collections' => 'Net Collections',
                'total_ar' => 'Total Outstanding A/R',
                'net_profit' => 'Net Cash Profit',
                'collection_rate' => 'Collection Rate',
                'invoices_tab' => 'Invoices & Billing Hub',
                'payments_tab' => 'Payments & Collections',
                'installments_tab' => 'Patient Financing Contracts',
                'commissions_tab' => 'Doctor Commissions & Split Ledgers',
                'expenses_tab' => 'Clinic Operating Expenses',
                'status' => 'Status',
                'amount' => 'Amount',
                'date' => 'Date',
                'patient' => 'Patient',
                'actions' => 'Actions',
            ],
            'ar' => [
                'nav_dashboard' => 'لوحة التحكم',
                'nav_patients' => 'المرضى',
                'nav_operations' => 'العمليات والعيادة',
                'nav_finance' => 'الإدارة المالية',
                'nav_insurance' => 'التأمين الطبي',
                'nav_inventory' => 'المخزون والمستلزمات',
                'nav_labs' => 'معامل الأسنان',
                'admin_panel' => 'لوحة الإدارة',
                'open_app' => 'دخول النظام',
                'welcome_title' => 'الجيل القادم لإدارة عيادات ومراكز طب الأسنان',
                'welcome_subtitle' => 'نظام متكامل يجمع السجلات الطبية، الأودونتوجرام ثلاثي الأبعاد، الجداول السريرية، الحسابات والتمريض، وإدارة المعامل.',
                'language' => 'اللغة',
                'currency' => 'العملة',
                'login' => 'تسجيل الدخول',
                'register' => 'إنشاء حساب عيادة',
                'quick_access' => 'وصول سريع',
                'view_all' => 'عرض الكل',
                'total_production' => 'إجمالي الفواتير (الإنتاجية)',
                'total_collections' => 'صافي التحصيلات',
                'total_ar' => 'المستحقات المعلقة',
                'net_profit' => 'صافي الأرباح النقدية',
                'collection_rate' => 'معدل التحصيل',
                'invoices_tab' => 'مركز الفواتير والمطالبات',
                'payments_tab' => 'المدفوعات والتحصيلات',
                'installments_tab' => 'عقود الأقساط والتمويل',
                'commissions_tab' => 'عمولات الأطباء والنسب',
                'expenses_tab' => 'مصروفات وتشغيل العيادة',
                'status' => 'الحالة',
                'amount' => 'المبلغ',
                'date' => 'التاريخ',
                'patient' => 'المريض',
                'actions' => 'الإجراءات',
            ],
            'fr' => [
                'nav_dashboard' => 'Tableau de Bord',
                'nav_patients' => 'Patients',
                'nav_operations' => 'Opérations',
                'nav_finance' => 'Finances & Trésorerie',
                'nav_insurance' => 'Assurances',
                'nav_inventory' => 'Inventaire & Stocks',
                'nav_labs' => 'Laboratoires Dentaires',
                'admin_panel' => 'Administration',
                'open_app' => 'Ouvrir l\'App',
                'welcome_title' => 'Gestion Intelligente de Cabinet Dentaire',
                'welcome_subtitle' => 'Flux de travail cliniques unifiés, odontogrammes 3D, gestion intelligente des patients, facturation multidevise et opérations de laboratoire.',
                'language' => 'Langue',
                'currency' => 'Devise',
                'login' => 'Connexion',
                'register' => 'Créer un Cabinet',
                'quick_access' => 'Accès Rapide',
                'view_all' => 'Voir Tout',
                'total_production' => 'Total Facturé (Production)',
                'total_collections' => 'Recouvrements Nets',
                'total_ar' => 'Créances en Attente',
                'net_profit' => 'Bénéfice Net de Caisse',
                'collection_rate' => 'Taux de Recouvrement',
                'invoices_tab' => 'Factures & Facturation',
                'payments_tab' => 'Paiements & Encaissements',
                'installments_tab' => 'Plans d\'Échelonnement',
                'commissions_tab' => 'Commissions des Praticiens',
                'expenses_tab' => 'Dépenses du Cabinet',
                'status' => 'Statut',
                'amount' => 'Montant',
                'date' => 'Date',
                'patient' => 'Patient',
                'actions' => 'Actions',
            ],
        ];

        $jsonPath = base_path("lang/{$code}.json");
        $jsonTranslations = [];
        if (file_exists($jsonPath)) {
            $decoded = json_decode(file_get_contents($jsonPath), true);
            if (is_array($decoded)) {
                $jsonTranslations = $decoded;
            }
        }

        $baseTranslations = $translations[$code] ?? $translations['en'];
        return array_merge($baseTranslations, $jsonTranslations);
    }
}
