import { usePage } from '@inertiajs/react';
import { useEffect } from 'react';
import { DirectionProvider } from '@/components/ui/direction';
import { useLocale } from '@/hooks/use-locale';
import { dirFor, isAppLocale } from '@/i18n';

export function AppDirectionProvider({
    children,
}: {
    children: React.ReactNode;
}) {
    const { locale } = usePage().props;
    const { locale: storedLocale, resolvedDirection, updateLocale } =
        useLocale();

    useEffect(() => {
        if (isAppLocale(locale) && locale !== storedLocale) {
            updateLocale(locale);
        }
    }, [locale]);

    return (
        <DirectionProvider dir={resolvedDirection}>
            {children}
        </DirectionProvider>
    );
}

export function dirFromProps(locale: unknown): 'ltr' | 'rtl' {
    return dirFor(isAppLocale(locale) ? locale : 'en');
}

export default AppDirectionProvider;
