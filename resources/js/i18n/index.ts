import i18next from 'i18next';
import { initReactI18next } from 'react-i18next';
import ar from '@/i18n/locales/ar.json';
import en from '@/i18n/locales/en.json';

export const SUPPORTED_LOCALES = ['en', 'ar'] as const;
export type AppLocale = (typeof SUPPORTED_LOCALES)[number];

export const isAppLocale = (locale: unknown): locale is AppLocale =>
    typeof locale === 'string' && SUPPORTED_LOCALES.includes(locale as AppLocale);

export const dirFor = (locale: string) => (locale === 'ar' ? 'rtl' : 'ltr');

export function applyDocumentLocale(locale: string): void {
    document.documentElement.lang = locale;
    document.documentElement.dir = dirFor(locale);
}

export const getCookieLocale = (): AppLocale | undefined => {
    if (typeof document === 'undefined') {
        return undefined;
    }

    const match = document.cookie
        .split('; ')
        .find((cookie) => cookie.startsWith('locale='));

    return isAppLocale(match?.slice('locale='.length))
        ? (match?.slice('locale='.length) as AppLocale)
        : undefined;
};

export const i18n = i18next;

i18n.use(initReactI18next).init({
    resources: {
        en: { translation: en },
        ar: { translation: ar },
    },
    lng: getCookieLocale() ?? 'en',
    fallbackLng: 'en',
    interpolation: {
        escapeValue: false,
    },
    react: {
        useSuspense: false,
    },
});

export default i18n;
