import React, { createContext, useContext, useEffect, useState } from 'react';
import { usePage } from '@inertiajs/react';

const defaultLocales = {
    en: { name: 'English', native: 'English', flag: '🇺🇸', dir: 'ltr' },
    ar: { name: 'Arabic', native: 'العربية', flag: '🇪🇬', dir: 'rtl' },
    fr: { name: 'French', native: 'Français', flag: '🇫🇷', dir: 'ltr' },
};

const LocaleContext = createContext({
    locale: 'en',
    direction: 'ltr',
    isRtl: false,
    availableLocales: defaultLocales,
    t: (key, fallback) => fallback || key,
    changeLanguage: () => {},
});

export function LocaleProvider({ children }) {
    const page = usePage();
    const props = page?.props || {};

    const initialLocale = props.locale || 'en';
    const [locale, setLocaleState] = useState(initialLocale);

    const availableLocales = props.availableLocales || defaultLocales;
    const translations = props.translations || {};

    const direction = availableLocales[locale]?.dir || (locale === 'ar' ? 'rtl' : 'ltr');
    const isRtl = direction === 'rtl';

    useEffect(() => {
        if (props.locale && props.locale !== locale) {
            setLocaleState(props.locale);
        }
    }, [props.locale]);

    useEffect(() => {
        const root = document.documentElement;
        root.lang = locale;
        root.dir = direction;
        if (isRtl) {
            root.classList.add('rtl');
        } else {
            root.classList.remove('rtl');
        }
    }, [locale, direction, isRtl]);

    const changeLanguage = (newLocale) => {
        if (newLocale === locale) return;
        setLocaleState(newLocale);
        window.location.href = `/locale/${newLocale}`;
    };

    const t = (key, fallback = '') => {
        return translations[key] || fallback || key;
    };

    return (
        <LocaleContext.Provider
            value={{
                locale,
                direction,
                isRtl,
                availableLocales,
                t,
                changeLanguage,
            }}
        >
            {children}
        </LocaleContext.Provider>
    );
}

export function useLocale() {
    const context = useContext(LocaleContext);
    if (!context) {
        throw new Error('useLocale must be used within a LocaleProvider');
    }
    return context;
}
