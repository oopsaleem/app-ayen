import { Head, router } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import RiderOrderController from '@/actions/App/Http/Controllers/Rider/RiderOrderController';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';

type OrderSummary = {
    id: number;
    status: 'awaiting_delivery' | 'out_for_delivery';
    total: string;
    created_at?: string;
    restaurant: { name_en: string };
};

export default function RiderOrders({ orders }: { orders: OrderSummary[] }) {
    const { t } = useTranslation();

    function act(order: OrderSummary, action: 'pickup' | 'deliver') {
        router.post(
            action === 'pickup'
                ? RiderOrderController.pickup({ order: order.id })
                : RiderOrderController.deliver({ order: order.id }),
            {},
            { preserveScroll: true },
        );
    }

    return (
        <>
            <Head title={t('rider.orders.page_title')} />

            <div className="space-y-6">
                <Heading variant="small" title={t('rider.orders.heading')} />

                {orders.length === 0 ? (
                    <p className="py-8 text-center text-muted-foreground">
                        {t('rider.orders.no_orders')}
                    </p>
                ) : (
                    <div className="space-y-4">
                        {orders.map((order) => (
                            <div
                                key={order.id}
                                className="flex items-center justify-between gap-4 rounded-lg border p-4"
                            >
                                <div className="flex items-center gap-3">
                                    <div className="font-medium">
                                        #{order.id} · {order.restaurant.name_en}
                                    </div>
                                    <Badge
                                        variant={
                                            order.status === 'awaiting_delivery'
                                                ? 'secondary'
                                                : 'default'
                                        }
                                    >
                                        {t(
                                            'rider.orders.status.' +
                                                order.status,
                                        )}
                                    </Badge>
                                </div>
                                <div className="flex items-center gap-2">
                                    <span className="text-sm text-muted-foreground">
                                        {order.total}
                                    </span>
                                    {order.status === 'awaiting_delivery' ? (
                                        <Button
                                            size="sm"
                                            onClick={() => act(order, 'pickup')}
                                        >
                                            {t('rider.orders.pickup')}
                                        </Button>
                                    ) : (
                                        <Button
                                            size="sm"
                                            onClick={() =>
                                                act(order, 'deliver')
                                            }
                                        >
                                            {t('rider.orders.delivered')}
                                        </Button>
                                    )}
                                </div>
                            </div>
                        ))}
                    </div>
                )}
            </div>
        </>
    );
}
