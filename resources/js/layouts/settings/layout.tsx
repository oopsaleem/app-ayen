import { Link } from '@inertiajs/react';
import type { PropsWithChildren } from 'react';
import { useTranslation } from 'react-i18next';
import Heading from '@/components/heading';
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { toUrl } from '@/lib/utils';
import { edit as editAppearance } from '@/routes/appearance';
import { edit } from '@/routes/profile';
import { edit as editSecurity } from '@/routes/security';
import { index as teams } from '@/routes/teams';
import type { NavItem } from '@/types';

const sidebarNavItems: NavItem[] = [
    {
        title: 'settings.nav.profile',
        href: edit(),
        icon: null,
    },
    {
        title: 'settings.nav.security',
        href: editSecurity(),
        icon: null,
    },
    {
        title: 'settings.nav.teams',
        href: teams(),
        icon: null,
    },
    {
        title: 'settings.nav.appearance',
        href: editAppearance(),
        icon: null,
    },
];

export default function SettingsLayout({ children }: PropsWithChildren) {
    const { isCurrentOrParentUrl } = useCurrentUrl();
    const { t } = useTranslation();

    const activeItem = sidebarNavItems.find((item) =>
        isCurrentOrParentUrl(item.href),
    );

    return (
        <div className="px-4 py-6 pb-20 md:pb-6">
            <Heading
                title={t('settings.title')}
                description={t('settings.description')}
            />

            <div className="md:space-y-8">
                <Tabs
                    value={activeItem ? toUrl(activeItem.href) : undefined}
                    aria-label={t('settings.nav.aria_label')}
                    className="fixed inset-x-0 bottom-0 z-40 border-t bg-background p-2 md:static md:z-auto md:border-t-0 md:bg-transparent md:p-0"
                >
                    <TabsList className="w-full">
                        {sidebarNavItems.map((item) => (
                            <TabsTrigger
                                key={toUrl(item.href)}
                                value={toUrl(item.href)}
                                asChild
                            >
                                <Link href={item.href}>
                                    {item.icon && <item.icon />}
                                    {t(item.title)}
                                </Link>
                            </TabsTrigger>
                        ))}
                    </TabsList>
                </Tabs>

                <section className="max-w-xl space-y-12">{children}</section>
            </div>
        </div>
    );
}
