import { useSyncExternalStore } from 'react';
import { applyDocumentLocale, i18n, isAppLocale } from '@/i18n';
import type { AppLocale } from '@/i18n';

export type Locale = AppLocale;

export type UseLocaleReturn = {
    readonly locale: Locale;
    readonly updateLocale: (locale: Locale) => void;
};

const listeners = new Set<() => void>();
let currentLocale: Locale = 'en';

const setCookie = (name: string, value: string, days = 365): void => {
    if (typeof document === 'undefined') {
        return;
    }

    const maxAge = days * 24 * 60 * 60;
    document.cookie = `${name}=${value};path=/;max-age=${maxAge};SameSite=Lax`;
};

export const getStoredLocale = (): Locale => {
    if (typeof window === 'undefined') {
        return 'en';
    }

    const stored = localStorage.getItem('locale');

    return isAppLocale(stored) ? stored : 'en';
};

const applyLocale = (locale: Locale): void => {
    if (typeof document === 'undefined') {
        return;
    }

    applyDocumentLocale(locale);

    if (i18n.language !== locale) {
        void i18n.changeLanguage(locale);
    }
};

const subscribe = (callback: () => void) => {
    listeners.add(callback);

    return () => listeners.delete(callback);
};

const notify = (): void => listeners.forEach((listener) => listener());

export function initializeLocale(serverLocale?: unknown): void {
    if (typeof window === 'undefined') {
        return;
    }

    if (!localStorage.getItem('locale')) {
        localStorage.setItem(
            'locale',
            isAppLocale(serverLocale) ? serverLocale : 'en',
        );
    } else {
        setCookie('locale', getStoredLocale());
    }

    currentLocale = getStoredLocale();
    applyLocale(currentLocale);
}

export function useLocale(): UseLocaleReturn {
    const locale: Locale = useSyncExternalStore(
        subscribe,
        () => currentLocale,
        () => 'en',
    );

    const updateLocale = (next: Locale): void => {
        currentLocale = next;

        localStorage.setItem('locale', next);
        setCookie('locale', next);

        applyLocale(next);
        notify();
    };

    return { locale, updateLocale } as const;
}
