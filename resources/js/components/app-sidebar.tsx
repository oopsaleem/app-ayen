import { usePage } from '@inertiajs/react';
import { BookOpen, Building2, ChefHat, FolderGit2, LayoutGrid, MapPin, UtensilsCrossed } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { NavFooter } from '@/components/nav-footer';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import { TeamSwitcher } from '@/components/team-switcher';
import { useDirection } from '@/components/ui/direction';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import { index as addressesIndex } from '@/routes/addresses';
import { index as chefKitchensIndex } from '@/routes/chef/kitchens';
import { index as companiesIndex } from '@/routes/companies';
import { index as restaurantsIndex } from '@/routes/restaurants';
import type { NavItem } from '@/types';

export function AppSidebar() {
    const page = usePage();
    const { t } = useTranslation();
    const dir = useDirection();
    const dashboardUrl = page.props.currentTeam
        ? dashboard(page.props.currentTeam.slug)
        : '/';

    const roles = page.props.auth.roles;

    const mainNavItems: NavItem[] = [
        {
            title: t('app.sidebar.dashboard'),
            href: dashboardUrl,
            icon: LayoutGrid,
        },
        ...(roles.includes('admin')
            ? [{ title: t('app.sidebar.companies'), href: companiesIndex(), icon: Building2 }]
            : []),
        ...(roles.includes('admin') || roles.includes('manager')
            ? [{ title: t('app.sidebar.restaurants'), href: restaurantsIndex(), icon: UtensilsCrossed }]
            : []),
        ...(roles.includes('chef')
            ? [{ title: t('app.sidebar.my_kitchens'), href: chefKitchensIndex(), icon: ChefHat }]
            : []),
        {
            title: t('app.sidebar.addresses'),
            href: addressesIndex(),
            icon: MapPin,
        },
    ];

    const footerNavItems: NavItem[] = [
        {
            title: t('app.sidebar.repository'),
            href: 'https://github.com/laravel/react-starter-kit',
            icon: FolderGit2,
        },
        {
            title: t('app.sidebar.documentation'),
            href: 'https://laravel.com/docs/starter-kits#react',
            icon: BookOpen,
        },
    ];

    return (
        <Sidebar
            dir={dir}
            side={dir === 'rtl' ? 'right' : 'left'}
            collapsible="icon"
            variant="inset"
        >
            <SidebarHeader>
                <SidebarMenu>
                    {/* <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={dashboardUrl} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem> */}
                    <SidebarMenuItem>
                        <TeamSwitcher />
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={mainNavItems} />
            </SidebarContent>

            <SidebarFooter>
                <NavFooter items={footerNavItems} className="mt-auto" />
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
