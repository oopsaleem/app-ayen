import type { InertiaLinkProps } from '@inertiajs/react';
import { Head, Link } from '@inertiajs/react';
import {
    Bike,
    Building2,
    ChefHat,
    ConciergeBell,
    Receipt,
    UtensilsCrossed,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import PendingInvitationsModal from '@/components/pending-invitations-modal';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { dashboard } from '@/routes';
import { index as addressesIndex } from '@/routes/addresses';
import { index as chefOrdersIndex } from '@/routes/chef/orders';
import { index as companiesIndex } from '@/routes/companies';
import { index as ordersIndex } from '@/routes/orders';
import { index as restaurantsIndex } from '@/routes/restaurants';
import { index as riderOrdersIndex } from '@/routes/rider/orders';
import { index as waiterOrdersIndex } from '@/routes/waiter/orders';
import type { DashboardInvitation } from '@/types';

type AdminSummary = {
    companies: number;
    restaurants: number;
    unverified_restaurants: number;
};

type ManagerSummary = { restaurants: number };
type ChefSummary = { kitchens: number; actionable_orders: number };
type WaiterSummary = { pickups: number };
type RiderSummary = { deliveries: number };
type CustomerSummary = { active_orders: number };

type Props = {
    pendingInvitations?: DashboardInvitation[];
    admin?: AdminSummary | null;
    manager?: ManagerSummary | null;
    chef?: ChefSummary | null;
    waiter?: WaiterSummary | null;
    rider?: RiderSummary | null;
    customer?: CustomerSummary | null;
};

export default function Dashboard({
    pendingInvitations = [],
    admin,
    manager,
    chef,
    waiter,
    rider,
    customer,
}: Props) {
    const [showInvitations, setShowInvitations] = useState(
        pendingInvitations.length > 0,
    );
    const { t } = useTranslation();

    return (
        <>
            <Head title={t('app.sidebar.dashboard')} />
            <PendingInvitationsModal
                invitations={pendingInvitations}
                open={pendingInvitations.length > 0 && showInvitations}
                onOpenChange={setShowInvitations}
            />
            <div className="grid gap-4 p-4 md:grid-cols-2 lg:grid-cols-3">
                {admin ? (
                    <DashboardRoleCard
                        icon={Building2}
                        title={t('dashboard.admin.title')}
                        description={t('dashboard.admin.description')}
                        stats={[
                            t('dashboard.admin.companies', {
                                count: admin.companies,
                            }),
                            t('dashboard.admin.unverified', {
                                count: admin.unverified_restaurants,
                            }),
                        ]}
                        href={companiesIndex()}
                        cta={t('dashboard.admin.cta')}
                    />
                ) : null}

                {manager ? (
                    <DashboardRoleCard
                        icon={UtensilsCrossed}
                        title={t('dashboard.manager.title')}
                        description={t('dashboard.manager.description')}
                        stats={[
                            t('dashboard.manager.restaurants', {
                                count: manager.restaurants,
                            }),
                        ]}
                        href={restaurantsIndex()}
                        cta={t('dashboard.manager.cta')}
                    />
                ) : null}

                {chef ? (
                    <DashboardRoleCard
                        icon={ChefHat}
                        title={t('dashboard.chef.title')}
                        description={t('dashboard.chef.description')}
                        stats={[
                            t('dashboard.chef.kitchens', {
                                count: chef.kitchens,
                            }),
                            t('dashboard.chef.actionable_orders', {
                                count: chef.actionable_orders,
                            }),
                        ]}
                        href={chefOrdersIndex()}
                        cta={t('dashboard.chef.cta')}
                    />
                ) : null}

                {waiter ? (
                    <DashboardRoleCard
                        icon={ConciergeBell}
                        title={t('dashboard.waiter.title')}
                        description={t('dashboard.waiter.description')}
                        stats={[
                            t('dashboard.waiter.pickups', {
                                count: waiter.pickups,
                            }),
                        ]}
                        href={waiterOrdersIndex()}
                        cta={t('dashboard.waiter.cta')}
                    />
                ) : null}

                {rider ? (
                    <DashboardRoleCard
                        icon={Bike}
                        title={t('dashboard.rider.title')}
                        description={t('dashboard.rider.description')}
                        stats={[
                            t('dashboard.rider.deliveries', {
                                count: rider.deliveries,
                            }),
                        ]}
                        href={riderOrdersIndex()}
                        cta={t('dashboard.rider.cta')}
                    />
                ) : null}

                {customer ? (
                    <DashboardRoleCard
                        icon={Receipt}
                        title={t('dashboard.customer.title')}
                        description={t('dashboard.customer.description')}
                        stats={[
                            t('dashboard.customer.active_orders', {
                                count: customer.active_orders,
                            }),
                        ]}
                        href={ordersIndex()}
                        cta={t('dashboard.customer.cta')}
                        secondary={{
                            href: addressesIndex(),
                            label: t('app.sidebar.addresses'),
                        }}
                    />
                ) : null}
            </div>
        </>
    );
}

function DashboardRoleCard({
    icon: Icon,
    title,
    description,
    stats,
    href,
    cta,
    secondary,
}: {
    icon: LucideIcon;
    title: string;
    description: string;
    stats: string[];
    href: NonNullable<InertiaLinkProps['href']>;
    cta: string;
    secondary?: {
        href: NonNullable<InertiaLinkProps['href']>;
        label: string;
    };
}) {
    return (
        <Card>
            <CardHeader>
                <div className="flex items-center gap-2">
                    <Icon className="size-5 text-muted-foreground" />
                    <CardTitle>{title}</CardTitle>
                </div>
            </CardHeader>
            <CardContent className="space-y-4">
                <div>
                    <p className="text-sm text-muted-foreground">
                        {description}
                    </p>
                    <ul className="mt-2 space-y-1 text-sm font-medium">
                        {stats.map((stat) => (
                            <li key={stat}>{stat}</li>
                        ))}
                    </ul>
                </div>

                <div className="flex items-center gap-2">
                    <Button asChild size="sm">
                        <Link href={href}>{cta}</Link>
                    </Button>
                    {secondary ? (
                        <Button asChild size="sm" variant="outline">
                            <Link href={secondary.href}>{secondary.label}</Link>
                        </Button>
                    ) : null}
                </div>
            </CardContent>
        </Card>
    );
}

Dashboard.layout = (props: { currentTeam?: { slug: string } | null }) => ({
    breadcrumbs: [
        {
            title: 'app.sidebar.dashboard',
            href: props.currentTeam ? dashboard(props.currentTeam.slug) : '/',
        },
    ],
});
