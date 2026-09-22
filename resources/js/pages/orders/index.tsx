import { Head, router } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import OrderController from '@/actions/App/Http/Controllers/Orders/OrderController';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';

type OrderSummary = {
    id: number;
    status:
        | 'pending'
        | 'confirmed'
        | 'preparing'
        | 'awaiting_delivery'
        | 'awaiting_pickup'
        | 'out_for_delivery'
        | 'closed'
        | 'cancelled';
    delivery_mode: 'delivery' | 'pickup';
    total: string;
    created_at?: string;
    restaurant: { name_en: string };
    can_cancel: boolean;
};

export default function OrdersIndex({ orders }: { orders: OrderSummary[] }) {
    const { t } = useTranslation();

    function cancel(order: OrderSummary) {
        router.post(
            OrderController.cancel({ order: order.id }),
            {},
            { preserveScroll: true },
        );
    }

    return (
        <>
            <Head title={t('orders.index.page_title')} />

            <div className="space-y-6">
                <Heading variant="small" title={t('orders.index.heading')} />

                {orders.length === 0 ? (
                    <p className="py-8 text-center text-muted-foreground">
                        {t('orders.index.no_orders')}
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
                                            order.status === 'cancelled'
                                                ? 'destructive'
                                                : order.status === 'closed'
                                                  ? 'default'
                                                  : 'secondary'
                                        }
                                    >
                                        {t(
                                            'orders.index.status.' +
                                                order.status,
                                        )}
                                    </Badge>
                                    <span className="text-sm text-muted-foreground">
                                        {t(
                                            'orders.delivery_mode.' +
                                                order.delivery_mode,
                                        )}
                                    </span>
                                </div>
                                <div className="flex items-center gap-2">
                                    <span className="text-sm text-muted-foreground">
                                        {order.total}
                                    </span>
                                    {order.can_cancel ? (
                                        <Button
                                            size="sm"
                                            variant="outline"
                                            onClick={() => cancel(order)}
                                        >
                                            {t('orders.index.cancel')}
                                        </Button>
                                    ) : null}
                                </div>
                            </div>
                        ))}
                    </div>
                )}
            </div>
        </>
    );
}
