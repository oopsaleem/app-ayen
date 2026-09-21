import { Head, router } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import WaiterOrderController from '@/actions/App/Http/Controllers/Waiter/WaiterOrderController';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';

type OrderSummary = {
    id: number;
    status: 'awaiting_pickup';
    total: string;
    created_at?: string;
    restaurant: { name_en: string };
};

export default function WaiterOrders({ orders }: { orders: OrderSummary[] }) {
    const { t } = useTranslation();

    function handOff(order: OrderSummary) {
        router.post(
            WaiterOrderController.handOff({ order: order.id }),
            {},
            { preserveScroll: true },
        );
    }

    return (
        <>
            <Head title={t('waiter.orders.page_title')} />

            <div className="space-y-6">
                <Heading variant="small" title={t('waiter.orders.heading')} />

                {orders.length === 0 ? (
                    <p className="py-8 text-center text-muted-foreground">
                        {t('waiter.orders.no_orders')}
                    </p>
                ) : (
                    <div className="space-y-4">
                        {orders.map((order) => (
                            <div
                                key={order.id}
                                className="flex items-center justify-between gap-4 rounded-lg border p-4"
                            >
                                <div className="font-medium">
                                    #{order.id} · {order.restaurant.name_en}
                                </div>
                                <div className="flex items-center gap-2">
                                    <span className="text-sm text-muted-foreground">
                                        {order.total}
                                    </span>
                                    <Button
                                        size="sm"
                                        onClick={() => handOff(order)}
                                    >
                                        {t('waiter.orders.hand_off')}
                                    </Button>
                                </div>
                            </div>
                        ))}
                    </div>
                )}
            </div>
        </>
    );
}
