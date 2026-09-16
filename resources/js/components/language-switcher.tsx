import { router, usePage } from '@inertiajs/react';
import { Languages } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useLocale } from '@/hooks/use-locale';
import type { AppLocale } from '@/i18n';
import { cn } from '@/lib/utils';
import { update as saveLocale } from '@/routes/locale';

export function LanguageSwitcher({ className }: { className?: string }) {
    const { t } = useTranslation();
    const { locale, updateLocale } = useLocale();

    const { locale: serverLocale, availableLocales } = usePage().props;

    const locales = Object.entries(availableLocales ?? {}) as Array<
        [string, string]
    >;

    if (locales.length < 2) {
        return null;
    }

    const activeLocale = isAppLocale(serverLocale) ? serverLocale : locale;

    const selectLocale = (next: AppLocale) => {
        if (next === activeLocale) {
            return;
        }

        updateLocale(next);

        router.post(saveLocale(), { locale: next }, { preserveScroll: true });
    };

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button
                    variant="ghost"
                    size="icon"
                    className={cn('size-9 cursor-pointer', className)}
                    aria-label={t('language')}
                >
                    <Languages className="size-5 opacity-80" />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end">
                {locales.map(([localeKey, label]) => (
                    <DropdownMenuItem
                        key={localeKey}
                        onClick={() =>
                            isAppLocale(localeKey) && selectLocale(localeKey)
                        }
                        className={
                            localeKey === activeLocale ? 'bg-accent' : undefined
                        }
                    >
                        {label}
                    </DropdownMenuItem>
                ))}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}

function isAppLocale(locale: unknown): locale is AppLocale {
    return locale === 'en' || locale === 'ar';
}

export default LanguageSwitcher;
